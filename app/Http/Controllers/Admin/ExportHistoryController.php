<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExportHistory;
use Illuminate\Contracts\View\View;

class ExportHistoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('access-admin-area');

        $history = ExportHistory::query()
            ->with(['user', 'reference'])
            ->latest('id')
            ->paginate(40);

        return view('admin.export-history.index', compact('history'));
    }
}
