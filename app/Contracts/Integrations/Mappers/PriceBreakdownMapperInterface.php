<?php

namespace App\Contracts\Integrations\Mappers;

use App\Contracts\Integrations\IdentifiesIntegrationProvider;
use App\Data\Integrations\PriceBreakdownData;

/**
 * Maps fare revalidation / price-check JSON to {@see PriceBreakdownData}.
 */
interface PriceBreakdownMapperInterface extends IdentifiesIntegrationProvider
{
    /**
     * @param  array<string, mixed>  $rawSupplierPayload
     */
    public function mapPriceBreakdown(array $rawSupplierPayload): PriceBreakdownData;
}
