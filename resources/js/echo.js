import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

export function createEcho(config) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: Number(config.port || 80),
        wssPort: Number(config.port || 443),
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}
