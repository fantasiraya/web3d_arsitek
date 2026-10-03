<?php

namespace App\Domains\Billing\Gateway;

use App\Domains\Billing\Gateway\Contracts\PaymentGatewayInterface;
use App\Domains\Billing\Gateway\DTO\PaymentData;
use App\Domains\Billing\Gateway\DTO\PaymentResult;
use App\Domains\Billing\Gateway\Providers\MidtransGateway;
use App\Domains\Billing\Gateway\Providers\TripayGateway;
use App\Domains\Billing\Gateway\Providers\XenditGateway;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Illuminate\Container\Container;

/**
 * Mengelola semua payment provider.
 *
 * Priority: primary → fallback → (error)
 * Future providers tidak dipakai secara otomatis — hanya tersedia untuk dikonfigurasi.
 *
 * Cara tambah provider baru:
 *   1. Buat class di Providers/ yang implement PaymentGatewayInterface
 *   2. Daftarkan di $availableProviders di bawah
 *   3. Tambah field config di admin panel
 *   Selesai — tidak ada perubahan lain.
 */
class PaymentGatewayManager
{
    /**
     * Semua provider yang tersedia.
     * Key = nama provider (getName()), value = class name.
     */
    private array $availableProviders = [
        'midtrans' => MidtransGateway::class,
        'tripay'   => TripayGateway::class,
        'xendit'   => XenditGateway::class,
    ];

    /** Provider yang sudah di-instantiate (cache) */
    private array $instances = [];

    public function __construct(
        protected SystemSettingRepository $settings,
        protected Container $container,
    ) {}

    /**
     * Resolve provider berdasarkan nama.
     */
    public function driver(string $name): PaymentGatewayInterface
    {
        if (!isset($this->instances[$name])) {
            if (!isset($this->availableProviders[$name])) {
                throw new \InvalidArgumentException("Payment provider [{$name}] tidak dikenal.");
            }
            $this->instances[$name] = $this->container->make($this->availableProviders[$name]);
        }

        return $this->instances[$name];
    }

    /**
     * Resolve provider aktif dengan fallback otomatis.
     * Urutan: primary → fallback (jika primary gagal / tidak dikonfigurasi)
     *
     * @throws \RuntimeException jika semua provider gagal
     */
    public function resolveActive(): PaymentGatewayInterface
    {
        $primary  = $this->settings->get('payment_provider_primary', 'midtrans');
        $fallback = $this->settings->get('payment_provider_fallback', '');

        $primaryDriver = $this->driver($primary);
        if ($primaryDriver->isConfigured()) {
            return $primaryDriver;
        }

        if ($fallback && $fallback !== $primary) {
            $fallbackDriver = $this->driver($fallback);
            if ($fallbackDriver->isConfigured()) {
                \Log::warning("[PaymentGateway] Primary [{$primary}] tidak terkonfigurasi, pakai fallback [{$fallback}]");
                return $fallbackDriver;
            }
        }

        throw new \RuntimeException("Tidak ada payment provider yang terkonfigurasi. Silakan atur di admin panel.");
    }

    /**
     * Buat transaksi dengan provider aktif + auto-fallback.
     * Jika primary throw exception (misal API down), coba fallback.
     */
    public function createTransaction(PaymentData $data): PaymentResult
    {
        $primary  = $this->settings->get('payment_provider_primary', 'midtrans');
        $fallback = $this->settings->get('payment_provider_fallback', '');

        try {
            $driver = $this->driver($primary);
            if (!$driver->isConfigured()) {
                throw new \RuntimeException("Provider [{$primary}] tidak dikonfigurasi.");
            }
            return $driver->createTransaction($data);
        } catch (\Throwable $e) {
            \Log::error("[PaymentGateway] Primary [{$primary}] gagal: " . $e->getMessage());

            if ($fallback && $fallback !== $primary) {
                try {
                    $fallbackDriver = $this->driver($fallback);
                    if ($fallbackDriver->isConfigured()) {
                        \Log::warning("[PaymentGateway] Pakai fallback [{$fallback}]");
                        return $fallbackDriver->createTransaction($data);
                    }
                } catch (\Throwable $e2) {
                    \Log::error("[PaymentGateway] Fallback [{$fallback}] juga gagal: " . $e2->getMessage());
                }
            }

            throw new \RuntimeException('Gagal menginisialisasi payment gateway: ' . $e->getMessage());
        }
    }

    /**
     * Ambil semua provider yang tersedia beserta status konfigurasinya.
     * Dipakai di admin panel untuk tampilkan daftar provider.
     */
    public function getProvidersStatus(): array
    {
        return collect($this->availableProviders)
            ->map(function (string $class, string $name) {
                $driver = $this->driver($name);
                return [
                    'name'          => $name,
                    'label'         => $driver->getLabel(),
                    'is_configured' => $driver->isConfigured(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Resolve provider dari nama untuk proses webhook.
     * Dicari berdasarkan payment_type yang disimpan di Transaction.
     */
    public function driverForWebhook(string $providerName): ?PaymentGatewayInterface
    {
        try {
            return $this->driver($providerName);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
