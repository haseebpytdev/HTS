<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadAirportDirectoryRequest;
use App\Services\Travel\AirportDirectoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AirportDirectoryController extends Controller
{
    public function __construct(
        private readonly AirportDirectoryService $airports,
    ) {
    }

    public function edit(): View
    {
        $this->authorize('manage-tenancy-settings');

        return view('admin.system.airports', [
            'meta' => $this->airports->localDatasetMeta(),
        ]);
    }

    public function update(UploadAirportDirectoryRequest $request): RedirectResponse
    {
        $this->authorize('manage-tenancy-settings');

        $result = $this->airports->replaceLocalDataset($request->file('airports_file'));
        if (($result['count'] ?? 0) <= 0) {
            return redirect()->route('admin.system.airports.edit')
                ->withErrors(['airports_file' => 'Uploaded file does not contain valid airport rows.']);
        }

        return redirect()->route('admin.system.airports.edit')
            ->with('success', sprintf('Airport list updated successfully (%d rows).', $result['count']));
    }
}

