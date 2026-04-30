<?php

namespace App\Actions\Admin;

use App\Models\Quotation;
use Illuminate\Support\Facades\DB;

class DeleteQuotationAction
{
    public function execute(Quotation $quotation): void
    {
        DB::transaction(function () use ($quotation): void {
            $quotation->items()->delete();
            $quotation->delete();
        });
    }
}
