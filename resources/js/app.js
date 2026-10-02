import Alpine from 'alpinejs';
import { createEcho } from './echo';

window.Alpine = Alpine;

Alpine.start();

function updateNotificationBadge() {
    document.querySelectorAll('[data-notification-badge]').forEach((badge) => {
        const count = Number.parseInt(badge.textContent || '0', 10) + 1;
        badge.textContent = String(count);
        badge.classList.remove('d-none');
    });
}

function showRealtimeNotification(notification) {
    const container = document.querySelector('[data-realtime-notifications]');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = 'toast show shadow border-0 mb-2';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');

    const body = document.createElement('div');
    body.className = 'toast-body';

    const heading = document.createElement('div');
    heading.className = 'fw-semibold mb-1';
    heading.textContent = 'Nueva notificación';

    const message = document.createElement('div');
    message.textContent = notification.message || 'Tienes una nueva notificación operativa.';

    const actions = document.createElement('div');
    actions.className = 'd-flex gap-2 mt-3';

    if (notification.action_url) {
        const link = document.createElement('a');
        link.className = 'btn btn-sm btn-primary';
        link.href = notification.action_url;
        link.textContent = 'Ver vale';
        actions.appendChild(link);
    }

    const dismiss = document.createElement('button');
    dismiss.type = 'button';
    dismiss.className = 'btn btn-sm btn-outline-secondary';
    dismiss.textContent = 'Cerrar';
    dismiss.addEventListener('click', () => toast.remove());
    actions.appendChild(dismiss);

    body.append(heading, message, actions);
    toast.appendChild(body);
    container.prepend(toast);

    window.setTimeout(() => toast.remove(), 15000);
}

function initializeRealtimeNotifications() {
    const realtime = document.querySelector('[data-realtime-user-id]');
    if (!realtime || !realtime.dataset.reverbKey) return;

    if (window.Echo) return;

    const echo = createEcho({
        key: realtime.dataset.reverbKey,
        host: realtime.dataset.reverbHost,
        port: realtime.dataset.reverbPort,
        scheme: realtime.dataset.reverbScheme,
    });

    echo.private(`App.Models.User.${realtime.dataset.realtimeUserId}`)
        .notification((notification) => {
            updateNotificationBadge();
            showRealtimeNotification(notification);
        });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeRealtimeNotifications, { once: true });
} else {
    initializeRealtimeNotifications();
}
