import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import AuthLayout from '@/layouts/AuthLayout.vue';
import UnifiedLayout from '@/layouts/UnifiedLayout.vue';
import { initializeFlashToast } from '@/lib/flashToast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),

    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', { eager: true }) as Record<string, any>;
        return pages[`./pages/${name}.vue`];
    },

    layout: (name) => {
        switch (true) {
            case name === 'Welcome':
            case name === 'Error':
            case name.startsWith('Project/'):
            case name.startsWith('Projects/'):
            case name.startsWith('Teams/'):
            case name.startsWith('Checkout/'):
                return null;

            case name === 'auth/Login':
            case name === 'auth/Register':
            case name === 'auth/ForgotPassword':
                // Halaman ini pakai LandingLayout via defineOptions di page component
                return undefined;

            case name.startsWith('auth/'):
                return AuthLayout;

            // Settings pages: pakai UnifiedLayout (adaptif admin/user)
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
