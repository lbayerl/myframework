// Custom Service Worker with Push Notification handling
// This file is imported by the PWA bundle's service worker

self.addEventListener('push', (event) => {
    if (!event.data) return;

    const data = (() => {
        try {
            return event.data.json();
        } catch {
            return { title: 'Benachrichtigung', body: event.data.text() };
        }
    })();

    const title = data.title || 'Benachrichtigung';
    const options = {
        body: data.body || '',
        icon: data.icon || '/icon.svg',
        badge: data.badge || '/icon.svg',
        data: { url: data.url || '/' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then((list) => {
            const target = new URL(url, self.location.origin).href;
            const existing = list.find((c) => c.url === target);
            return existing ? existing.focus() : clients.openWindow(target);
        })
    );
});
