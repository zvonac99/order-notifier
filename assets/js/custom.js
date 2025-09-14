import BroadcastChannelHandler from '@order-notifier/BroadcastChannelHandler';
import { OrderNotifierData } from '@order-notifier/config';

const channel = new BroadcastChannelHandler('order-notifier');

/**
 * Cookie helper funkcije
 */
function getCookie(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
    return null;
}

function setCookie(name, value, maxAge = 300, path = "/") {
    document.cookie = `${name}=${value}; max-age=${maxAge}; path=${path}`;
}

function deleteCookie(name, path = "/") {
    document.cookie = `${name}=; max-age=0; path=${path}`;
}

/**
 * Prikazuje notifikaciju s danim parametrima.
 */
function showNotification(title, message, type = 'info', position = 'top', timeout = 0, icon = '') {
    return notifier.show(title, message, type, icon, timeout, position);
}

/**
 * Ažurira badge prikaz broja novih narudžbi u izborniku.
 */
function updateBadge(count = 0) {
    const badgeText = count > 0 ? `${count} ${count === 1 ? 'nova' : 'nove'} narudžb${count === 1 ? 'a' : 'e'}` : '';
    const badge = document.querySelector('.order-badge');
    const link = document.querySelector('#toplevel_page_woocommerce ul.wp-submenu a[href*="admin.php?page=wc-orders"]');

    if (count > 0 && link) {
        if (!badge) {
            const newBadge = document.createElement('span');
            newBadge.className = 'order-badge';
            newBadge.textContent = badgeText;
            link.appendChild(newBadge);
        } else {
            badge.textContent = badgeText;
        }
    } else if (badge) {
        badge.remove();
    }
}

/**
 * Obradjuje payload eventa tipa 'message'.
 */
function handleMessageEvent(payload) {
    if (typeof payload.count === 'number') {
        updateBadge(payload.count);
    } else if (payload.title && payload.message) {
        showNotification(
            payload.title,
            payload.message,
            payload.type || 'info',
            payload.position || 'top',
            payload.timeout || 0,
            payload.icon || ''
        );
    } else {
        console.warn('Nedovoljno podataka za prikaz notifikacije', payload);
    }
}

/**
 * Handshake s cookie logikom
 */
function ReloadPage(data) {
    if (!data.uid) {
        console.warn("Nema UID u payloadu", data);
        return;
    }

    const cookieName = data.uid;
    const existingCookie = getCookie(cookieName);

    if (existingCookie === null) {
        console.log(`Novi event UID ${cookieName}, postavljam cookie=0`);
        setCookie(cookieName, 0, 300);

        if (window.location.href.includes('admin.php?page=wc-orders')) {
            location.reload();
            return;
        } else {
            console.log('Reload preskočen (nije stranica narudžbi)');
        }
    }

    if (existingCookie === "0") {
        console.log(`Prikazujem payload za UID ${cookieName} i označavam cookie=1`);
        handleMessageEvent(data.payload);
        setCookie(cookieName, 1, 300);
        return;
    }

    if (existingCookie === "1") {
        console.log(`UID ${cookieName} već procesuiran, ništa ne radim`);
    }
}

/**
 * Odlučuje kako obraditi event tipa 'message'.
 */
function handleMessageOrReload(data) {
    if (data.reload) {
        ReloadPage(data);
    } else {
        channel.send('message', data.payload);
        handleMessageEvent(data.payload);
        setCookie(data.uid, 1, 300);
        console.log('Event prikazan i emitiran u druge tabove bez reloada');
    }
}

/**
 * Obrada sistemskih eventa tipa ping/heartbeat
 */
function handleSystemEvent(data) {
    switch (data.type) {
        case 'ping':
            console.log('%cEvent tip: system-ping', 'color: orange; font-weight: bold;', data);
            // console.log('Primljen system-ping:', data);
            break;
        case 'heartbeat':
            console.log('Primljen system-heartbeat:', data);
            break;
        default:
            console.log('Nepoznati sistemski tip:', data);
    }
}

/**
 * Inicijalizira SSE vezu i postavlja event listener za poruke.
 */
function initSSE() {
    if (!OrderNotifierData.endpoint) {
        console.error("Nije definiran SSE URL");
        return;
    }

    const source = new EventSource(`${OrderNotifierData.endpoint}?_wpnonce=${OrderNotifierData.nonce}`);

    source.addEventListener('event', e => {
        try {
            const data = JSON.parse(e.data);
            console.log('%cSSE Event RAW:', 'color: green; font-weight: bold;', data);

            if (data.event_type === 'message') {
                // console.log('%cEvent tip: message', 'color: green; font-weight: bold;');
                // console.log('UID:', data.uid);
                // console.log('Payload:', data.payload);
                // console.log('Reload:', data.reload);

                handleMessageOrReload(data);
            } else if (data.event_type === 'system') {
                handleSystemEvent(data);
            }

        } catch (error) {
            console.error('Greška pri parsiranju SSE eventa:', error);
        }
    });

    source.onerror = event => {
        if (event?.target?.readyState === EventSource.CLOSED) {
            console.warn("SSE veza zatvorena trajno.");
        } else if (event?.target?.readyState === EventSource.CONNECTING) {
            console.warn("SSE pokušava ponovno uspostaviti vezu...");
        } else {
            console.warn("Nepoznata SSE greška (nije kritična).");
        }
    };
}

// Slušaj poruke s kanala
channel.on('message', payload => {
    console.log(`channel.on('message')`, payload);
    handleMessageEvent(payload);
    removeStorageItem(RELOAD_PAYLOAD_KEY); // Test
});

document.addEventListener('DOMContentLoaded', initSSE);
