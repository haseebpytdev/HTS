<?php

namespace App\Actions\Admin;

use App\Models\Quotation;
use App\Repositories\QuotationRepository;
use Illuminate\Support\Facades\DB;

class DuplicateQuotationAction
{
    public function __construct(private readonly QuotationRepository $quotationRepository)
    {
    }

    public function execute(Quotation $quotation): Quotation
    {
        return DB::transaction(function () use ($quotation): Quotation {
            $copy = Quotation::create([
                ...$quotation->only([
                    'agency_id',
                    'user_id',
                    'inquiry_id',
                    'customer_name',
                    'customer_email',
                    'customer_phone',
                    'travel_date',
                    'return_date',
                    'adults',
                    'children',
                    'infants',
                    'currency',
                    'subtotal',
                    'tax_amount',
                    'discount_amount',
                    'total_amount',
                    'notes',
                    'expires_at',
                ]),
                'quote_number' => $this->quotationRepository->nextQuoteNumber(),
                'status' => 'draft',
            ]);

            foreach ($quotation->items as $item) {
                $copy->items()->create($item->only([
                    'item_type',
                    'reference_type',
                    'reference_id',
                    'title',
                    'description',
                    'quantity',
                    'unit_price',
                    'total_price',
                    'meta',
                ]));
            }

            return $copy;
        });
    }
}
