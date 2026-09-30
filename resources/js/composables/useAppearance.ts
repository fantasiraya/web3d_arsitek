import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    const currentAppearance = getStoredAppearance();

    updateTheme(currentAppearance || 'system');
};

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    // Initialize theme from saved preference or default to system...
    const savedAppearance = getStoredAppearance();
    updateTheme(savedAppearance || 'system');

    // Set up system theme change listener...
    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

// Module-scope reactive state — shared across all useAppearance() calls
const appearance = ref<Appearance>('system');

// Reactive flag that mirrors document.documentElement.classList.has('dark')
// Updated whenever updateTheme() runs so computed isDark stays in sync
const _isDarkDOM = ref(false);

/** Patch updateTheme so it also keeps _isDarkDOM in sync */
const _originalUpdateTheme = updateTheme;
function _updateThemeReactive(value: Appearance): void {
    _originalUpdateTheme(value);
    if (typeof document !== 'undefined') {
        _isDarkDOM.value = document.documentElement.classList.contains('dark');
    }
}

export function useAppearance(): UseAppearanceReturn & { isDark: ComputedRef<boolean>; toggleTheme: () => void } {
    onMounted(() => {
        const savedAppearance = localStorage.getItem('appearance') as Appearance | null;

        if (savedAppearance) {
            appearance.value = savedAppearance;
        }

        // Always sync reactive DOM flag on mount
        _isDarkDOM.value = document.documentElement.classList.contains('dark');
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            // Use _isDarkDOM as reactive dependency so computed updates when DOM changes
            return _isDarkDOM.value ? 'dark' : 'light';
        }
        return appearance.value;
    });

    const isDark = computed<boolean>(() => {
        return resolvedAppearance.value === 'dark';
    });

    function updateAppearance(value: Appearance) {
        appearance.value = value;
        localStorage.setItem('appearance', value);
        setCookie('appearance', value);
        _updateThemeReactive(value);
    }

    function toggleTheme() {
        const newTheme = resolvedAppearance.value === 'dark' ? 'light' : 'dark';
        updateAppearance(newTheme);
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
        isDark,
        toggleTheme,
    };
}
