function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);
    for (let i = 0; i < raw.length; i += 1) {
        output[i] = raw.charCodeAt(i);
    }
    return output;
}

export function pushSupported() {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

export async function enablePushNotifications(vapidPublicKey) {
    if (!vapidPublicKey) {
        throw new Error('Push is not configured on the server (missing VAPID keys).');
    }
    if (!pushSupported()) {
        throw new Error('This browser does not support push notifications.');
    }

    // iOS Safari only supports Web Push after the app is added to the Home Screen.
    const isIos =
        /iPad|iPhone|iPod/.test(navigator.userAgent) ||
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const isStandalone =
        window.matchMedia('(display-mode: standalone)').matches ||
        window.navigator.standalone === true;
    if (isIos && !isStandalone) {
        throw new Error('On iPhone/iPad: Share → Add to Home Screen, open the app, then enable push.');
    }

    let permission = Notification.permission;
    if (permission === 'default') {
        permission = await Notification.requestPermission();
    }
    if (permission !== 'granted') {
        throw new Error('Notification permission was blocked. Enable it in browser settings.');
    }

    const registration = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;

    // Force an update so the push handler in sw.js is current.
    try {
        await registration.update();
    } catch {
        // ignore
    }

    let subscription = await registration.pushManager.getSubscription();
    if (subscription) {
        // Resubscribe if the existing subscription used different VAPID keys.
        await subscription.unsubscribe().catch(() => {});
        subscription = null;
    }

    subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    const json = subscription.toJSON();
    const encodings = PushManager.supportedContentEncodings || ['aes128gcm'];
    await window.axios.post(route('push-subscriptions.store'), {
        endpoint: json.endpoint,
        keys: json.keys,
        contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : encodings[0],
    });

    // Confirm permission works with a local system notification.
    try {
        await registration.showNotification('BEDITA eREQUISITION', {
            body: 'Browser notifications are enabled.',
            icon: '/icons/icon-192.png',
            tag: 'erequisition-enabled',
        });
    } catch {
        // ignore
    }

    return subscription;
}
