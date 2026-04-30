<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agency\UpdateAgencyProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $this->authorize('access-agency-area');

        $user = $request->user()->load('agency');

        return view('agency.profile.edit', compact('user'));
    }

    public function update(UpdateAgencyProfileRequest $request): RedirectResponse
    {
        $this->authorize('access-agency-area');

        $user = $request->user();
        $user->update([
            'name' => $request->validated()['name'],
            'email' => $request->validated()['email'],
        ]);

        if ($user->agency) {
            $user->agency->update([
                'name' => $request->validated()['agency_name'],
                'code' => strtoupper($request->validated()['agency_code']),
            ]);
        }

        return redirect()->route('agency.profile.edit')
            ->with('success', 'Profile and agency settings updated.');
    }
}
