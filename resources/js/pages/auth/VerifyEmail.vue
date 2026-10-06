<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Spinner } from '@/components/ui/spinner';
import LandingLayout from '@/layouts/LandingLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({ layout: LandingLayout });

defineProps<{
    status?: string;
}>();

const page = usePage();
const userEmail = (page.props.auth as any)?.user?.email ?? '';
</script>

<template>
    <Head title="Verifikasi Email — PitchArch" />

    <div class="flex min-h-[calc(100vh-80px)] items-center justify-center px-6 py-16">

        <!-- Ambient glow -->
        <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden">
            <div class="absolute top-1/4 left-1/2 -translate-x-1/2 h-[500px] w-[700px] rounded-full bg-indigo-600/8 blur-[140px]"></div>
            <div class="absolute bottom-0 right-0 h-[400px] w-[400px] rounded-full bg-purple-600/5 blur-[120px]"></div>
        </div>

        <div class="relative z-10 w-full max-w-[440px]">

            <!-- Card -->
            <div class="relative overflow-hidden rounded-2xl border border-white/8 bg-white/[0.03] backdrop-blur-xl p-8 shadow-2xl shadow-black/60">

                <!-- Specular top bar -->
                <div class="absolute top-0 inset-x-6 h-px bg-gradient-to-r from-transparent via-indigo-500/30 to-transparent"></div>

                <!-- Icon -->
                <div class="flex flex-col items-center text-center mb-6">
                    <div class="relative mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-white/10 bg-white/5">
                        <div class="absolute inset-0 rounded-2xl bg-indigo-500/20 blur-md"></div>
                        <!-- Envelope icon -->
                        <svg class="relative z-10 h-6 w-6 text-indigo-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="20" height="16" x="2" y="4" rx="2"/>
                            <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                        </svg>
                    </div>

                    <h1 class="text-xl font-semibold text-white tracking-tight mb-2">
                        Verifikasi Email Anda
                    </h1>
                    <p class="text-sm text-neutral-400 leading-relaxed max-w-xs">
                        Kami telah mengirimkan tautan verifikasi ke
                        <span v-if="userEmail" class="text-indigo-400 font-medium break-all">{{ userEmail }}</span>
                        <span v-else>alamat email Anda</span>.
                        Klik tautan tersebut untuk mengaktifkan akun.
                    </p>
                </div>

                <!-- Success status -->
                <div
                    v-if="status === 'verification-link-sent'"
                    class="mb-5 flex items-start gap-3 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3"
                >
                    <svg class="h-4 w-4 text-emerald-400 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>
                    <p class="text-sm text-emerald-300 leading-relaxed">
                        Tautan verifikasi baru telah dikirim ke email Anda. Periksa juga folder spam.
                    </p>
                </div>

                <!-- Resend form -->
                <Form
                    v-bind="send.form()"
                    class="space-y-3"
                    v-slot="{ processing }"
                >
                    <button
                        type="submit"
                        :disabled="processing"
                        class="w-full h-11 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm flex items-center justify-center gap-2 transition-all duration-150 active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed shadow-lg shadow-indigo-500/20"
                    >
                        <Spinner v-if="processing" class="h-4 w-4" />
                        <svg v-else class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span>{{ processing ? 'Mengirim...' : 'Kirim Ulang Email Verifikasi' }}</span>
                    </button>

                    <Link
                        :href="logout()"
                        method="post"
                        as="button"
                        class="w-full h-11 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 text-neutral-400 hover:text-white font-medium text-sm flex items-center justify-center gap-2 transition-all duration-150"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        Keluar
                    </Link>
                </Form>

                <!-- Tips -->
                <div class="mt-6 pt-5 border-t border-white/8">
                    <p class="text-[11px] font-semibold text-neutral-500 uppercase tracking-wider mb-3">Tips jika email tidak masuk</p>
                    <ul class="space-y-1.5">
                        <li class="flex items-start gap-2 text-[12px] text-neutral-500">
                            <span class="text-indigo-400 mt-0.5 shrink-0">•</span>
                            Periksa folder <span class="text-neutral-300">Spam</span> atau <span class="text-neutral-300">Junk</span>
                        </li>
                        <li class="flex items-start gap-2 text-[12px] text-neutral-500">
                            <span class="text-indigo-400 mt-0.5 shrink-0">•</span>
                            Pastikan alamat email yang didaftarkan sudah benar
                        </li>
                        <li class="flex items-start gap-2 text-[12px] text-neutral-500">
                            <span class="text-indigo-400 mt-0.5 shrink-0">•</span>
                            Tautan verifikasi berlaku selama <span class="text-neutral-300">60 menit</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</template>
