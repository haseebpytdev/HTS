<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\FilterGroupRequest;
use App\Models\TravelGroup;
use App\Services\Seo\SeoPresenter;
use App\Services\System\SystemSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View as ViewFactory;

class GroupController extends Controller
{
    public function __construct(
        private readonly SeoPresenter $seoPresenter,
        private readonly SystemSettingsService $settingsService,
    ) {
    }

    public function index(FilterGroupRequest $request): View|JsonResponse
    {
        abort_unless($this->isGroupTicketingEnabled(), 404);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 12);

        $groups = TravelGroup::query()
            ->with(['package', 'images'])
            ->when(Arr::get($filters, 'q'), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('group_type', 'like', "%{$q}%")
                        ->orWhereHas('package', fn ($packageQuery) => $packageQuery->where('title', 'like', "%{$q}%"));
                });
            })
            ->when(Arr::get($filters, 'status'), fn ($query, $status) => $query->where('status', $status))
            ->when(Arr::get($filters, 'departure_from'), fn ($query, $from) => $query->whereDate('departure_date', '>=', $from))
            ->when(Arr::get($filters, 'departure_to'), fn ($query, $to) => $query->whereDate('departure_date', '<=', $to))
            ->when(
                Arr::get($filters, 'sort') === 'earliest_departure',
                fn ($query) => $query->orderBy('departure_date')
            )
            ->when(
                Arr::get($filters, 'sort') === 'seats_left',
                fn ($query) => $query->orderByDesc('seats_left')
            )
            ->when(
                ! in_array(Arr::get($filters, 'sort'), ['earliest_departure', 'seats_left'], true),
                fn ($query) => $query->latest('id')
            )
            ->paginate($perPage)
            ->withQueryString();

        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json([
                'html' => ViewFactory::make('frontend.groups.partials.list', compact('groups'))->render(),
                'pagination' => ViewFactory::make('frontend.groups.partials.pagination', compact('groups'))->render(),
            ]);
        }

        return view('frontend.groups.index', [
            'groups' => $groups,
            'filters' => $filters,
        ]);
    }

    public function show(string $slug): View
    {
        abort_unless($this->isGroupTicketingEnabled(), 404);

        $group = TravelGroup::query()
            ->with([
                'package.destination',
                'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id'),
            ])
            ->where('slug', $slug)
            ->firstOrFail();

        $relatedGroups = TravelGroup::query()
            ->whereKeyNot($group->id)
            ->when($group->package_id, fn ($q) => $q->where('package_id', $group->package_id))
            ->latest('id')
            ->limit(4)
            ->get();

        $pageSeo = $this->seoPresenter->mergeDetailMeta(
            config('seo.group_detail_page_key'),
            $group->name,
            $group->notes
        );

        return view('frontend.groups.show', compact('group', 'relatedGroups', 'pageSeo'));
    }

    private function isGroupTicketingEnabled(): bool
    {
        return $this->settingsService->getBool(
            key: 'app.feature_flags.group_ticketing_enabled',
            default: true,
            context: ['scope' => 'platform', 'category' => 'app']
        );
    }
}
