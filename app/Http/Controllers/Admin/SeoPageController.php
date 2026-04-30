<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterSeoPageRequest;
use App\Http\Requests\Admin\StoreSeoPageRequest;
use App\Http\Requests\Admin\UpdateSeoPageRequest;
use App\Models\SeoPage;
use App\Repositories\SeoPageRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeoPageController extends Controller
{
    public function __construct(
        private readonly SeoPageRepository $seoPageRepository
    ) {
    }

    public function index(FilterSeoPageRequest $request): View
    {
        $this->authorize('access-admin-area');

        $filters = $request->validated();
        $pages = $this->seoPageRepository->paginateForAdmin($filters);

        return view('admin.seo-pages.index', compact('pages', 'filters'));
    }

    public function create(): View
    {
        $this->authorize('access-admin-area');

        return view('admin.seo-pages.create');
    }

    public function store(StoreSeoPageRequest $request): RedirectResponse
    {
        $this->authorize('access-admin-area');

        SeoPage::query()->create($request->toSeoPageAttributes());

        return redirect()->route('admin.seo-pages.index')
            ->with('success', 'SEO page created.');
    }

    public function edit(SeoPage $seo_page): View
    {
        $this->authorize('access-admin-area');

        return view('admin.seo-pages.edit', ['seoPage' => $seo_page]);
    }

    public function update(UpdateSeoPageRequest $request, SeoPage $seo_page): RedirectResponse
    {
        $this->authorize('access-admin-area');

        $seo_page->update($request->toSeoPageAttributes());

        return redirect()->route('admin.seo-pages.index')
            ->with('success', 'SEO page updated.');
    }

    public function destroy(SeoPage $seo_page): RedirectResponse
    {
        $this->authorize('access-admin-area');
        $seo_page->delete();

        return redirect()->route('admin.seo-pages.index')
            ->with('success', 'SEO page deleted.');
    }
}
