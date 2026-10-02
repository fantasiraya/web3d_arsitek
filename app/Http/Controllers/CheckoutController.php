<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Plan;
use App\Domains\Billing\Models\Transaction;
use App\Domains\SystemConfig\Repositories\SystemSettingRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function __construct(
        protected SystemSettingRepository $settings
    ) {}

    /**
     * GET /checkout/{plan}
     * Tampilkan halaman checkout untuk plan tertentu.
     */
    public function show(Request $request, string $planSlug): Response|RedirectResponse
    {
        $plan = Plan::where('slug', $planSlug)
            ->where('status', 'active')
            ->firstOrFail();

        // Free plan → langsung ke register
        if ($plan->price_monthly === 'Rp 0' || strtolower($plan->price_monthly) === 'rp 0') {
            return redirect()->route('register');
        }

        // Baca konfigurasi payment
        $gatewayEnabled = $this->settings->get('payment_gateway_enabled', '0') === '1';
        $bankName       = $this->settings->get('bank_name', 'BCA');
        $bankAccount    = $this->settings->get('bank_account_number', '');
        $bankHolder     = $this->settings->get('bank_account_holder', '');

        return Inertia::render('Checkout/Index', [
            'plan' => [
                'id'            => $plan->id,
                'slug'          => $plan->slug,
                'display_name'  => $plan->display_name ?? $plan->name,
                'tagline'       => $plan->tagline ?? '',
                'price_monthly' => $plan->price_monthly,
                'price_annual'  => $plan->price_annual,
                'period_label'  => $plan->period_label,
                'benefits'      => $plan->benefits ?? [],
                'is_featured'   => $plan->is_featured,
            ],
            'gatewayEnabled' => $gatewayEnabled,
            'bankInfo' => [
                'bank_name'          => $bankName,
                'account'            => $bankAccount,
                'holder'             => $bankHolder,
                'whatsapp'           => $this->settings->get('admin_whatsapp', ''),
                'whatsapp_template'  => $this->settings->get('whatsapp_template', ''),
            ],
            'midtransClientKey' => $gatewayEnabled
                ? $this->settings->get('midtrans_client_key', '')
                : null,
            'isProduction' => $this->settings->get('midtrans_is_production', '0') === '1',
        ]);
    }

    /**
     * POST /checkout/{plan}/order
     * Buat order / transaction record.
     * - Mode manual  → buat transaction pending, redirect ke halaman instruksi transfer
     * - Mode gateway → buat transaction + generate Midtrans Snap token
     */
    public function order(Request $request, string $planSlug): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $plan = Plan::where('slug', $planSlug)->where('status', 'active')->firstOrFail();

        $validated = $request->validate([
            'billing_type' => ['required', 'in:monthly,annual'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
        ]);

        $user         = $request->user();
        $amount       = $validated['billing_type'] === 'annual'
            ? $this->parsePrice($plan->price_annual)
            : $this->parsePrice($plan->price_monthly);
        $orderId      = 'AETHER-' . strtoupper($plan->slug) . '-' . time();
        $gatewayEnabled = $this->settings->get('payment_gateway_enabled', '0') === '1';

        // Buat transaction record
        $transaction = Transaction::create([
            'user_id'      => $user?->id,
            'order_id'     => $orderId,
            'amount'       => (string) $amount,
            'payment_type' => $gatewayEnabled ? 'midtrans' : 'bank_transfer',
            'status'       => Transaction::STATUS_PENDING,
            'snap_response' => [
                'plan_slug'    => $plan->slug,
                'billing_type' => $validated['billing_type'],
                'buyer_name'   => $validated['name'],
                'buyer_email'  => $validated['email'],
            ],
        ]);

        // ── Mode: Transfer Manual ──────────────────────────
        if (!$gatewayEnabled) {
            return redirect()->route('checkout.pending', $transaction->id);
        }

        // ── Mode: Midtrans Gateway ─────────────────────────
        try {
            $snapToken = $this->getMidtransSnapToken(
                orderId:  $orderId,
                amount:   $amount,
                name:     $validated['name'],
                email:    $validated['email'],
                planName: $plan->display_name ?? $plan->name,
            );

            $transaction->update([
                'snap_response' => array_merge(
                    $transaction->snap_response ?? [],
                    ['snap_token' => $snapToken]
                ),
            ]);

            return response()->json([
                'snap_token'     => $snapToken,
                'order_id'       => $orderId,
                'transaction_id' => $transaction->id,
            ]);
        } catch (\Exception $e) {
            \Log::error('[Checkout] Midtrans error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menginisialisasi payment gateway. ' . $e->getMessage()], 500);
        }
    }

    /**
     * GET /checkout/pending/{transaction}
     * Halaman instruksi transfer manual setelah order dibuat.
     */
    public function pending(Request $request, Transaction $transaction): Response
    {
        return Inertia::render('Checkout/Pending', [
            'transaction' => [
                'id'            => $transaction->id,
                'order_id'      => $transaction->order_id,
                'amount'        => 'Rp ' . number_format((float) $transaction->amount, 0, ',', '.'),
                'status'        => $transaction->status,
                'created_at'    => $transaction->created_at?->format('d M Y H:i'),
                'snap_response' => $transaction->snap_response,
            ],
            'bankInfo' => [
                'bank_name'         => $this->settings->get('bank_name', 'BCA'),
                'account'           => $this->settings->get('bank_account_number', ''),
                'holder'            => $this->settings->get('bank_account_holder', ''),
                'whatsapp'          => $this->settings->get('admin_whatsapp', ''),
                'whatsapp_template' => $this->settings->get('whatsapp_template', ''),
            ],
        ]);
    }

    /**
     * POST /checkout/midtrans/notification
     * Webhook dari Midtrans — update status transaksi otomatis.
     */
    public function notification(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? null;

        if (!$orderId) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $transaction = Transaction::where('order_id', $orderId)->first();
        if (!$transaction) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        $transactionStatus = $payload['transaction_status'] ?? '';
        $fraudStatus       = $payload['fraud_status'] ?? 'accept';

        $newStatus = match (true) {
            $transactionStatus === 'capture' && $fraudStatus === 'accept' => Transaction::STATUS_SETTLEMENT,
            $transactionStatus === 'settlement'                            => Transaction::STATUS_SETTLEMENT,
            in_array($transactionStatus, ['cancel', 'deny', 'expire'])    => Transaction::STATUS_CANCEL,
            default                                                        => $transaction->status,
        };

        $transaction->update([
            'status'        => $newStatus,
            'payment_type'  => $payload['payment_type'] ?? $transaction->payment_type,
            'paid_at'       => $newStatus === Transaction::STATUS_SETTLEMENT ? now() : $transaction->paid_at,
            'snap_response' => array_merge($transaction->snap_response ?? [], $payload),
        ]);

        // Jika settlement → aktifkan subscription via event/job (simplified)
        if ($newStatus === Transaction::STATUS_SETTLEMENT && $transaction->user_id) {
            $planSlug = ($transaction->snap_response ?? [])['plan_slug'] ?? null;
            if ($planSlug) {
                $plan = Plan::where('slug', $planSlug)->first();
                $user = \App\Domains\Auth\Models\User::find($transaction->user_id);
                if ($plan && $user) {
                    app(\App\Domains\Billing\Actions\ChangeUserPlanAction::class)->execute(
                        targetUser: $user,
                        newPlan:    $plan,
                        billingType: ($transaction->snap_response ?? [])['billing_type'] ?? 'monthly',
                        reason:     "Midtrans auto-settlement. Order: {$transaction->order_id}",
                    );
                }
            }
        }

        return response()->json(['message' => 'OK']);
    }

    // ─── Helpers ─────────────────────────────────────────
    private function parsePrice(string $price): int
    {
        // "Rp 149.000" → 149000
        return (int) preg_replace('/[^0-9]/', '', $price);
    }

    private function getMidtransSnapToken(string $orderId, int $amount, string $name, string $email, string $planName): string
    {
        $serverKey   = $this->settings->get('midtrans_server_key', '');
        $isProduction = $this->settings->get('midtrans_is_production', '0') === '1';
        $baseUrl     = $isProduction
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';

        $payload = [
            'transaction_details' => ['order_id' => $orderId, 'gross_amount' => $amount],
            'customer_details'    => ['first_name' => $name, 'email' => $email],
            'item_details'        => [['id' => $orderId, 'price' => $amount, 'quantity' => 1, 'name' => $planName]],
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

        $data = json_decode($response, true);
        if ($httpCode !== 201 || empty($data['token'])) {
            throw new \RuntimeException($data['error_messages'][0] ?? 'Midtrans error');
        }

        return $data['token'];
    }
}
