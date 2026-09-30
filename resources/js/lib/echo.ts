/**
 * Singleton Laravel Echo instance yang terhubung ke Reverb via Pusher protocol.
 * Import { echo } ke komponen mana saja yang perlu subscribe channel.
 */
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Pusher-js dipakai sebagai transport layer oleh Reverb
(window as any).Pusher = Pusher;

const echo = new Echo({
    broadcaster: 'reverb',
    key:         import.meta.env.VITE_REVERB_APP_KEY     as string,
    wsHost:      import.meta.env.VITE_REVERB_HOST        as string ?? 'localhost',
    wsPort:      Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort:     Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS:    (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
});

export { echo };
