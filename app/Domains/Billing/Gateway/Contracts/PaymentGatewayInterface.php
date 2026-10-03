<?php

namespace App\Domains\Billing\Gateway\Contracts;

use App\Domains\Billing\Gateway\DTO\PaymentData;
use App\Domains\Billing\Gateway\DTO\PaymentResult;

/**
 * Contract untuk semua payment provider.
 *
 * Cara tambah provider baru:
 *   1. Buat class baru di app/Domains/Billing/Gateway/Providers/
 *   2. Implementasikan interface ini (5 method)
 *   3. Daftarkan di PaymentGatewayManager::$providers
 *   4. Tambah config di admin panel
 *
 * Tidak ada perubahan di CheckoutController atau kode lain.
 */
interface PaymentGatewayInterface
{
    /**
     * Nama unik provider — dipakai sebagai key di DB dan admin panel.
     * Contoh: 'midtrans', 'tripay', 'xendit'
     */
    public function getName(): string;

    /**
     * Label tampilan untuk admin panel.
     */
    public function getLabel(): string;

    /**
     * Apakah provider ini sudah dikonfigurasi dan siap digunakan.
     * Cek keberadaan API key, dsb.
     */
    public function isConfigured(): bool;

    /**
     * Buat transaksi di provider → return PaymentResult standar.
     * Frontend render berdasarkan PaymentResult::$type.
     *
     * @throws \RuntimeException jika provider gagal
     */
    public function createTransaction(PaymentData $data): PaymentResult;

    /**
     * Verifikasi bahwa webhook/callback dari provider adalah asli.
     * Pakai signature/hash yang dikirim provider.
     */
    public function verifyWebhook(array $payload, string $signature): bool;

    /**
     * Parse payload webhook → status transaksi standar.
     *
     * @return string 'settlement' | 'pending' | 'cancel' | 'expire'
     */
    public function parseWebhookStatus(array $payload): string;

    /**
     * Ambil order_id dari payload webhook.
     */
    public function getOrderIdFromWebhook(array $payload): ?string;
}
