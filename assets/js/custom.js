import BroadcastChannelHandler from '@order-notifier/BroadcastChannelHandler';
import {
    getParsedStorageItem,
    setStringifiedStorageItem,
    removeStorageItem,
    // getParsedCookieObject,
    // updateCookieField,
} from '@order-notifier/storage-utils';
import { OrderNotifierData } from '@order-notifier/config';


const channel = new BroadcastChannelHandler('order-notifier');
const RELOAD_PAYLOAD_KEY = 'orderNotifierReloadPayload';

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
 * Sprema payload i pokreće reload.
 */
function saveReloadPayload(payload) {
    if (!payload) return;

    setStringifiedStorageItem(RELOAD_PAYLOAD_KEY, payload);

    // Provjeri ima li URL admin.php?page=wc-orders u query stringu
    if (window.location.href.indexOf('admin.php?page=wc-orders') !== -1) {
        console.log('Pokrećem reload - stranica narudžbi');
        location.reload();
    } else {
        checkAndShowReloadPayload();
        console.log('Reload preskočen jer trenutni URL nije stranica narudžbi');
    }
}


/**
 * Provjerava postoji li spremljeni payload i prikazuje ga.
 */
function checkAndShowReloadPayload() {
    const payload = getParsedStorageItem(RELOAD_PAYLOAD_KEY);
    if (payload) {
        // Emitiraj prema ostalim tabovima
        channel.send('message', payload);

        handleMessageEvent(payload);
        removeStorageItem(RELOAD_PAYLOAD_KEY); // Isključeno za test
        console.log('Payload prikazan nakon reloada');
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

    checkAndShowReloadPayload();

    const source = new EventSource(`${OrderNotifierData.endpoint}?_wpnonce=${OrderNotifierData.nonce}`);

    source.addEventListener('event', e => {
        try {
            const data = JSON.parse(e.data);
            console.log('RAW Podatci', data);

            if (data.event_type === 'message') {
                if (data.reload) {
                    saveReloadPayload(data.payload);
                } else {
                    channel.send('message', data.payload);
                    handleMessageEvent(data.payload);
                    console.log('Emitirano u druge tabove bez reloada');
                }

            } else if (data.event_type === 'system') {
                switch (data.type) {
                    case 'ping':
                        console.log('Primljen system-ping:', data);
                        break;
                    case 'heartbeat':
                        console.log('Primljen system-heartbeat:', data);
                        break;
                    default:
                        console.log('Nepoznati sistemski tip:', data);
                }
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
channel.on('message', (payload) => {
    // if (document.visibilityState !== 'visible') return;

    console.log(`channel.on('message')`, payload);
    handleMessageEvent(payload);
    removeStorageItem(RELOAD_PAYLOAD_KEY); // Test
});

document.addEventListener('DOMContentLoaded', initSSE);
