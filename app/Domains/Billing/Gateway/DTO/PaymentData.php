<?php

namespace App\Domains\Billing\Gateway\DTO;

/**
 * Input standar untuk semua payment provider.
 * CheckoutController cukup isi DTO ini — tidak perlu tahu provider mana yang aktif.
 */
class PaymentData
{
    public function __construct(
        public readonly string $orderId,
        public readonly int    $amount,         // dalam Rupiah (integer, bukan desimal)
        public readonly string $customerName,
        public readonly string $customerEmail,
        public readonly string $itemName,       // nama paket, misal "Pro - Bulanan"
        public readonly string $planSlug,
        public readonly string $billingType,    // monthly | annual | lifetime
    ) {}
}
