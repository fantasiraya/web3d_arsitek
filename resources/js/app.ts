import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name.startsWith('Project/'):
                return null;

            case name.startsWith('auth/'):
                return AuthLayout;

            // Settings pages: pakai UnifiedLayout (adaptif admin/user), tanpa inner tab nav
            case name.startsWith('settings/'):
                return UnifiedLayout;

            default:
                return;
        }
    },

    progress: {
        color: '#4B5563',
    },
});

initializeTheme();
initializeFlashToast();