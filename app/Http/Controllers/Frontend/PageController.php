<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Content\ContentSettingsService;
use Illuminate\Contracts\View\View;

class PageController extends Controller
{
    public function __construct(
        private readonly ContentSettingsService $contentSettings,
    ) {
    }

    public function about(): View
    {
        return view('frontend.about', [
            'cmsSettings' => $this->contentSettings->frontendSettings(),
        ]);
    }

    public function contact(): View
    {
        return view('frontend.contact', [
            'cmsSettings' => $this->contentSettings->frontendSettings(),
        ]);
    }

    public function bank(): View
    {
        return view('frontend.bank', [
            'cmsSettings' => $this->contentSettings->frontendSettings(),
        ]);
    }
}
