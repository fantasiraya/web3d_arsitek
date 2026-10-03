<?php

namespace App\Domains\Billing\Gateway\Providers;

use App\Domains\Billing\Gateway\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Gateway\DTO\PaymentData;
use App\Domains\Billing\Gateway\DTO\PaymentResult;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;

/**
 * Tripay Payment Gateway — https://tripay.co.id
 *
 * Cara mendaftar:
 *   1. Daftar di https://tripay.co.id
 *   2. Verifikasi email + KTP (1-2 hari kerja)
 *   3. Ambil API Key, Private Key, Merchant Code dari dashboard
 *
 * Tripay menggunakan model "redirect" — user diarahkan ke halaman
 * pembayaran Tripay yang sudah berisi pilihan metode (QRIS, VA BCA,
 * VA Mandiri, Alfamart, dll).
 *
 * Untuk mengaktifkan: isi tripay_api_key, tripay_private_key,
 * tripay_merchant_code di admin panel → Payment Settings.
 */
class TripayGateway implements PaymentGatewayInterface
{
    private const API_URL_PROD    = 'https://tripay.co.id/api';
    private const API_URL_SANDBOX = 'https://tripay.co.id/api-sandbox';

    public function __construct(
        protected SystemSettingRepository $settings
    ) {}

    public function getName(): string  { return 'tripay'; }
    public function getLabel(): string { return 'Tripay'; }

    public function isConfigured(): bool
    {
        return !empty($this->settings->get('tripay_api_key'))
            && !empty($this->settings->get('tripay_private_key'))
            && !empty($this->settings->get('tripay_merchant_code'));
    }

    public function createTransaction(PaymentData $data): PaymentResult
    {
        $apiKey       = $this->settings->get('tripay_api_key', '');
        $privateKey   = $this->settings->get('tripay_private_key', '');
        $merchantCode = $this->settings->get('tripay_merchant_code', '');
        $isSandbox    = $this->settings->getBool('tripay_is_sandbox', true);
        $baseUrl      = $isSandbox ? self::API_URL_SANDBOX : self::API_URL_PROD;

        // Tripay: signature = HMAC-SHA256(merchantCode + orderId + amount, privateKey)
        $signature = hash_hmac('sha256', $merchantCode . $data->orderId . $data->amount, $privateKey);

        $payload = [
            'method'         => 'QRIS',           // default QRIS, bisa diganti VA dll
            'merchant_ref'   => $data->orderId,
            'amount'         => $data->amount,
            'customer_name'  => $data->customerName,
            'customer_email' => $data->customerEmail,
            'order_items'    => [[
                'sku'      => $data->planSlug,
                'name'     => $data->itemName,
                'price'    => $data->amount,
                'quantity' => 1,
            ]],
            'signature'      => $signature,
            'expired_time'   => time() + (24 * 60 * 60), // 24 jam
            'return_url'     => url('/dashboard?payment=success'),
        ];

        $ch = curl_init($baseUrl . '/transaction/create');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if (!($decoded['success'] ?? false)) {
            \Log::error('[TripayGateway] Error: ' . $response . ' HTTP:' . $httpCode);
            throw new \RuntimeException($decoded['message'] ?? 'Tripay error (HTTP ' . $httpCode . ')');
        }

        $data2 = $decoded['data'] ?? [];

        return new PaymentResult(
            provider:    $this->getName(),
            type:        'redirect',
            orderId:     $data->orderId,
            paymentUrl:  $data2['checkout_url'] ?? null,
            raw:         $decoded,
        );
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        $privateKey = $this->settings->get('tripay_private_key', '');
        $json       = json_encode($payload);
        $expected   = hash_hmac('sha256', $json, $privateKey);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookStatus(array $payload): string
    {
        return match ($payload['status'] ?? '') {
            'PAID'    => 'settlement',
            'EXPIRED' => 'expire',
            'FAILED'  => 'cancel',
            default   => 'pending',
        };
    }

    public function getOrderIdFromWebhook(array $payload): ?string
    {
        return $payload['merchant_ref'] ?? null;
    }
}
