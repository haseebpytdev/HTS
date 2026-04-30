<?php

namespace App\Contracts\Payments;

use App\Data\Payments\GatewayChargeRequest;
use App\Data\Payments\GatewayChargeResult;
use App\Data\Payments\GatewayRefundRequest;
use App\Data\Payments\GatewayRefundResult;

interface PaymentGatewayInterface
{
    /**
     * Stable driver key used for persistence (e.g. manual, stripe).
     */
    public function driverKey(): string;

    public function charge(GatewayChargeRequest $request): GatewayChargeResult;

    public function refund(GatewayRefundRequest $request): GatewayRefundResult;
}
