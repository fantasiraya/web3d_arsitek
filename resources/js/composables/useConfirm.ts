/**
 * useConfirm — composable untuk menampilkan confirm dialog programatically
 *
 * Cara pakai:
 *   const { confirm } = useConfirm()
 *   const ok = await confirm({ title: 'Hapus?', description: 'Tidak bisa diurungkan.', confirmText: 'Hapus', variant: 'destructive' })
 *   if (ok) { ... lakukan aksi ... }
 */
import { ref, shallowRef } from 'vue';

export interface ConfirmOptions {
    title: string;
    description?: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'destructive' | 'default';
    icon?: 'trash' | 'warning' | 'info';
}

interface ConfirmState extends ConfirmOptions {
    open: boolean;
    resolve: ((value: boolean) => void) | null;
}

const state = ref<ConfirmState>({
    open: false,
    title: '',
    description: '',
    confirmText: 'Konfirmasi',
    cancelText: 'Batal',
    variant: 'destructive',
    icon: 'warning',
    resolve: null,
});

export function useConfirm() {
    function confirm(options: ConfirmOptions): Promise<boolean> {
        return new Promise<boolean>((resolve) => {
            state.value = {
                open: true,
                title: options.title,
                description: options.description ?? '',
                confirmText: options.confirmText ?? 'Konfirmasi',
                cancelText: options.cancelText ?? 'Batal',
                variant: options.variant ?? 'destructive',
                icon: options.icon ?? 'warning',
                resolve,
            };
        });
    }

    function handleConfirm() {
        state.value.resolve?.(true);
        state.value.open = false;
    }

    function handleCancel() {
        state.value.resolve?.(false);
        state.value.open = false;
    }

    return { confirm, state, handleConfirm, handleCancel };
}
