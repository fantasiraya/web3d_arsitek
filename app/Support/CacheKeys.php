<?php

namespace App\Support;

/**
 * Sentralisasi semua cache key, tag, dan TTL di satu tempat.
 *
 * Aturan penggunaan:
 *  - Key     : string unik yang mengidentifikasi satu nilai cache.
 *  - Tag     : label grup; memungkinkan flush banyak key sekaligus.
 *  - TTL     : konstanta integer (detik) — ubah di sini, berlaku di mana-mana.
 *
 * Kapan pakai tags vs tidak:
 *  - Pakai tags  → jika ada kebutuhan invalidasi grup (user dihapus, role berubah,
 *                  setting diupdate, dll.).
 *  - Tidak perlu → TTL sangat pendek (< 60 detik) atau nilai tidak pernah diubah
 *                  secara eksplisit sebelum expired.
 */
final class CacheKeys
{
    // ── TTL constants (detik) ─────────────────────────────────────────────────

    /** 5 menit — cocok untuk data semi-statis per user (role, plan) */
    public const TTL_SHORT = 300;

    /** 1 jam — cocok untuk data yang jarang berubah (system settings) */
    public const TTL_MEDIUM = 3600;

    /** Permanen — dihapus manual via clearCache(), bukan expired */
    public const TTL_FOREVER = 0;

    // ── Tags ──────────────────────────────────────────────────────────────────

    /**
     * Tag untuk semua cache milik satu user.
     * Flush seluruh tag ini saat user dihapus, suspend, atau role berubah.
     *
     * Usage: Cache::tags([CacheKeys::tagUser($id)])->...
     */
    public static function tagUser(string $userId): string
    {
        return "user:{$userId}";
    }

    /**
     * Tag khusus untuk data role/permission user.
     * Flush saat role di-assign atau di-revoke.
     *
     * Usage: Cache::tags([CacheKeys::tagUserRoles($id)])->...
     */
    public static function tagUserRoles(string $userId): string
    {
        return "user:{$userId}:roles";
    }

    /**
     * Tag global untuk semua system settings.
     * Flush saat admin mengubah setting apapun.
     *
     * Usage: Cache::tags([CacheKeys::TAG_SETTINGS])->...
     */
    public const TAG_SETTINGS = 'system_settings';

    // ── Keys ──────────────────────────────────────────────────────────────────

    /**
     * Apakah user adalah super_admin.
     * Tags: tagUser($id), tagUserRoles($id)
     */
    public static function userIsAdmin(string $userId): string
    {
        return "user:{$userId}:is_super_admin";
    }

    /**
     * Throttle flag: sudah update last_active_at dalam window ini.
     * Tags: tagUser($id)
     */
    public static function userLastActiveWritten(string $userId): string
    {
        return "user:{$userId}:last_active_written";
    }

    /**
     * Semua system settings sebagai array.
     * Tags: TAG_SETTINGS
     */
    public const KEY_SETTINGS_ALL = 'system_settings_all';

    /**
     * Tag untuk cache paket/plans.
     */
    public const TAG_PLANS = 'plans';

    /**
     * Key untuk daftar active plans (Welcome & Plans page).
     * Tags: TAG_PLANS
     */
    public const KEY_PLANS_ACTIVE = 'plans:active_list';

    // ── Safe Tagging & Flush helpers ──────────────────────────────────────────

    /**
     * Dapatkan cache store dengan tags jika didukung driver (Redis/Memcached).
     * Jika driver tidak mendukung tags (misal file/database saat fallback),
     * otomatis fallback ke cache store default tanpa crash (BadMethodCallException).
     */
    public static function tags(array $tags)
    {
        if (\Illuminate\Support\Facades\Cache::supportsTags()) {
            return \Illuminate\Support\Facades\Cache::tags($tags);
        }

        return \Illuminate\Support\Facades\Cache::store();
    }

    /**
     * Flush semua cache milik satu user (role, throttle, dll.).
     * Dipanggil saat: user dihapus, akun di-suspend, role berubah.
     */
    public static function flushUser(string $userId): void
    {
        if (\Illuminate\Support\Facades\Cache::supportsTags()) {
            \Illuminate\Support\Facades\Cache::tags([
                self::tagUser($userId),
            ])->flush();
        }
    }

    /**
     * Flush hanya data role/permission user.
     * Dipanggil saat: role di-assign atau di-revoke via Admin Panel.
     */
    public static function flushUserRoles(string $userId): void
    {
        if (\Illuminate\Support\Facades\Cache::supportsTags()) {
            \Illuminate\Support\Facades\Cache::tags([
                self::tagUserRoles($userId),
            ])->flush();
        }
    }

    /**
     * Flush semua system settings.
     * Dipanggil saat: admin mengubah nilai setting apapun.
     */
    public static function flushSettings(): void
    {
        if (\Illuminate\Support\Facades\Cache::supportsTags()) {
            \Illuminate\Support\Facades\Cache::tags([
                self::TAG_SETTINGS,
            ])->flush();
        } else {
            \Illuminate\Support\Facades\Cache::forget(self::KEY_SETTINGS_ALL);
        }
    }

    /**
     * Flush semua cache daftar paket (plans).
     * Dipanggil saat: admin mengupdate paket, harga, atau benefit.
     */
    public static function flushPlans(): void
    {
        if (\Illuminate\Support\Facades\Cache::supportsTags()) {
            \Illuminate\Support\Facades\Cache::tags([
                self::TAG_PLANS,
            ])->flush();
        } else {
            \Illuminate\Support\Facades\Cache::forget(self::KEY_PLANS_ACTIVE);
        }
    }
}
