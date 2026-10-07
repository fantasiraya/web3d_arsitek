<script setup lang="ts">
/**
 * AddressInput.vue — Input alamat lengkap Indonesia
 * Urutan: Provinsi → Kab/Kota → Kecamatan → Kelurahan/Desa → Alamat → Kode Pos
 * Data dari /region/* (laravolt/indonesia, publik, cached 24h)
 */
import { ref, watch, onMounted } from 'vue';
import { useIndonesiaRegion, type RegionValue } from '@/composables/useIndonesiaRegion';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Loader2 } from '@lucide/vue';

export interface AddressValue extends RegionValue {
    address:     string;
    postal_code: string;
}

const props = defineProps<{
    modelValue?: Partial<AddressValue> | null;
    disabled?:   boolean;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', v: AddressValue): void;
}>();

// Init composable dengan nilai awal dari props
const {
    provinces, cities, districts, villages,
    loadingProvinces, loadingCities, loadingDistricts, loadingVillages,
    selectedProvince, selectedCity, selectedDistrict, selectedVillage,
    provinceName, cityName, districtName, villageName,
    getValue,
} = useIndonesiaRegion({
    province_id:   props.modelValue?.province_id   ?? null,
    city_id:       props.modelValue?.city_id       ?? null,
    district_id:   props.modelValue?.district_id   ?? null,
    village_id:    props.modelValue?.village_id    ?? null,
    province_name: props.modelValue?.province_name ?? '',
    city_name:     props.modelValue?.city_name     ?? '',
    district_name: props.modelValue?.district_name ?? '',
    village_name:  props.modelValue?.village_name  ?? '',
});

const address    = ref(props.modelValue?.address    ?? '');
const postalCode = ref(props.modelValue?.postal_code ?? '');

function emitChange() {
    emit('update:modelValue', {
        ...getValue(),
        address:     address.value,
        postal_code: postalCode.value,
    });
}

// Emit tiap ada perubahan wilayah
watch([selectedProvince, selectedCity, selectedDistrict, selectedVillage], emitChange);
watch([address, postalCode], emitChange);

const selectClass = 'w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50 disabled:cursor-not-allowed';
</script>

<template>
    <div class="space-y-4">
        <!-- Provinsi + Kab/Kota -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <Label>Provinsi</Label>
                <div class="relative">
                    <select v-model="selectedProvince" :disabled="disabled || loadingProvinces" :class="selectClass">
                        <option :value="null">{{ loadingProvinces ? 'Memuat…' : '— Pilih Provinsi —' }}</option>
                        <option v-for="p in provinces" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                    <Loader2 v-if="loadingProvinces" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-slate-400" />
                </div>
            </div>

            <div class="space-y-1.5">
                <Label>Kabupaten / Kota</Label>
                <div class="relative">
                    <select v-model="selectedCity" :disabled="disabled || !selectedProvince || loadingCities" :class="selectClass">
                        <option :value="null">
                            {{ !selectedProvince ? '— Pilih Provinsi dulu —' : loadingCities ? 'Memuat…' : '— Pilih Kab/Kota —' }}
                        </option>
                        <option v-for="c in cities" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                    <Loader2 v-if="loadingCities" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-slate-400" />
                </div>
            </div>
        </div>

        <!-- Kecamatan + Kelurahan -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
                <Label>Kecamatan</Label>
                <div class="relative">
                    <select v-model="selectedDistrict" :disabled="disabled || !selectedCity || loadingDistricts" :class="selectClass">
                        <option :value="null">
                            {{ !selectedCity ? '— Pilih Kab/Kota dulu —' : loadingDistricts ? 'Memuat…' : '— Pilih Kecamatan —' }}
                        </option>
                        <option v-for="d in districts" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <Loader2 v-if="loadingDistricts" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-slate-400" />
                </div>
            </div>

            <div class="space-y-1.5">
                <Label>Kelurahan / Desa</Label>
                <div class="relative">
                    <select v-model="selectedVillage" :disabled="disabled || !selectedDistrict || loadingVillages" :class="selectClass">
                        <option :value="null">
                            {{ !selectedDistrict ? '— Pilih Kecamatan dulu —' : loadingVillages ? 'Memuat…' : '— Pilih Kelurahan/Desa —' }}
                        </option>
                        <option v-for="v in villages" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                    <Loader2 v-if="loadingVillages" class="absolute right-3 top-1/2 -translate-y-1/2 h-4 w-4 animate-spin text-slate-400" />
                </div>
            </div>
        </div>

        <!-- Alamat detail -->
        <div class="space-y-1.5">
            <Label>Alamat Lengkap</Label>
            <textarea v-model="address" :disabled="disabled" rows="2"
                      placeholder="Jl. Contoh No.1, RT 01/RW 02…"
                      class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-ring resize-none disabled:opacity-50" />
        </div>

        <!-- Kode Pos -->
        <div class="space-y-1.5 max-w-36">
            <Label>Kode Pos</Label>
            <Input v-model="postalCode" :disabled="disabled" placeholder="12345" maxlength="10" />
        </div>
    </div>
</template>
