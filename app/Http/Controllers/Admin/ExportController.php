<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RecordExportHistoryAction;
use App\Http\Controllers\Controller;
use App\Models\ExportHistory;
use App\Services\Export\AdminTabularCsvWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function __construct(
        private readonly RecordExportHistoryAction $recordExportHistoryAction,
        private readonly AdminTabularCsvWriter $adminTabularCsvWriter
    ) {
    }

    public function inquiriesCsv(): StreamedResponse
    {
        $this->authorize('access-admin-area');

        $fileName = 'inquiries-'.now()->format('Ymd_His').'.csv';
        $this->recordExportHistoryAction->execute(
            (int) auth()->id(),
            ExportHistory::TYPE_INQUIRIES_CSV,
            null,
            ['file_name' => $fileName]
        );
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];

        return response()->stream(function (): void {
            $handle = fopen('php://output', 'w');
            $this->adminTabularCsvWriter->writeInquiries($handle);
            fclose($handle);
        }, 200, $headers);
    }

    public function bookingsCsv(): StreamedResponse
    {
        $this->authorize('access-admin-area');

        $fileName = 'bookings-'.now()->format('Ymd_His').'.csv';
        $this->recordExportHistoryAction->execute(
            (int) auth()->id(),
            ExportHistory::TYPE_BOOKINGS_CSV,
            null,
            ['file_name' => $fileName]
        );
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
        ];

        return response()->stream(function (): void {
            $handle = fopen('php://output', 'w');
            $this->adminTabularCsvWriter->writeBookings($handle);
            fclose($handle);
        }, 200, $headers);
    }
}
