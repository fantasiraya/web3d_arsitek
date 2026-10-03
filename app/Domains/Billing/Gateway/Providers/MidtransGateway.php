<?php

namespace App\Domains\Billing\Gateway\Providers;

use App\Domains\Billing\Gateway\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Gateway\DTO\PaymentData;
use App\Domains\Billing\Gateway\DTO\PaymentResult;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;

class MidtransGateway implements PaymentGatewayInterface
{
    public function __construct(
        protected SystemSettingRepository $settings
    ) {}

    public function getName(): string  { return 'midtrans'; }
    public function getLabel(): string { return 'Midtrans'; }

    public function isConfigured(): bool
    {
        return !empty($this->settings->get('midtrans_server_key'))
            && !empty($this->settings->get('midtrans_client_key'));
    }

    public function createTransaction(PaymentData $data): PaymentResult
    {
        $serverKey    = $this->settings->get('midtrans_server_key', '');
        $isProduction = $this->settings->getBool('midtrans_is_production');
        $baseUrl      = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $payload = [
            'transaction_details' => [
                'order_id'     => $data->orderId,
                'gross_amount' => $data->amount,
            ],
            'customer_details' => [
                'first_name' => $data->customerName,
                'email'      => $data->customerEmail,
            ],
            'item_details' => [[
                'id'       => $data->orderId,
                'price'    => $data->amount,
                'quantity' => 1,
                'name'     => $data->itemName,
            ]],
        ];

        $ch = curl_init($baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode($serverKey . ':'),
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true);

        if ($httpCode !== 201 || empty($decoded['token'])) {
            \Log::error('[MidtransGateway] Error: ' . $response . ' HTTP:' . $httpCode);
            throw new \RuntimeException($decoded['error_messages'][0] ?? 'Midtrans error (HTTP ' . $httpCode . ')');
        }

        return new PaymentResult(
            provider:   $this->getName(),
            type:       'snap_popup',
            orderId:    $data->orderId,
            snapToken:  $decoded['token'],
            raw:        $decoded,
        );
    }

    public function verifyWebhook(array $payload, string $signature): bool
    {
        $serverKey    = $this->settings->get('midtrans_server_key', '');
        $orderId      = $payload['order_id'] ?? '';
        $statusCode   = $payload['status_code'] ?? '';
        $grossAmount  = $payload['gross_amount'] ?? '';

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        return hash_equals($expected, $signature);
    }

    public function parseWebhookStatus(array $payload): string
    {
        $status = $payload['transaction_status'] ?? '';
        $fraud  = $payload['fraud_status'] ?? 'accept';

        return match (true) {
            $status === 'capture' && $fraud === 'accept' => 'settlement',
            $status === 'settlement'                      => 'settlement',
            in_array($status, ['cancel', 'deny'])         => 'cancel',
            $status === 'expire'                          => 'expire',
            default                                       => 'pending',
        };
    }

    public function getOrderIdFromWebhook(array $payload): ?string
    {
        return $payload['order_id'] ?? null;
    }
}
