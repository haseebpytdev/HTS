<?php

namespace App\Services\Payment\Gateways;

use App\Contracts\Payments\PaymentGatewayInterface;
use App\Data\Payments\GatewayChargeRequest;
use App\Data\Payments\GatewayChargeResult;
use App\Data\Payments\GatewayRefundRequest;
use App\Data\Payments\GatewayRefundResult;
use App\Enums\GatewayTransactionStatus;
use App\Services\Payment\Exceptions\PaymentProcessingException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Stripe-shaped integration behind {@see PaymentGatewayInterface}.
 * When `payments.stripe.secret` is empty, operations fail fast so callers never assume card capture happened.
 */
final class StripePaymentGateway implements PaymentGatewayInterface
{
    public function driverKey(): string
    {
        return 'stripe';
    }

    public function charge(GatewayChargeRequest $request): GatewayChargeResult
    {
        $secret = trim((string) config('payments.stripe.secret', ''));
        if ($secret === '') {
            throw new PaymentProcessingException('Stripe is not configured (missing secret).');
        }

        $payload = [
            'amount' => (int) round((float) $request->amount * 100),
            'currency' => strtolower($request->currency),
            'metadata' => array_merge($request->metadata, [
                'booking_id' => $request->booking?->id,
            ]),
        ];

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/payment_intents', $payload);

        if (! $response->successful()) {
            return new GatewayChargeResult(
                success: false,
                status: GatewayTransactionStatus::Failed->value,
                externalId: null,
                message: $response->json('error.message') ?? 'Stripe charge failed',
                raw: $response->json(),
            );
        }

        $json = $response->json();
        $status = is_array($json) && isset($json['status']) ? (string) $json['status'] : GatewayTransactionStatus::Initiated->value;
        $succeeded = $status === 'succeeded';

        return new GatewayChargeResult(
            success: $succeeded,
            status: $succeeded ? GatewayTransactionStatus::Succeeded->value : GatewayTransactionStatus::RequiresAction->value,
            externalId: is_array($json) && isset($json['id']) ? (string) $json['id'] : null,
            message: null,
            raw: is_array($json) ? $json : null,
        );
    }

    public function refund(GatewayRefundRequest $request): GatewayRefundResult
    {
        $secret = trim((string) config('payments.stripe.secret', ''));
        if ($secret === '') {
            throw new PaymentProcessingException('Stripe is not configured (missing secret).');
        }

        if ($request->parentExternalId === null || $request->parentExternalId === '') {
            return new GatewayRefundResult(
                success: false,
                status: GatewayTransactionStatus::Failed->value,
                externalRefundId: null,
                message: 'Stripe refund requires parent charge/payment intent id.',
                raw: null,
            );
        }

        $refundBody = [
            'amount' => (int) round((float) $request->amount * 100),
        ];
        if ($request->metadata !== []) {
            foreach ($request->metadata as $key => $value) {
                $refundBody['metadata['.$key.']'] = $value;
            }
        }
        if (Str::startsWith($request->parentExternalId, 'pi_')) {
            $refundBody['payment_intent'] = $request->parentExternalId;
        } elseif (Str::startsWith($request->parentExternalId, 'ch_')) {
            $refundBody['charge'] = $request->parentExternalId;
        } else {
            return new GatewayRefundResult(
                success: false,
                status: GatewayTransactionStatus::Failed->value,
                externalRefundId: null,
                message: 'Stripe refund parent id must be a payment_intent or charge id.',
                raw: null,
            );
        }

        $response = Http::asForm()
            ->withToken($secret)
            ->post('https://api.stripe.com/v1/refunds', $refundBody);

        if (! $response->successful()) {
            return new GatewayRefundResult(
                success: false,
                status: GatewayTransactionStatus::Failed->value,
                externalRefundId: null,
                message: $response->json('error.message') ?? 'Stripe refund failed',
                raw: $response->json(),
            );
        }

        $json = $response->json();
        $status = is_array($json) && isset($json['status']) ? (string) $json['status'] : GatewayTransactionStatus::Initiated->value;

        return new GatewayRefundResult(
            success: $status === 'succeeded',
            status: $status === 'succeeded' ? GatewayTransactionStatus::Succeeded->value : GatewayTransactionStatus::RequiresAction->value,
            externalRefundId: is_array($json) && isset($json['id']) ? (string) $json['id'] : null,
            message: null,
            raw: is_array($json) ? $json : null,
        );
    }
}
