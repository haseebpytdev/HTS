<?php

namespace App\Actions\Admin;

use App\Models\ExportHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class RecordExportHistoryAction
{
    public function execute(
        int $userId,
        string $exportType,
        ?Model $reference = null,
        array $meta = [],
        ?Request $request = null
    ): ExportHistory {
        $request ??= request();

        return ExportHistory::query()->create([
            'user_id' => $userId,
            'export_type' => $exportType,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
            'meta' => $meta ?: null,
            'ip_address' => $request->ip(),
        ]);
    }
}
