<?php

namespace App\Domains\Billing\Gateway\DTO;

/**
 * Output standar dari semua payment provider.
 * Frontend menentukan aksi berdasarkan $type.
 */
class PaymentResult
{
    /**
     * @param string      $provider       Nama provider: midtrans | tripay | xendit
     * @param string      $type           Cara render di frontend:
     *                                     'snap_popup' → Midtrans Snap JS popup
     *                                     'redirect'   → window.location ke $paymentUrl
     *                                     'qris'       → tampilkan gambar QR
     * @param string      $orderId        Order ID yang dikirim ke provider
     * @param string|null $snapToken      Hanya untuk type=snap_popup (Midtrans)
     * @param string|null $paymentUrl     Hanya untuk type=redirect (Tripay, Xendit)
     * @param string|null $qrisImageUrl   Hanya untuk type=qris
     * @param array       $raw            Raw response dari provider (untuk debugging)
     */
    public function __construct(
        public readonly string  $provider,
        public readonly string  $type,
        public readonly string  $orderId,
        public readonly ?string $snapToken    = null,
        public readonly ?string $paymentUrl   = null,
        public readonly ?string $qrisImageUrl = null,
        public readonly array   $raw          = [],
    ) {}

    public function toArray(): array
    {
        return [
            'provider'       => $this->provider,
            'type'           => $this->type,
            'order_id'       => $this->orderId,
            'snap_token'     => $this->snapToken,
            'payment_url'    => $this->paymentUrl,
            'qris_image_url' => $this->qrisImageUrl,
        ];
    }
}
