<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Gateway\DTO\PaymentData;
use App\Domains\Billing\Gateway\PaymentGatewayManager;
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
        protected SystemSettingRepository $settings,
        protected PaymentGatewayManager   $gateway,
    ) {}

    /**
     * GET /checkout/{plan}
     */
    public function show(Request $request, string $planSlug): Response|RedirectResponse
    {
        $plan = Plan::where('slug', $planSlug)->where('status', 'active')->firstOrFail();

        if ($plan->price_monthly === 'Rp 0' || strtolower($plan->price_monthly) === 'rp 0') {
            return redirect()->route('register');
        }

        $gatewayEnabled  = $this->settings->getBool('payment_gateway_enabled');
        $activeProvider  = $this->settings->get('payment_provider_primary', 'midtrans');

        // Data khusus Midtrans (client key untuk Snap JS)
        $midtransClientKey = null;
        if ($gatewayEnabled && $activeProvider === 'midtrans') {
            $midtransClientKey = $this->settings->get('midtrans_client_key', '');
        }

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
            'gatewayEnabled'   => $gatewayEnabled,
            'activeProvider'   => $activeProvider,
            'bankInfo' => [
                'bank_name'         => $this->settings->get('bank_name', 'BCA'),
                'account'           => $this->settings->get('bank_account_number', ''),
                'holder'            => $this->settings->get('bank_account_holder', ''),
                'whatsapp'          => $this->settings->get('admin_whatsapp', ''),
                'whatsapp_template' => $this->settings->get('whatsapp_template', ''),
            ],
            'midtransClientKey' => $midtransClientKey,
            'isProduction'      => $this->settings->getBool('midtrans_is_production'),
        ]);
    }

    /**
     * POST /checkout/{plan}/order
     */
    public function order(Request $request, string $planSlug): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        $plan = Plan::where('slug', $planSlug)->where('status', 'active')->firstOrFail();

        $validated = $request->validate([
            'billing_type' => ['required', 'in:monthly,annual'],
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['required', 'email', 'max:255'],
        ]);

        $user           = $request->user();
        $amount         = $validated['billing_type'] === 'annual'
            ? $this->parsePrice($plan->price_annual)
            : $this->parsePrice($plan->price_monthly);
        $orderId        = 'PITCH-' . strtoupper($plan->slug) . '-' . time();
        $gatewayEnabled = $this->settings->getBool('payment_gateway_enabled');

        // ── Mode: Transfer Manual ──────────────────────────
        if (!$gatewayEnabled) {
            $transaction = Transaction::create([
                'user_id'       => $user?->id,
                'order_id'      => $orderId,
                'amount'        => (string) $amount,
                'payment_type'  => 'bank_transfer',
                'status'        => Transaction::STATUS_PENDING,
                'snap_response' => [
                    'plan_slug'    => $plan->slug,
                    'billing_type' => $validated['billing_type'],
                    'buyer_name'   => $validated['name'],
                    'buyer_email'  => $validated['email'],
                ],
            ]);
            return redirect()->route('checkout.pending', $transaction->id);
        }

        // ── Mode: Payment Gateway (multi-provider) ─────────
        try {
            $paymentData = new PaymentData(
                orderId:       $orderId,
                amount:        $amount,
                customerName:  $validated['name'],
                customerEmail: $validated['email'],
                itemName:      ($plan->display_name ?? $plan->name) . ' - ' . ucfirst($validated['billing_type']),
                planSlug:      $plan->slug,
                billingType:   $validated['billing_type'],
            );

            $result = $this->gateway->createTransaction($paymentData);

            // Simpan transaction dengan provider yang dipakai
            $transaction = Transaction::create([
                'user_id'       => $user?->id,
                'order_id'      => $orderId,
                'amount'        => (string) $amount,
                'payment_type'  => $result->provider,
                'status'        => Transaction::STATUS_PENDING,
                'snap_response' => array_merge([
                    'plan_slug'    => $plan->slug,
                    'billing_type' => $validated['billing_type'],
                    'buyer_name'   => $validated['name'],
                    'buyer_email'  => $validated['email'],
                    'provider'     => $result->provider,
                ], $result->raw),
            ]);

            return response()->json($result->toArray() + ['transaction_id' => $transaction->id]);

        } catch (\Exception $e) {
            \Log::error('[Checkout] Gateway error: ' . $e->getMessage());
            return response()->json(['message' => 'Gagal menginisialisasi payment gateway. ' . $e->getMessage()], 500);
        }
    }

    /**
     * POST /checkout/payment-callback
     * Fallback dari Snap/redirect onSuccess untuk local dev.
     */
    public function paymentCallback(Request $request): \Illuminate\Http\JsonResponse
    {
        $orderId = $request->input('order_id');
        $status  = $request->input('status', 'settlement');

        if (!$orderId) return response()->json(['message' => 'Invalid payload'], 400);

        $transaction = Transaction::where('order_id', $orderId)->first();
        if (!$transaction) return response()->json(['message' => 'Transaction not found'], 404);

        if ($transaction->status === Transaction::STATUS_PENDING) {
            $transaction->update(['status' => Transaction::STATUS_SETTLEMENT, 'paid_at' => now()]);

            if ($transaction->user_id) {
                $snap     = $transaction->snap_response ?? [];
                $planSlug = $snap['plan_slug'] ?? null;
                if ($planSlug) {
                    $plan = Plan::where('slug', $planSlug)->first();
                    $user = \App\Domains\Auth\Models\User::find($transaction->user_id);
                    if ($plan && $user) {
                        app(\App\Domains\Billing\Actions\ChangeUserPlanAction::class)->execute(
                            targetUser:  $user,
                            newPlan:     $plan,
                            billingType: $snap['billing_type'] ?? 'monthly',
                            reason:      "Gateway onSuccess callback. Order: {$transaction->order_id}",
                        );
                    }
                }
            }
        }
        return response()->json(['message' => 'OK']);
    }

    /**
     * GET /checkout/pending/{transaction}
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
     * POST /checkout/midtrans/notification — webhook Midtrans
     */
    public function notification(Request $request): \Illuminate\Http\JsonResponse
    {
        $payload  = $request->all();
        $orderId  = $payload['order_id'] ?? null;

        if (!$orderId) return response()->json(['message' => 'Invalid payload'], 400);

        $transaction = Transaction::where('order_id', $orderId)->first();
        if (!$transaction) return response()->json(['message' => 'Transaction not found'], 404);

        // Gunakan driver yang sesuai dengan provider transaksi ini
        $providerName = $transaction->payment_type ?? 'midtrans';
        $driver       = $this->gateway->driverForWebhook($providerName);

        if (!$driver) {
            return response()->json(['message' => 'Unknown provider'], 400);
        }

        $newStatus = $driver->parseWebhookStatus($payload);

        $transaction->update([
            'status'        => $newStatus,
            'paid_at'       => $newStatus === Transaction::STATUS_SETTLEMENT ? now() : $transaction->paid_at,
            'snap_response' => array_merge($transaction->snap_response ?? [], $payload),
        ]);

        if ($newStatus === Transaction::STATUS_SETTLEMENT && $transaction->user_id) {
            $snap     = $transaction->snap_response ?? [];
            $planSlug = $snap['plan_slug'] ?? null;
            if ($planSlug) {
                $plan = Plan::where('slug', $planSlug)->first();
                $user = \App\Domains\Auth\Models\User::find($transaction->user_id);
                if ($plan && $user) {
                    app(\App\Domains\Billing\Actions\ChangeUserPlanAction::class)->execute(
                        targetUser:  $user,
                        newPlan:     $plan,
                        billingType: $snap['billing_type'] ?? 'monthly',
                        reason:      "Webhook {$providerName}. Order: {$transaction->order_id}",
                    );
                }
            }
        }

        return response()->json(['message' => 'OK']);
    }

    private function parsePrice(string $price): int
    {
        return (int) preg_replace('/[^0-9]/', '', $price);
    }
}
