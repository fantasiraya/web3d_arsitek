<?php

namespace App\Domains\Billing\Gateway\Providers;

use App\Domains\Billing\Gateway\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Gateway\DTO\PaymentData;
use App\Domains\Billing\Gateway\DTO\PaymentResult;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;

/**
 * Xendit Payment Gateway — https://xendit.co
 *
 * STATUS: Future (belum aktif) — implementasi stub.
 *
 * Cara mendaftar:
 *   1. Daftar di https://dashboard.xendit.co/register
 *   2. Verifikasi bisnis (bisa perorangan)
 *   3. Ambil Secret Key dari Settings → API Keys
 *
 * Xendit menggunakan model Invoice — user diarahkan ke halaman
 * invoice Xendit yang berisi semua metode pembayaran yang aktif
 * (QRIS, VA, e-wallet OVO/DANA/GoPay, kartu kredit).
 *
 * Keunggulan vs Midtrans:
 *   - API lebih modern (REST JSON pure)
 *   - Lebih mudah setup recurring/subscription
 *   - Disbursement (transfer ke rekening) mudah
 *
 * Untuk mengaktifkan: isi xendit_secret_key, xendit_webhook_token
 * di admin panel → Payment Settings.
 */
class XenditGateway implements PaymentGatewayInterface
{
    private const API_URL = 'https://api.xendit.co';

    public function __construct(
        protected SystemSettingRepository $settings
    ) {}

    public function getName(): string  { return 'xendit'; }
    public function getLabel(): string { return 'Xendit'; }

    public function isConfigured(): bool
    {
        return !empty($this->settings->get('xendit_secret_key'));
    }

    public function createTransaction(PaymentData $data): PaymentResult
    {
        $secretKey = $this->settings->get('xendit_secret_key', '');

        $payload = [
            'external_id'      => $data->orderId,
            'amount'           => $data->amount,
            'payer_email'      => $data->customerEmail,
            'description'      => $data->itemName,
            'customer'         => [
                'given_names' => $data->customerName,
                'email'       => $data->customerEmail,
            ],
            'items' => [[
                'name'     => $data->itemName,
                'quantity' => 1,
                'price'    => $data->amount,
                'category' => 'subscription',
            ]],
            'invoice_duration'  => 86400, // 24 jam
            'success_redirect_url' => url('/dashboard?payment=success'),
            'failure_redirect_url' => url('/checkout/' . $data->planSlug . '?payment=failed'),
            'currency'          => 'IDR',
        ];

        $ch = curl_init(self::API_URL . '/v2/invoices');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($secretKey . ':'),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode !== 200 || empty($decoded['invoice_url'])) {
            \Log::error('[XenditGateway] Error: ' . $response . ' HTTP:' . $httpCode);
            throw new \RuntimeException($decoded['message'] ?? 'Xendit error (HTTP ' . $httpCode . ')');
        }

        return new PaymentResult(
            provider:   $this->getName(),
            type:       'redirect',
            orderId:    $data->orderId,
            paymentUrl: $decoded['invoice_url'],
            raw:        $decoded,
        );
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        $webhookToken = $this->settings->get('xendit_webhook_token', '');
        return hash_equals($webhookToken, $signature);
    }

    public function parseWebhookStatus(array $payload): string
    {
        return match ($payload['status'] ?? '') {
            'PAID'    => 'settlement',
            'EXPIRED' => 'expire',
            default   => 'pending',
        };
    }

    public function getOrderIdFromWebhook(array $payload): ?string
    {
        return $payload['external_id'] ?? null;
    }
}
