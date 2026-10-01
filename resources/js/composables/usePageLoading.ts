/**
 * usePageLoading — track Inertia navigation state
 * Returns `isLoading` ref yang true saat halaman sedang diload
 * sehingga komponen skeleton bisa ditampilkan.
 */
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';

export function usePageLoading(initialDelay = 0) {
    const isLoading = ref(false);
    let startTimer: ReturnType<typeof setTimeout> | null = null;

    const removeStart = router.on('start', () => {
        // Delay kecil agar skeleton tidak flicker pada navigasi cepat
        startTimer = setTimeout(() => { isLoading.value = true; }, initialDelay);
    });

    const removeFinish = router.on('finish', () => {
        if (startTimer) clearTimeout(startTimer);
        isLoading.value = false;
    });

    onBeforeUnmount(() => {
        removeStart();
        removeFinish();
        if (startTimer) clearTimeout(startTimer);
    });

    return { isLoading };
}
