<script setup lang="ts">
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import { computed, ref, onMounted } from 'vue';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import AddressInput from '@/components/AddressInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { send } from '@/routes/verification';
import { Camera, Trash2, Upload, X } from '@lucide/vue';

const page = usePage();
const user = computed(() => page.props.auth.user as any);

// ── Avatar ────────────────────────────────────────────────────────────────────
const fileInput   = ref<HTMLInputElement | null>(null);
const previewUrl  = ref<string | null>(null);
const isDragging  = ref(false);
const uploadError = ref<string | null>(null);
const isUploading = ref(false);

const displayAvatar = computed(() => previewUrl.value ?? user.value?.avatar ?? null);
const initials      = computed(() => {
    const name: string = user.value?.name ?? 'U';
    return name.split(' ').map((n: string) => n[0]).slice(0, 2).join('').toUpperCase();
});

function triggerFileInput() { fileInput.value?.click(); }

function onFileChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    handleFile(file);
}

function onDrop(e: DragEvent) {
    isDragging.value = false;
    handleFile(e.dataTransfer?.files?.[0]);
}

function handleFile(file: File | undefined) {
    uploadError.value = null;
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
        uploadError.value = 'Format tidak didukung. Gunakan JPG, PNG, atau WebP.';
        return;
    }
    if (file.size > 2 * 1024 * 1024) { uploadError.value = 'Ukuran file maksimal 2 MB.'; return; }
    const reader = new FileReader();
    reader.onload = (ev) => { previewUrl.value = ev.target?.result as string; };
    reader.readAsDataURL(file);
}

function cancelPreview() { previewUrl.value = null; uploadError.value = null; if (fileInput.value) fileInput.value.value = ''; }

function submitAvatar() {
    if (!fileInput.value?.files?.[0]) return;
    isUploading.value = true;
    const fd = new FormData();
    fd.append('avatar', fileInput.value.files[0]);
    router.post('/settings/profile/avatar', fd, {
        forceFormData: true,
        onSuccess: () => { previewUrl.value = null; isUploading.value = false; },
        onError: (errs: any) => { uploadError.value = errs.avatar ?? 'Upload gagal.'; isUploading.value = false; },
    });
}

function removeAvatar() {
    router.delete('/settings/profile/avatar', {
        onSuccess: () => { previewUrl.value = null; },
    });
}

// ── Profile form ──────────────────────────────────────────────────────────────
const COMPANY_TYPES = ['Perorangan', 'PT', 'CV', 'UD', 'Firma', 'Koperasi', 'Yayasan', 'Lainnya'];

const form = useForm({
    name:         user.value?.name         ?? '',
    email:        user.value?.email        ?? '',
    // Badan usaha
    company_type: user.value?.company_type ?? '',
    company_name: user.value?.company_name ?? '',
    phone:        user.value?.phone        ?? '',
    // Wilayah — diisi oleh AddressInput
    province_id:   user.value?.province_id   ?? null as string | null,
    city_id:       user.value?.city_id       ?? null as string | null,
    district_id:   user.value?.district_id   ?? null as string | null,
    village_id:    user.value?.village_id    ?? null as string | null,
    province_name: user.value?.province_name ?? '',
    city_name:     user.value?.city_name     ?? '',
    district_name: user.value?.district_name ?? '',
    village_name:  user.value?.village_name  ?? '',
    address:       user.value?.address       ?? '',
    postal_code:   user.value?.postal_code   ?? '',
});

function onAddressChange(val: any) {
    form.province_id   = val.province_id;
    form.city_id       = val.city_id;
    form.district_id   = val.district_id;
    form.village_id    = val.village_id;
    form.province_name = val.province_name;
    form.city_name     = val.city_name;
    form.district_name = val.district_name;
    form.village_name  = val.village_name;
    form.address       = val.address;
    form.postal_code   = val.postal_code;
}

const addressInitial = computed(() => ({
    province_id:   user.value?.province_id   ?? null,
    city_id:       user.value?.city_id       ?? null,
    district_id:   user.value?.district_id   ?? null,
    village_id:    user.value?.village_id    ?? null,
    province_name: user.value?.province_name ?? '',
    city_name:     user.value?.city_name     ?? '',
    district_name: user.value?.district_name ?? '',
    village_name:  user.value?.village_name  ?? '',
    address:       user.value?.address       ?? '',
    postal_code:   user.value?.postal_code   ?? '',
}));

