// Service Worker with Push Notification handling for Kohlkopf App
// This file handles push notifications even when the app is closed

self.addEventListener('install', (event) => {
    event.waitUntil(self.skipWaiting());
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', (event) => {
    if (!event.data || event.data.type !== 'skip-waiting') {
        return;
    }

    event.waitUntil(self.skipWaiting());
});

self.addEventListener('push', (event) => {
    if (!event.data) {
        console.warn('[SW] Push event received but no data');
        return;
    }

    const data = (() => {
        try {
            return event.data.json();
        } catch (error) {
            console.error('[SW] Failed to parse push data as JSON:', error);
            // Default notification text (German: "Notification")
            return { title: 'Benachrichtigung', body: event.data.text() };
        }
    })();

    console.log('[SW] Push notification received:', data);

    const title = data.title || 'Benachrichtigung';
    const options = {
        body: data.body || '',
        icon: data.icon || '/images/icon.svg',
        badge: data.badge || '/images/icon.svg',
        data: { url: data.url || '/' },
    };

    event.waitUntil(
        self.registration.showNotification(title, options)
            .then(() => console.log('[SW] Notification shown:', title))
            .catch(error => {
                console.error('[SW] Failed to show notification:', error);
            })
    );
});

self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event.notification);

    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            const target = new URL(url, self.location.origin).href;
            const existing = list.find((c) => c.url === target);
            if (existing) {
                console.log('[SW] Focusing existing window:', target);
                return existing.focus();
            } else {
                console.log('[SW] Opening new window:', target);
                return clients.openWindow(target);
            }
        }).catch(error => console.error('[SW] Failed to handle notification click:', error))
    );
});
