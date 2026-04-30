<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Frontend\FilterPackageRequest;
use App\Models\Category;
use App\Models\Destination;
use App\Models\TravelPackage;
use App\Services\Seo\SeoPresenter;
use App\Services\System\SystemSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\View as ViewFactory;

class PackageController extends Controller
{
    public function __construct(
        private readonly SeoPresenter $seoPresenter,
        private readonly SystemSettingsService $settingsService,
    ) {
    }

    public function index(FilterPackageRequest $request): View|JsonResponse
    {
        abort_unless($this->isUmrahPackagesEnabled(), 404);

        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 12);

        $packages = TravelPackage::query()
            ->with(['destination', 'category', 'images'])
            ->where('is_active', true)
            ->when(Arr::get($filters, 'q'), function ($query, $q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('title', 'like', "%{$q}%")
                        ->orWhere('excerpt', 'like', "%{$q}%");
                });
            })
            ->when(Arr::get($filters, 'destination'), function ($query, $destination) {
                $query->whereHas('destination', function ($inner) use ($destination) {
                    $inner->where('slug', $destination)->orWhere('name', 'like', "%{$destination}%");
                });
            })
            ->when(Arr::get($filters, 'category'), function ($query, $category) {
                $query->whereHas('category', function ($inner) use ($category) {
                    $inner->where('slug', $category)->orWhere('name', 'like', "%{$category}%");
                });
            })
            ->when(Arr::get($filters, 'price_min'), fn ($query, $min) => $query->where('base_price', '>=', $min))
            ->when(Arr::get($filters, 'price_max'), fn ($query, $max) => $query->where('base_price', '<=', $max))
            ->when(
                Arr::get($filters, 'sort') === 'price_low',
                fn ($query) => $query->orderBy('base_price')
            )
            ->when(
                Arr::get($filters, 'sort') === 'price_high',
                fn ($query) => $query->orderByDesc('base_price')
            )
            ->when(
                ! in_array(Arr::get($filters, 'sort'), ['price_low', 'price_high'], true),
                fn ($query) => $query->latest('id')
            )
            ->paginate($perPage)
            ->withQueryString();

        if ($request->expectsJson() || $request->boolean('ajax')) {
            return response()->json([
                'html' => ViewFactory::make('frontend.packages.partials.list', compact('packages'))->render(),
                'pagination' => ViewFactory::make('frontend.packages.partials.pagination', compact('packages'))->render(),
            ]);
        }

        return view('frontend.packages.index', [
            'packages' => $packages,
            'filters' => $filters,
            'destinations' => Destination::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug']),
            'categories' => Category::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function show(string $slug): View
    {
        abort_unless($this->isUmrahPackagesEnabled(), 404);

        $package = TravelPackage::query()
            ->with([
                'destination',
                'category',
                'images' => fn ($q) => $q->orderByDesc('is_cover')->orderBy('sort_order')->orderBy('id'),
                'departures' => fn ($q) => $q->orderBy('departure_date'),
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $relatedPackages = TravelPackage::query()
            ->where('is_active', true)
            ->whereKeyNot($package->id)
            ->where(function ($query) use ($package) {
                $query->where('destination_id', $package->destination_id)
                    ->orWhere('category_id', $package->category_id);
            })
            ->latest('id')
            ->limit(4)
            ->get();

        $pageSeo = $this->seoPresenter->mergeDetailMeta(
            config('seo.package_detail_page_key'),
            $package->title,
            $package->excerpt
        );

        return view('frontend.packages.show', compact('package', 'relatedPackages', 'pageSeo'));
    }

    private function isUmrahPackagesEnabled(): bool
    {
        return $this->settingsService->getBool(
            key: 'app.feature_flags.umrah_packages_enabled',
            default: true,
            context: ['scope' => 'platform', 'category' => 'app']
        );
    }
}
