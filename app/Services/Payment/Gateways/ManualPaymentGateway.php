<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\GatewayChargeRequest;
use App\Data\Payments\GatewayChargeResult;
use App\Data\Payments\GatewayRefundRequest;
use App\Data\Payments\GatewayRefundResult;
use App\Enums\GatewayTransactionStatus;
use Illuminate\Support\Str;

final class ManualPaymentGateway implements PaymentGatewayInterface
{
    public function driverKey(): string
    {
        return 'manual';
    }

    public function charge(GatewayChargeRequest $request): GatewayChargeResult
    {
        return new GatewayChargeResult(
            success: true,
            status: GatewayTransactionStatus::Succeeded->value,
            externalId: 'manual_'.Str::uuid()->toString(),
            message: null,
            raw: ['simulated' => true],
        );
    }

    public function refund(GatewayRefundRequest $request): GatewayRefundResult
    {
        return new GatewayRefundResult(
            success: true,
            status: GatewayTransactionStatus::Succeeded->value,
            externalRefundId: 'manual_refund_'.Str::uuid()->toString(),
            message: null,
            raw: ['simulated' => true],
        );
    }
}
