<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TravelGroup;
use App\Models\TravelPackage;
use Illuminate\View\View;

class CmsCatalogController extends Controller
{
    public function packages(): View
    {
        $this->authorize('access-admin-area');

        $packages = TravelPackage::query()->orderBy('title')->paginate(30);

        return view('admin.cms.packages-index', compact('packages'));
    }

    public function groups(): View
    {
        $this->authorize('access-admin-area');

        $groups = TravelGroup::query()->with('package')->orderBy('name')->paginate(30);

        return view('admin.cms.groups-index', compact('groups'));
    }
}
