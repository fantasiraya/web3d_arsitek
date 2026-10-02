<?php

namespace App\Http\Controllers;

use App\Domains\Billing\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    /**
     * GET /billing
     * Halaman riwayat pembelian paket milik user yang sedang login.
     */
    public function index(Request $request): Response
    {
        $user    = $request->user();
        $perPage = 10;
        $page    = (int) $request->query('page', 1);

        $rawTx = Transaction::where('user_id', $user->id)
            ->latest()
            ->get()
            ->map(function (Transaction $t) {
                $snap = $t->snap_response ?? [];
                // Plan slug → bisa ditampilkan lebih ramah
                $planSlug = $snap['plan_slug'] ?? null;
                $planLabel = match ($planSlug) {
                    'pro'        => 'Pro',
                    'enterprise' => 'Enterprise',
                    'free'       => 'Free',
                    default      => $planSlug ? ucfirst($planSlug) : '—',
                };

                $billingType = match ($snap['billing_type'] ?? '') {
                    'monthly'  => 'Bulanan',
                    'annual'   => 'Tahunan',
                    'lifetime' => 'Selamanya',
                    default    => $snap['billing_type'] ?? '—',
                };

                $paymentLabel = match ($t->payment_type) {
                    'bank_transfer' => 'Transfer Bank',
                    'midtrans'      => 'Payment Gateway',
                    'manual'        => 'Aktivasi Manual',
                    default         => $t->payment_type ?? '—',
                };

                return [
                    'id'           => $t->id,
                    'order_id'     => $t->order_id,
                    'plan_name'    => $planLabel,
                    'billing_type' => $billingType,
                    'amount'       => 'Rp ' . number_format((float) $t->amount, 0, ',', '.'),
                    'payment_type' => $paymentLabel,
                    'status'       => $t->status,
                    'created_at'   => $t->created_at?->format('d M Y, H:i'),
                    'paid_at'      => $t->paid_at?->format('d M Y, H:i'),
                ];
            });

        $total     = $rawTx->count();
        $lastPage  = (int) max(1, ceil($total / $perPage));
        $page      = max(1, min($page, $lastPage));
        $data      = $rawTx->forPage($page, $perPage)->values();

        return Inertia::render('Billing/Index', [
            'transactions' => [
                'data'         => $data,
                'total'        => $total,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'per_page'     => $perPage,
            ],
        ]);
    }
}
