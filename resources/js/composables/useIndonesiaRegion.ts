/**
 * useIndonesiaRegion.ts
 *
 * Composable untuk dropdown wilayah Indonesia yang chained.
 * Menggunakan laravolt/indonesia v4 — foreign key via `code` (string), bukan integer id.
 *
 * Provinsi → Kabupaten/Kota → Kecamatan → Kelurahan/Desa
 */

import { ref, watch, readonly } from 'vue';

export interface RegionOption {
    id:   string; // = code dari laravolt (contoh: "11", "1101", "1101010")
    name: string;
}

export interface RegionValue {
    province_id:   string | null;
    city_id:       string | null;
    district_id:   string | null;
    village_id:    string | null;
    province_name: string;
    city_name:     string;
    district_name: string;
    village_name:  string;
}

export function useIndonesiaRegion(initial?: Partial<RegionValue>) {
    const provinces = ref<RegionOption[]>([]);
    const cities    = ref<RegionOption[]>([]);
    const districts = ref<RegionOption[]>([]);
    const villages  = ref<RegionOption[]>([]);

    const loadingProvinces = ref(false);
    const loadingCities    = ref(false);
    const loadingDistricts = ref(false);
    const loadingVillages  = ref(false);

    const selectedProvince = ref<string | null>(initial?.province_id ?? null);
    const selectedCity     = ref<string | null>(initial?.city_id ?? null);
    const selectedDistrict = ref<string | null>(initial?.district_id ?? null);
    const selectedVillage  = ref<string | null>(initial?.village_id ?? null);

    const provinceName  = ref(initial?.province_name ?? '');
    const cityName      = ref(initial?.city_name ?? '');
    const districtName  = ref(initial?.district_name ?? '');
    const villageName   = ref(initial?.village_name ?? '');

    async function fetchJson(url: string): Promise<RegionOption[]> {
        try {
            // Pakai origin dinamis agar bekerja di port manapun (8000, 8001, dll.)
            const base = window.location.origin;
            const res = await fetch(`${base}${url}`, {
                credentials: 'include',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok) return [];
            return await res.json();
        } catch {
            return [];
        }
    }

    async function loadProvinces() {
        loadingProvinces.value = true;
        provinces.value = await fetchJson('/region/provinces');
        loadingProvinces.value = false;
        // Jika ada nilai awal, load rantai tanpa reset children
        if (selectedProvince.value) await loadCities(selectedProvince.value, false);
    }

    async function loadCities(code: string, resetChildren = true) {
        if (resetChildren) {
            selectedCity.value = null; selectedDistrict.value = null; selectedVillage.value = null;
            cityName.value = ''; districtName.value = ''; villageName.value = '';
            districts.value = []; villages.value = [];
        }
        loadingCities.value = true;
        cities.value = await fetchJson(`/region/cities?province_id=${code}`);
        loadingCities.value = false;
        // Setelah load, pilih ulang city yang tersimpan dan load rantai selanjutnya
        if (selectedCity.value && !resetChildren) await loadDistricts(selectedCity.value, false);
    }

    async function loadDistricts(code: string, resetChildren = true) {
        if (resetChildren) {
            selectedDistrict.value = null; selectedVillage.value = null;
            districtName.value = ''; villageName.value = '';
            villages.value = [];
        }
        loadingDistricts.value = true;
        districts.value = await fetchJson(`/region/districts?city_id=${code}`);
        loadingDistricts.value = false;
        if (selectedDistrict.value && !resetChildren) await loadVillages(selectedDistrict.value);
    }

    async function loadVillages(code: string) {
        loadingVillages.value = true;
        villages.value = await fetchJson(`/region/villages?district_id=${code}`);
        loadingVillages.value = false;
    }

    watch(selectedProvince, async (id) => {
        const found = provinces.value.find(p => p.id === id);
        provinceName.value = found?.name ?? '';
        if (id) await loadCities(id);
        else { cities.value = []; districts.value = []; villages.value = []; }
    });

    watch(selectedCity, async (id) => {
        const found = cities.value.find(c => c.id === id);
        cityName.value = found?.name ?? '';
        if (id) await loadDistricts(id);
        else { districts.value = []; villages.value = []; }
    });

    watch(selectedDistrict, async (id) => {
        const found = districts.value.find(d => d.id === id);
        districtName.value = found?.name ?? '';
        if (id) await loadVillages(id);
        else villages.value = [];
    });

    watch(selectedVillage, (id) => {
        const found = villages.value.find(v => v.id === id);
        villageName.value = found?.name ?? '';
    });

    // Setelah list di-load, sinkronkan nama jika ID sudah terpilih
    watch(provinces,  (list) => { if (selectedProvince.value)  provinceName.value  = list.find(p => p.id === selectedProvince.value)?.name  ?? provinceName.value;  });
    watch(cities,     (list) => { if (selectedCity.value)      cityName.value      = list.find(p => p.id === selectedCity.value)?.name      ?? cityName.value;      });
    watch(districts,  (list) => { if (selectedDistrict.value)  districtName.value  = list.find(p => p.id === selectedDistrict.value)?.name  ?? districtName.value;  });
    watch(villages,   (list) => { if (selectedVillage.value)   villageName.value   = list.find(p => p.id === selectedVillage.value)?.name   ?? villageName.value;   });

    function getValue(): RegionValue {
        return {
            province_id:   selectedProvince.value,
            city_id:       selectedCity.value,
            district_id:   selectedDistrict.value,
            village_id:    selectedVillage.value,
            province_name: provinceName.value,
            city_name:     cityName.value,
            district_name: districtName.value,
            village_name:  villageName.value,
        };
    }

    function setInitialValue(v: Partial<RegionValue>) {
        selectedProvince.value = v.province_id  ? String(v.province_id)  : null;
        selectedCity.value     = v.city_id      ? String(v.city_id)      : null;
        selectedDistrict.value = v.district_id  ? String(v.district_id)  : null;
        selectedVillage.value  = v.village_id   ? String(v.village_id)   : null;
        provinceName.value     = v.province_name ?? '';
        cityName.value         = v.city_name     ?? '';
        districtName.value     = v.district_name ?? '';
        villageName.value      = v.village_name  ?? '';
    }

    loadProvinces();

    return {
        provinces: readonly(provinces),
        cities:    readonly(cities),
        districts: readonly(districts),
        villages:  readonly(villages),
        loadingProvinces: readonly(loadingProvinces),
        loadingCities:    readonly(loadingCities),
        loadingDistricts: readonly(loadingDistricts),
        loadingVillages:  readonly(loadingVillages),
        selectedProvince,
        selectedCity,
        selectedDistrict,
        selectedVillage,
        provinceName:  readonly(provinceName),
        cityName:      readonly(cityName),
        districtName:  readonly(districtName),
        villageName:   readonly(villageName),
        getValue,
        setInitialValue,
    };
}
