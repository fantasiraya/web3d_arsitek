<script setup lang="ts">
import { Form, Head, usePage, router } from '@inertiajs/vue3';
import { Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { send } from '@/routes/verification';
import { Camera, Trash2, Upload, X } from '@lucide/vue';

const page = usePage();
const user = computed(() => page.props.auth.user);

// ─── Avatar upload state ──────────────────────────────────────────────────────
const fileInput = ref<HTMLInputElement | null>(null);
const previewUrl = ref<string | null>(null);
const isDragging = ref(false);
const uploadError = ref<string | null>(null);
const isUploading = ref(false);

/** Current displayed avatar: preview (if chosen) > saved avatar > null */
const displayAvatar = computed(() => previewUrl.value ?? user.value?.avatar ?? null);

/** User initials fallback */
const initials = computed(() => {
    const name: string = user.value?.name ?? 'U';
    return name.split(' ').map((n: string) => n[0]).slice(0, 2).join('').toUpperCase();
});

function triggerFileInput() {
    fileInput.value?.click();
}

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    handleFile(file);
}

function onDrop(e: DragEvent) {
    isDragging.value = false;
    const file = e.dataTransfer?.files?.[0];
    handleFile(file);
}

function handleFile(file: File | undefined) {
    uploadError.value = null;
    if (!file) return;

    // Validate client-side
    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
        uploadError.value = 'Format tidak didukung. Gunakan JPG, PNG, atau WebP.';
        return;
    }
    if (file.size > 2 * 1024 * 1024) {
        uploadError.value = 'Ukuran file maksimal 2 MB.';
        return;
    }

    // Show preview
    const reader = new FileReader();
    reader.onload = (ev) => { previewUrl.value = ev.target?.result as string; };
    reader.readAsDataURL(file);
}

function cancelPreview() {
    previewUrl.value = null;
    uploadError.value = null;
    if (fileInput.value) fileInput.value.value = '';
}

function submitAvatar() {
    if (!fileInput.value?.files?.[0]) return;
    isUploading.value = true;

    const form = new FormData();
    form.append('avatar', fileInput.value.files[0]);

    router.post('/settings/profile/avatar', form, {
        forceFormData: true,
        onSuccess: () => {
            previewUrl.value = null;
            isUploading.value = false;
        },
        onError: (errs) => {
            uploadError.value = errs.avatar ?? 'Upload gagal, coba lagi.';
            isUploading.value = false;
        },
    });
}

function removeAvatar() {
    router.delete('/settings/profile/avatar', {
        onSuccess: () => { previewUrl.value = null; },
    });
}
</script>

