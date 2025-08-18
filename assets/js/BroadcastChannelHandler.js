/**
 * BroadcastChannelHandler
 * https://adocasts.com/lessons/cross-tab-communication-in-javascript-using-a-broadcastchannel
 *
 * Omogućuje strukturiranu komunikaciju između više tabova istog preglednika
 * korištenjem BroadcastChannel API-ja. Svaki tab ima stabilan `senderId`
 * kroz cijelu sesiju pomoću sessionStorage.
 *
 * @example
 * const channel = new BroadcastChannelHandler('orders');
 *
 * channel.on('orderProcessed', (data) => {
 *     console.log('Primljena obrada narudžbe: ', data.orderId);
 * });
 *
 * channel.send('orderProcessed', { orderId: 123 });
 */

// =======================
// HELPER FUNKCIJE
// =======================

/**
 * Ključ za spremanje ID-a trenutnog taba u sessionStorage.
 * @type {string}
 */
const senderIdKey = 'broadcast_sender_id';

/**
 * Generira jedinstveni ID.
 * @returns {string}
 */
function generateUniqueId() {
    return Math.random().toString(36).slice(2);
}

/**
 * Dohvaća ili generira jedinstveni ID po tabu.
 * Taj ID se sprema u sessionStorage i vrijedi dokle god je tab otvoren.
 *
 * @returns {string}
 */
function getSenderId() {
    let senderId = sessionStorage.getItem(senderIdKey) || generateUniqueId();
    sessionStorage.setItem(senderIdKey, senderId);
    return senderId;
}

// =======================
// GLAVNA KLASA
// =======================

export default class BroadcastChannelHandler {
    /**
     * Jedinstveni ID trenutnog taba (stabilan dok traje sesija).
     * @type {string}
     */
    senderId = getSenderId();

    /**
     * BroadcastChannel instanca.
     * @type {BroadcastChannel}
     */
    channel;

    /**
     * Listeneri po tipu poruke.
     * @type {Map<string, Function[]>}
     */
    listeners = new Map();

    /**
     * Inicijalizira kanal s određenim imenom.
     *
     * @param {string} channelName - Ime kanala koji se koristi za komunikaciju.
     */
    constructor(channelName) {
        this.channel = new BroadcastChannel(channelName);
        this.channel.onmessage = (event) => this._handleMessage(event);
    }

    /**
     * Interni handler za dolazne poruke.
     * Ignorira poruke koje je poslao ovaj tab (self-message).
     *
     * @param {MessageEvent} event
     * @private
     */
    _handleMessage(event) {
        const { sender, type, payload } = event.data || {};

        if (!type) return;

        // Preskoči ako je poruka od samog sebe
        if (sender === this.senderId) return;

        if (this.listeners.has(type)) {
            for (const callback of this.listeners.get(type)) {
                callback(payload);
            }
        }
    }

    /**
     * Šalje poruku na kanal.
     *
     * @param {string} type - Tip poruke.
     * @param {*} payload - Podaci poruke (mora biti JSON-serializable).
     */
    send(type, payload) {
        this.channel.postMessage({
            sender: this.senderId,
            type,
            payload
        });
    }

    /**
     * Registrira listener za određeni tip poruke.
     *
     * @param {string} type - Tip poruke.
     * @param {Function} callback - Callback koji će biti pozvan.
     */
    on(type, callback) {
        if (typeof callback !== 'function') return;
        if (!this.listeners.has(type)) {
            this.listeners.set(type, []);
        }
        this.listeners.get(type).push(callback);
    }

    /**
     * Uklanja prethodno registrirani listener za određeni tip poruke.
     *
     * Ako postoji više listenera za isti tip, uklanja se samo onaj koji je jednak danoj funkciji.
     * Ako nije pronađen, metoda ne radi ništa.
     *
     * @param {string} type - Tip poruke za koji se listener uklanja.
     * @param {Function} callback - Funkcija listenera koja se treba ukloniti.
     *
     * @example
     * function onOrder(data) {
     *     console.log('Nova narudžba', data);
     * }
     * 
     * channel.on('orderCreated', onOrder);
     * channel.off('orderCreated', onOrder); // Uklanja listener
     */
    off(type, callback) {
        if (!this.listeners.has(type)) return;

        const callbacks = this.listeners.get(type);
        const filtered = callbacks.filter(cb => cb !== callback);

        if (filtered.length > 0) {
            this.listeners.set(type, filtered);
        } else {
            this.listeners.delete(type);
        }
    }

    /**
     * Zatvara kanal i uklanja sve listenere.
     */
    close() {
        this.channel.close();
        this.listeners.clear();
    }
}
