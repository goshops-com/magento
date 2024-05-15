self.addEventListener("install", onServiceWorkerInstalled);
self.addEventListener("activate", onServiceWorkerActivated);
self.addEventListener("push", (e) => e.waitUntil(onNotificationReceived(e)));
self.addEventListener("notificationclick", (e) => e.waitUntil(onNotificationClicked(e)));
self.addEventListener("notificationclose", (e) => e.waitUntil(onNotificationClosed(e)));

function onServiceWorkerInstalled(e) {
    e.waitUntil(self.skipWaiting());
}

function onServiceWorkerActivated(e) {
    e.waitUntil(self.clients.claim());
}

async function onNotificationReceived(e) {
    const data = JSON.parse(JSON.stringify(e.data.json()));
    console.log('notification received data');

    try {
        await self.registration.showNotification(data.notification.title, data.notification);
    } catch (ex) {
        console.log('error notification received', ex);
    }
}

async function onNotificationClicked(i) {
    i.notification.close();
    if (i.notification.data && i.notification.data.url) {
        let url = i.notification.data.url;
        if (i.action === "gopersonal-primary-action") {
            url = i.notification.data.primaryUrl;
        } else if (i.action === "gopersonal-secondary-action") {
            url = i.notification.data.secondaryUrl;
        }
        try {
            return self.clients.openWindow(url);
        } catch (e) {
            console.log("error notification clicked");
            return self.clients.openWindow(url);
        }
    }
}
async function onNotificationClosed(e) {
    console.log("notification closed", e);
}