<template>
    <Head title="Pengaturan Profil" />

    <h1 class="sr-only">Profile settings</h1>

    <div class="flex flex-col space-y-8">
        <Heading
            variant="small"
            title="Profil"
            description="Perbarui nama, email, dan foto profil Anda"
        />

        <!-- ── Avatar Section ───────────────────────────────────────────── -->
        <div class="flex flex-col gap-4">
            <Label>Foto Profil</Label>

            <div class="flex items-start gap-6">
                <!-- Avatar circle / preview -->
                <div
                    class="relative shrink-0 h-24 w-24 rounded-full cursor-pointer group"
                    @click="triggerFileInput"
                    @dragover.prevent="isDragging = true"
                    @dragleave="isDragging = false"
                    @drop.prevent="onDrop"
                >
                    <!-- Image or initials fallback -->
                    <div
                        class="h-24 w-24 rounded-full overflow-hidden border-2 transition-all duration-200"
                        :class="isDragging
                            ? 'border-sky-400 ring-2 ring-sky-400/30'
                            : previewUrl
                                ? 'border-sky-500'
                                : 'border-slate-200 dark:border-white/10'"
                    >
                        <img
                            v-if="displayAvatar"
                            :src="displayAvatar"
                            :alt="user?.name"
                            class="h-full w-full object-cover"
                        />
                        <div
                            v-else
                            class="h-full w-full flex items-center justify-center bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-white text-2xl font-bold select-none"
                        >
                            {{ initials }}
                        </div>
                    </div>

                    <!-- Hover overlay -->
                    <div class="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                        <Camera class="h-6 w-6 text-white" />
                    </div>

                    <!-- "Baru" badge kalau ada preview -->
                    <span
                        v-if="previewUrl"
                        class="absolute -top-1 -right-1 rounded-full bg-sky-500 text-white text-[10px] font-bold px-1.5 py-0.5 border-2 border-white dark:border-[#0d0e13]"
                    >
                        Baru
                    </span>
                </div>

                <!-- Controls -->
                <div class="flex flex-col gap-2 pt-1">
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        JPG, PNG, atau WebP · Maks 2 MB<br/>
                        Klik lingkaran atau seret foto ke sana
                    </p>

                    <div class="flex flex-wrap gap-2 mt-1">
                        <!-- Choose / Change -->
                        <button
                            type="button"
                            @click="triggerFileInput"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/10 transition-colors"
                        >
                            <Upload class="h-3.5 w-3.5" />
                            {{ displayAvatar ? 'Ganti Foto' : 'Pilih Foto' }}
                        </button>

                        <!-- Confirm upload (only if preview selected) -->
                        <button
                            v-if="previewUrl"
                            type="button"
                            :disabled="isUploading"
                            @click="submitAvatar"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-sky-500 hover:bg-sky-600 disabled:opacity-60 px-3 py-1.5 text-xs font-semibold text-white transition-colors"
                        >
                            <span v-if="isUploading">Mengupload…</span>
                            <span v-else>Simpan Foto</span>
                        </button>

                        <!-- Cancel preview -->
                        <button
                            v-if="previewUrl"
                            type="button"
                            @click="cancelPreview"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-white/10 px-3 py-1.5 text-xs font-medium text-slate-500 dark:text-slate-400 hover:text-slate-700 dark:hover:text-white transition-colors"
                        >
                            <X class="h-3.5 w-3.5" />
                            Batal
                        </button>

                        <!-- Remove saved avatar -->
                        <button
                            v-if="user?.avatar && !previewUrl"
                            type="button"
                            @click="removeAvatar"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 dark:border-rose-500/30 text-rose-500 dark:text-rose-400 px-3 py-1.5 text-xs font-medium hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Hapus Foto
                        </button>
                    </div>

                    <!-- Error -->
                    <p v-if="uploadError" class="text-xs text-rose-500 mt-1">{{ uploadError }}</p>
                </div>
            </div>

            <!-- Hidden file input -->
            <input
                ref="fileInput"
                type="file"
                accept="image/jpeg,image/png,image/webp"
                class="hidden"
                @change="onFileChange"
            />
        </div>

        <!-- ── Profile Form (name + email) ──────────────────────────────── -->
        <div class="border-t border-slate-100 dark:border-white/5 pt-6">
            <Form
                v-bind="ProfileController.update.form()"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Nama</Label>
                    <Input
                        id="name"
                        class="mt-1 block w-full"
                        name="name"
                        :default-value="user.name"
                        required
                        autocomplete="name"
                        placeholder="Nama lengkap"
                    />
                    <InputError class="mt-2" :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Alamat Email</Label>
                    <Input
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        name="email"
                        :default-value="user.email"
                        required
                        autocomplete="username"
                        placeholder="Alamat email"
                    />
                    <InputError class="mt-2" :message="errors.email" />
                </div>

                <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                    <p class="text-muted-foreground -mt-4 text-sm">
                        Email Anda belum diverifikasi.
                        <Link
                            :href="send()"
                            as="button"
                            class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        >
                            Klik di sini untuk kirim ulang email verifikasi.
                        </Link>
                    </p>

                    <div
                        v-if="page.props.status === 'verification-link-sent'"
                        class="mt-2 text-sm font-medium text-green-600"
                    >
                        Link verifikasi baru telah dikirim ke email Anda.
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <Button :disabled="processing">Simpan Perubahan</Button>
                </div>
            </Form>
        </div>
    </div>

    <DeleteUser />
</template>
