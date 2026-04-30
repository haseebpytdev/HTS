<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = LandingPage::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('frontend/landing-pages/show', compact('page'));
    }
}
