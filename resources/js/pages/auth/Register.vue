<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import LandingLayout from '@/layouts/LandingLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

// Pakai LandingLayout — navbar + footer + ambient glow otomatis
defineOptions({ layout: LandingLayout });

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Daftar — AETHER 3D" />

    <!-- Split screen container -->
    <div class="flex min-h-[calc(100vh-80px)] flex-col lg:flex-row">

        <!-- ═══════════════════════════════
             KIRI — Foto arsitektur (52%)
        ══════════════════════════════════ -->
        <div class="relative hidden lg:block lg:w-[52%] overflow-hidden">
            <img
                src="/images/register-villa.jpg"
                alt="Modern architectural interior with spatial precision"
                class="absolute inset-0 h-full w-full object-cover object-center scale-[1.02]"
            />
            <!-- Gradient overlay -->
            <div class="absolute inset-0 bg-gradient-to-t from-[#050608]/90 via-[#050608]/30 to-[#050608]/60"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-transparent to-[#050608]/40"></div>
            <!-- CAD grid overlay -->
            <div
                class="absolute inset-0 opacity-[0.05]"
                style="background-image: linear-gradient(rgba(99,102,241,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.5) 1px, transparent 1px); background-size: 64px 64px;"
            ></div>
        </div>

        <!-- ═══════════════════════════════
             KANAN — Form register (48%)
        ══════════════════════════════════ -->
        <div class="relative flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-[48%] lg:px-14 xl:px-16">

            <!-- Ambient glow -->
            <div class="pointer-events-none absolute -top-20 right-0 h-[400px] w-[400px] rounded-full bg-indigo-600/8 blur-[120px]"></div>
            <div class="pointer-events-none absolute bottom-0 left-0 h-[360px] w-[360px] rounded-full bg-purple-600/5 blur-[120px]"></div>

            <div class="relative z-10 mx-auto w-full max-w-[420px]">

                <!-- Card -->
                <div class="relative overflow-hidden rounded-2xl border border-white/8 bg-white/[0.03] backdrop-blur-xl p-7 sm:p-8 shadow-2xl shadow-black/60">

                    <!-- Specular top bar -->
                    <div class="absolute top-0 inset-x-6 h-px bg-gradient-to-r from-transparent via-indigo-500/30 to-transparent"></div>

                    <!-- Header -->
                    <div class="flex flex-col items-center text-center mb-6">
                        <div class="relative mb-3.5 flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5">
                            <div class="absolute inset-0 rounded-xl bg-indigo-500/15 blur-sm"></div>
                            <svg class="relative z-10 h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <path d="m21 7.5-9-5.25L3 7.5m18 0-9 5.25m9-5.25v9l-9 5.25m-9-14.25 9 5.25m-9-5.25v9l9 5.25m0-9v9"/>
                            </svg>
                        </div>
                        <h1 class="text-lg font-semibold text-white tracking-tight mb-1.5">Buat Akun Studio</h1>
                        <p class="text-sm text-neutral-400 max-w-xs leading-relaxed">
                            Daftarkan akun arsitek Anda untuk mulai berkolaborasi pada proyek 3D
                        </p>
                    </div>

                    <!-- Google OAuth -->
                    <div class="mb-4">
                        <a
                            href="/auth/google"
                            class="w-full h-11 px-4 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 hover:border-white/20 text-white text-sm font-medium flex items-center justify-center gap-2.5 transition-all duration-150 active:scale-[0.99]"
                        >
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24">
                                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                            </svg>
                            <span>Daftar dengan Google</span>
                        </a>
                    </div>

                    <!-- Divider -->
                    <div class="relative flex items-center justify-center mb-4">
                        <div class="w-full border-t border-white/8"></div>
                        <span class="absolute bg-[#050608] px-3 text-[10px] font-mono text-neutral-600 tracking-wider uppercase whitespace-nowrap">
                            atau daftar dengan email
                        </span>
                    </div>

                    <!-- Form -->
                    <Form
                        v-bind="store.form()"
                        :reset-on-success="['password', 'password_confirmation']"
                        v-slot="{ errors, processing }"
                        class="space-y-4"
                    >
                        <!-- Name -->
                        <div class="space-y-1.5">
                            <label for="name" class="block text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">
                                Nama Lengkap
                            </label>
                            <Input
                                id="name"
                                type="text"
                                name="name"
                                required
                                autofocus
                                :tabindex="1"
                                autocomplete="name"
                                placeholder="Nama lengkap Anda"
                                class="h-11 rounded-xl border-white/10 bg-white/5 text-white placeholder:text-neutral-600 focus:border-indigo-500/50 focus:ring-indigo-500/20 text-sm"
                            />
                            <InputError :message="errors.name" />
                        </div>

                        <!-- Email -->
                        <div class="space-y-1.5">
                            <label for="email" class="block text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">
                                Email
                            </label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                :tabindex="2"
                                autocomplete="email"
                                placeholder="nama@studio-aether.id"
                                class="h-11 rounded-xl border-white/10 bg-white/5 text-white placeholder:text-neutral-600 focus:border-indigo-500/50 focus:ring-indigo-500/20 text-sm"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <!-- Password -->
                        <div class="space-y-1.5">
                            <label for="password" class="block text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">
                                Kata Sandi
                            </label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                :tabindex="3"
                                autocomplete="new-password"
                                placeholder="Buat kata sandi kuat"
                                :passwordrules="passwordRules"
                                class="h-11 rounded-xl border-white/10 bg-white/5 text-white placeholder:text-neutral-600 focus:border-indigo-500/50 focus:ring-indigo-500/20 text-sm"
                            />
                            <InputError :message="errors.password" />
                        </div>

                        <!-- Confirm Password -->
                        <div class="space-y-1.5">
                            <label for="password_confirmation" class="block text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">
                                Konfirmasi Kata Sandi
                            </label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                :tabindex="4"
                                autocomplete="new-password"
                                placeholder="Ulangi kata sandi"
                                :passwordrules="passwordRules"
                                class="h-11 rounded-xl border-white/10 bg-white/5 text-white placeholder:text-neutral-600 focus:border-indigo-500/50 focus:ring-indigo-500/20 text-sm"
                            />
                            <InputError :message="errors.password_confirmation" />
                        </div>

                        <!-- Submit -->
                        <div class="pt-1">
                            <button
                                type="submit"
                                :tabindex="5"
                                :disabled="processing"
                                data-test="register-user-button"
                                class="w-full h-11 rounded-xl bg-white text-[#050608] font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] hover:bg-neutral-100 shadow-md shadow-white/5 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <Spinner v-if="processing" class="h-4 w-4 text-[#050608]" />
                                <span>{{ processing ? 'Membuat akun...' : 'Buat Akun Studio' }}</span>
                                <svg v-if="!processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M5 12h14M12 5l7 7-7 7"/>
                                </svg>
                            </button>
                        </div>

                        <!-- Login link -->
                        <p class="text-center text-xs text-neutral-500 pt-1">
                            Sudah punya akun?
                            <Link
                                :href="login()"
                                :tabindex="6"
                                class="text-white font-medium underline underline-offset-4 decoration-white/20 hover:decoration-white ml-1 transition-all"
                            >
                                Masuk di sini
                            </Link>
                        </p>
                    </Form>
                </div>
            </div>
        </div>

    </div>
</template>
