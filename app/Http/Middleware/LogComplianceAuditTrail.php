<?php

namespace App\Http\Middleware;

use App\Services\Compliance\ComplianceAuditService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogComplianceAuditTrail
{
    public function __construct(
        private readonly ComplianceAuditService $auditService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe() && (str_starts_with($request->path(), 'admin/') || str_starts_with($request->path(), 'api/v1/integrations/'))) {
            $this->auditService->record(
                area: str_starts_with($request->path(), 'admin/') ? 'admin' : 'integrations_api',
                action: $request->method().' '.$request->path(),
                severity: $response->getStatusCode() >= 400 ? 'warning' : 'info',
                context: [
                    'status' => $response->getStatusCode(),
                    'route' => $request->route()?->getName(),
                ],
                request: $request
            );
        }

        return $response;
    }
}