function submit() {
    form.patch('/settings/profile', {
        onSuccess: () => {},
    });
}
</script>

<template>
    <Head title="Pengaturan Profil" />

    <div class="flex flex-col space-y-8">
        <Heading variant="small" title="Profil"
                 description="Perbarui informasi profil, badan usaha, dan alamat Anda" />

        <!-- ── Avatar ─────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4">
            <Label>Foto Profil</Label>
            <div class="flex items-start gap-6">
                <div class="relative shrink-0 h-24 w-24 rounded-full cursor-pointer group"
                     @click="triggerFileInput"
                     @dragover.prevent="isDragging = true"
                     @dragleave="isDragging = false"
                     @drop.prevent="onDrop">
                    <div class="h-24 w-24 rounded-full overflow-hidden border-2 transition-all duration-200"
                         :class="isDragging ? 'border-sky-400 ring-2 ring-sky-400/30' : previewUrl ? 'border-sky-500' : 'border-slate-200 dark:border-white/10'">
                        <img v-if="displayAvatar" :src="displayAvatar" :alt="user?.name" class="h-full w-full object-cover" />
                        <div v-else class="h-full w-full flex items-center justify-center bg-slate-100 dark:bg-white/10 text-slate-600 dark:text-white text-2xl font-bold select-none">
                            {{ initials }}
                        </div>
                    </div>
                    <div class="absolute inset-0 rounded-full bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center pointer-events-none">
                        <Camera class="h-6 w-6 text-white" />
                    </div>
                    <span v-if="previewUrl" class="absolute -top-1 -right-1 rounded-full bg-sky-500 text-white text-[10px] font-bold px-1.5 py-0.5 border-2 border-white dark:border-[#0d0e13]">Baru</span>
                </div>
                <div class="flex flex-col gap-2 pt-1">
                    <p class="text-xs text-slate-500 dark:text-slate-400">JPG, PNG, atau WebP · Maks 2 MB</p>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <button type="button" @click="triggerFileInput"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-white/10 bg-white dark:bg-white/5 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-white/10 transition-colors">
                            <Upload class="h-3.5 w-3.5" /> {{ displayAvatar ? 'Ganti Foto' : 'Pilih Foto' }}
                        </button>
                        <button v-if="previewUrl" type="button" :disabled="isUploading" @click="submitAvatar"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-sky-500 hover:bg-sky-600 disabled:opacity-60 px-3 py-1.5 text-xs font-semibold text-white transition-colors">
                            {{ isUploading ? 'Mengupload…' : 'Simpan Foto' }}
                        </button>
                        <button v-if="previewUrl" type="button" @click="cancelPreview"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 dark:border-white/10 px-3 py-1.5 text-xs font-medium text-slate-500 hover:text-slate-700 dark:hover:text-white transition-colors">
                            <X class="h-3.5 w-3.5" /> Batal
                        </button>
                        <button v-if="user?.avatar && !previewUrl" type="button" @click="removeAvatar"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 dark:border-rose-500/30 text-rose-500 dark:text-rose-400 px-3 py-1.5 text-xs font-medium hover:bg-rose-50 dark:hover:bg-rose-500/10 transition-colors">
                            <Trash2 class="h-3.5 w-3.5" /> Hapus Foto
                        </button>
                    </div>
                    <p v-if="uploadError" class="text-xs text-rose-500 mt-1">{{ uploadError }}</p>
                </div>
            </div>
            <input ref="fileInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="onFileChange" />
        </div>

        <!-- ── Form Profil ─────────────────────────────────────────────── -->
        <form @submit.prevent="submit" class="space-y-8 border-t border-slate-100 dark:border-white/5 pt-6">

            <!-- Informasi Dasar -->
            <div class="space-y-5">
                <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Informasi Dasar</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <Label for="name">Nama Lengkap <span class="text-rose-500">*</span></Label>
                        <Input id="name" v-model="form.name" required autocomplete="name" placeholder="Nama lengkap" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label for="email">Alamat Email <span class="text-rose-500">*</span></Label>
                        <Input id="email" v-model="form.email" type="email" required placeholder="email@contoh.com" />
                        <InputError :message="form.errors.email" />
                    </div>
                </div>

                <div v-if="page.props.mustVerifyEmail && !user.email_verified_at" class="text-sm text-slate-500">
                    Email Anda belum diverifikasi.
                    <Link :href="send()" as="button" class="underline text-sky-600 hover:text-sky-700">
                        Kirim ulang verifikasi.
                    </Link>
                    <div v-if="page.props.status === 'verification-link-sent'" class="mt-1 text-sm font-medium text-green-600">
                        Link verifikasi telah dikirim.
                    </div>
                </div>

                <div class="space-y-1.5 max-w-xs">
                    <Label for="phone">Nomor Telepon / WhatsApp</Label>
                    <Input id="phone" v-model="form.phone" placeholder="+62 812 3456 7890" />
                    <InputError :message="form.errors.phone" />
                </div>
            </div>

            <!-- Badan Usaha -->
            <div class="space-y-5 border-t border-slate-100 dark:border-white/5 pt-6">
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Badan Usaha</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Digunakan sebagai header pada dokumen RAB yang diekspor.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Tipe badan usaha -->
                    <div class="space-y-1.5">
                        <Label>Tipe Usaha</Label>
                        <div class="flex gap-2 flex-wrap">
                            <button v-for="t in COMPANY_TYPES" :key="t"
                                    type="button"
                                    @click="form.company_type = t"
                                    :class="[
                                        'px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors',
                                        form.company_type === t
                                            ? 'bg-slate-800 dark:bg-white text-white dark:text-slate-900 border-slate-800'
                                            : 'bg-white dark:bg-white/5 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-white/10 hover:border-slate-300',
                                    ]">
                                {{ t }}
                            </button>
                        </div>
                        <InputError :message="form.errors.company_type" />
                    </div>

                    <!-- Nama badan usaha -->
                    <div class="space-y-1.5">
                        <Label for="company_name">
                            Nama
                            <span v-if="form.company_type && form.company_type !== 'Perorangan'"
                                  class="font-mono text-sky-600"> {{ form.company_type }}</span>
                        </Label>
                        <Input id="company_name" v-model="form.company_name"
                               :placeholder="form.company_type === 'PT' ? 'Nama PT Anda' : form.company_type === 'CV' ? 'Nama CV Anda' : 'Nama usaha Anda'" />
                        <InputError :message="form.errors.company_name" />
                    </div>
                </div>

                <!-- Preview kop surat -->
                <div v-if="form.name || form.company_name"
                     class="p-4 rounded-xl bg-slate-50 dark:bg-white/[0.03] border border-slate-200 dark:border-white/10 text-xs text-slate-500">
                    <p class="font-semibold text-slate-700 dark:text-slate-200 text-[11px] uppercase tracking-wider mb-1">Preview kop surat RAB:</p>
                    <p class="font-bold text-sm text-slate-800 dark:text-white">
                        {{ form.company_type && form.company_type !== 'Perorangan' ? form.company_type + ' ' : '' }}{{ form.company_name || form.name }}
                    </p>
                    <p v-if="form.company_name && form.name !== form.company_name" class="text-slate-600 dark:text-slate-300">{{ form.name }}</p>
                    <p v-if="form.phone" class="text-slate-500">Telp: {{ form.phone }}</p>
                    <p v-if="form.address" class="text-slate-500">
                        {{ form.address }}{{ form.village_name ? ', ' + form.village_name : '' }}{{ form.district_name ? ', ' + form.district_name : '' }}
                    </p>
                    <p v-if="form.city_name" class="text-slate-500">
                        {{ form.city_name }}{{ form.province_name ? ', ' + form.province_name : '' }}{{ form.postal_code ? ' ' + form.postal_code : '' }}
                    </p>
                </div>
            </div>

            <!-- Alamat -->
            <div class="space-y-5 border-t border-slate-100 dark:border-white/5 pt-6">
                <div>
                    <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-200">Alamat</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Alamat lengkap kantor / studio arsitektur Anda.</p>
                </div>

                <AddressInput
                    :model-value="addressInitial"
                    @update:model-value="onAddressChange"
                />

                <template v-if="form.errors.address || form.errors.province_id">
                    <InputError :message="form.errors.address || form.errors.province_id" />
                </template>
            </div>

            <!-- Submit -->
            <div class="flex items-center gap-4 border-t border-slate-100 dark:border-white/5 pt-6">
                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Menyimpan…' : 'Simpan Perubahan' }}
                </Button>
                <p v-if="form.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">
                    Profil berhasil disimpan.
                </p>
            </div>
        </form>
    </div>

    <DeleteUser />
</template>
