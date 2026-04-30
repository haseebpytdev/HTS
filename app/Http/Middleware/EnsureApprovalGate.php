<?php

namespace App\Http\Middleware;

use App\Models\ApprovalRequest;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApprovalGate
{
    public function handle(Request $request, Closure $next, string $requestType, ?string $routeParamName = null): Response
    {
        if ($this->canBypassForLowRiskVariant($request, $requestType, $routeParamName)) {
            return $next($request);
        }

        [$referenceType, $referenceId] = $this->resolveReference($request, $routeParamName);

        $approval = ApprovalRequest::query()
            ->where('request_type', $requestType)
            ->where('status', 'approved')
            ->when($referenceType !== null, fn ($q) => $q->where('reference_type', $referenceType))
            ->when($referenceId !== null, fn ($q) => $q->where('reference_id', $referenceId))
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->first();

        if (! $approval || $approval->reviewed_by_user_id === null) {
            abort(423, 'Action requires approved authorization request.');
        }

        $ttlHours = max(1, (int) config('compliance.approval_gate_ttl_hours', 72));
        if ($approval->reviewed_at === null || $approval->reviewed_at->lt(now()->subHours($ttlHours))) {
            abort(423, 'Approval has expired. Submit a fresh approval request.');
        }
        if ($approval->expires_at !== null && $approval->expires_at->lt(now())) {
            abort(423, 'Approval has expired. Submit a fresh approval request.');
        }

        $approval->update([
            'status' => 'consumed',
            'review_note' => trim((string) ($approval->review_note ?? '')."\nConsumed by user #".(string) ($request->user()?->id ?? 'system').' at '.now()->toDateTimeString()),
            'consumed_at' => now(),
            'consumed_by_user_id' => $request->user()?->id,
        ]);

        return $next($request);
    }

    private function canBypassForLowRiskVariant(Request $request, string $requestType, ?string $routeParamName): bool
    {
        if ($requestType === 'provider_production_activation') {
            $routeTarget = $routeParamName ? $request->route($routeParamName) : null;

            if ($routeTarget instanceof \App\Models\IntegrationConnection) {
                $isActivating = ! (bool) $routeTarget->is_active;
                $isProduction = strtolower((string) $routeTarget->environment) === 'production';

                return ! ($isActivating && $isProduction);
            }

            if ($routeTarget instanceof \App\Models\ServiceModule) {
                $isActivating = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL);
                $environment = strtolower((string) $request->input('environment', ''));
                $isProduction = $environment === 'production';

                return ! ($isActivating && $isProduction);
            }
        }

        if ($requestType === 'provider_production_credential_replace') {
            $credentials = (array) $request->input('credentials', []);
            foreach (array_keys($credentials) as $key) {
                if (is_string($key) && str_starts_with(strtolower($key), 'production:')) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @return array{0:?string,1:?int}
     */
    private function resolveReference(Request $request, ?string $routeParamName): array
    {
        if ($routeParamName === null || trim($routeParamName) === '') {
            return [null, null];
        }

        $value = $request->route($routeParamName);
        if ($value instanceof Model) {
            return [$value::class, (int) $value->getKey()];
        }
        if (is_numeric($value)) {
            return [null, (int) $value];
        }

        return [null, null];
    }
}
