<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import LandingLayout from '@/layouts/LandingLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({ layout: LandingLayout });

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Lupa Sandi — PitchArch" />

    <!-- Split screen -->
    <div class="flex min-h-[calc(100vh-80px)] flex-col lg:flex-row">

        <!-- ═══════════════════════════════
             KIRI — Foto arsitektur (52%)
        ══════════════════════════════════ -->
        <div class="relative hidden lg:block lg:w-[52%] overflow-hidden">
            <img
                src="/images/forgot-password.jpg"
                alt="Minimalist architectural space with precision spatial design"
                class="absolute inset-0 h-full w-full object-cover object-center scale-[1.02]"
            />
            <div class="absolute inset-0 bg-gradient-to-t from-[#050608]/90 via-[#050608]/30 to-[#050608]/60"></div>
            <div class="absolute inset-0 bg-gradient-to-r from-transparent to-[#050608]/40"></div>
            <div
                class="absolute inset-0 opacity-[0.05]"
                style="background-image: linear-gradient(rgba(99,102,241,0.5) 1px, transparent 1px), linear-gradient(90deg, rgba(99,102,241,0.5) 1px, transparent 1px); background-size: 64px 64px;"
            ></div>
        </div>

        <!-- ═══════════════════════════════
             KANAN — Form (48%)
        ══════════════════════════════════ -->
        <div class="relative flex w-full flex-col justify-center px-6 py-12 sm:px-12 lg:w-[48%] lg:px-14 xl:px-16">

            <div class="pointer-events-none absolute -top-20 right-0 h-[400px] w-[400px] rounded-full bg-indigo-600/8 blur-[120px]"></div>
            <div class="pointer-events-none absolute bottom-0 left-0 h-[360px] w-[360px] rounded-full bg-purple-600/5 blur-[120px]"></div>

            <div class="relative z-10 mx-auto w-full max-w-[420px]">
                <div class="relative overflow-hidden rounded-2xl border border-white/8 bg-white/[0.03] backdrop-blur-xl p-7 sm:p-8 shadow-2xl shadow-black/60">

                    <!-- Specular top bar -->
                    <div class="absolute top-0 inset-x-6 h-px bg-gradient-to-r from-transparent via-indigo-500/30 to-transparent"></div>

                    <!-- Header -->
                    <div class="flex flex-col items-center text-center mb-6">
                        <!-- Icon -->
                        <div class="relative mb-3.5 flex h-11 w-11 items-center justify-center rounded-xl border border-white/10 bg-white/5">
                            <div class="absolute inset-0 rounded-xl bg-amber-500/15 blur-sm"></div>
                            <svg class="relative z-10 h-5 w-5 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                        </div>
                        <h1 class="text-lg font-semibold text-white tracking-tight mb-1.5">Reset Kata Sandi</h1>
                        <p class="text-sm text-neutral-400 max-w-xs leading-relaxed">
                            Masukkan email Anda dan kami akan mengirimkan tautan untuk mereset kata sandi akun studio Anda
                        </p>
                    </div>

                    <!-- Success status -->
                    <div
                        v-if="status"
                        class="mb-5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300 text-center leading-relaxed"
                    >
                        {{ status }}
                    </div>

                    <!-- Form -->
                    <Form v-bind="email.form()" v-slot="{ errors, processing }">
                        <div class="space-y-4">
                            <!-- Email -->
                            <div class="space-y-1.5">
                                <label for="email" class="block text-[11px] font-semibold text-neutral-400 uppercase tracking-wider">
                                    Email Studio / Enterprise ID
                                </label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    autocomplete="off"
                                    autofocus
                                    placeholder="nama@studio-pitcharch.id"
                                    class="h-11 rounded-xl border-white/10 bg-white/5 text-white placeholder:text-neutral-600 focus:border-indigo-500/50 focus:ring-indigo-500/20 text-sm [color-scheme:dark] selection:bg-indigo-500 selection:text-white"
                                />
                                <InputError :message="errors.email" />
                            </div>

                            <!-- Submit -->
                            <div class="pt-1">
                                <button
                                    type="submit"
                                    :disabled="processing"
                                    data-test="email-password-reset-link-button"
                                    class="w-full h-11 rounded-xl bg-white text-[#050608] font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] hover:bg-neutral-100 shadow-md shadow-white/5 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <Spinner v-if="processing" class="h-4 w-4 text-[#050608]" />
                                    <span>{{ processing ? 'Mengirim...' : 'Kirim Tautan Reset' }}</span>
                                    <svg v-if="!processing" class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                        <path d="M22 2L11 13"/><path d="M22 2L15 22l-4-9-9-4 20-7z"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Back to login -->
                        <p class="mt-5 text-center text-xs text-neutral-500">
                            Ingat kata sandi Anda?
                            <Link
                                :href="login()"
                                class="text-white font-medium underline underline-offset-4 decoration-white/20 hover:decoration-white ml-1 transition-all"
                            >
                                Kembali ke Login
                            </Link>
                        </p>
                    </Form>

                    <!-- Security note -->
                    <div class="mt-6 pt-5 border-t border-white/8">
                        <div class="flex items-start gap-3 rounded-xl border border-amber-500/15 bg-amber-500/5 px-4 py-3">
                            <svg class="h-4 w-4 text-amber-400/70 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <p class="text-[11px] text-neutral-500 leading-relaxed">
                                Tautan reset akan dikirim ke email Anda dan berlaku selama <span class="text-neutral-300 font-medium">60 menit</span>. Periksa folder spam jika tidak muncul di inbox.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
