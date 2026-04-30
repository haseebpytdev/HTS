<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLandingPageRequest;
use App\Http\Requests\Admin\UpdateLandingPageRequest;
use App\Models\LandingPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function index(): View
    {
        $pages = LandingPage::query()->latest('id')->paginate(20);

        return view('admin/landing-pages/index', compact('pages'));
    }

    public function create(): View
    {
        return view('admin/landing-pages/create');
    }

    public function store(StoreLandingPageRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by_user_id'] = auth()->id();
        $data['updated_by_user_id'] = auth()->id();
        $data['published_at'] = $data['status'] === 'published' ? now() : null;

        LandingPage::query()->create($data);

        return redirect()->route('admin.landing-pages.index')->with('success', 'Landing page created.');
    }

    public function edit(LandingPage $landing_page): View
    {
        return view('admin/landing-pages/edit', ['page' => $landing_page]);
    }

    public function update(UpdateLandingPageRequest $request, LandingPage $landing_page): RedirectResponse
    {
        $data = $request->validated();
        $data['updated_by_user_id'] = auth()->id();
        if (($data['status'] ?? null) === 'published' && $landing_page->published_at === null) {
            $data['published_at'] = now();
        }

        $landing_page->update($data);

        return redirect()->route('admin.landing-pages.index')->with('success', 'Landing page updated.');
    }
}
