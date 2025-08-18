/**
 * === storage-utils.js ===
 * Pomoćne funkcije za rad s localStorage uz sigurnu serializaciju/parsing.
 */

// ===========================
// SIGURNO PARSIRANJE ARRAYA
// ===========================

/**
 * Sigurno parsira vrijednost u array.
 * Ako je vrijednost string, pokušava parsirati kao JSON.
 * Ako je već array, vraća ga direktno.
 * Ako je `null`, `undefined` ili prazan string, vraća prazni array.
 *
 * @param {*} item - Vrijednost koju želimo parsirati.
 * @returns {Array}
 */
export function parseArray(item) {
  if (!item || !item.length) return [];
  if (Array.isArray(item)) return item;

  try {
    const parsed = JSON.parse(item);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

// ===========================
// SPREMANJE I UČITAVANJE
// ===========================

/**
 * Sprema vrijednost u localStorage kao JSON string.
 * Ako je vrijednost `null` ili `undefined`, uklanja ključ.
 *
 * @param {string} key
 * @param {*} value
 */
export function setStringifiedStorageItem(key, value) {
  if (value === undefined || value === null) {
    localStorage.removeItem(key);
    return;
  }

  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch (e) {
    console.error(`Greška pri spremanju u localStorage (${key}):`, e);
  }
}

/**
 * Dohvaća JSON string iz localStorage i pokušava ga parsirati.
 * Ako je parsiranje neuspješno, vraća `null`.
 *
 * @param {string} storageKey
 * @returns {*}
 */
export function getParsedStorageItem(storageKey) {
  const stringValue = localStorage.getItem(storageKey);

  if (!stringValue) return null;

  try {
    return JSON.parse(stringValue);
  } catch {
    console.warn(`Neuspješno parsiranje localStorage vrijednosti za ključ: ${storageKey}`);
    return null;
  }
}

// ===========================
// DODATNE FUNKCIJE
// ===========================

/**
 * Uklanja stavku iz localStorage.
 *
 * @param {string} key
 */
export function removeStorageItem(key) {
  localStorage.removeItem(key);
}

/**
 * Provjerava postoji li ključ u localStorage.
 *
 * @param {string} key
 * @returns {boolean}
 */
export function hasStorageItem(key) {
  return localStorage.getItem(key) !== null;
}

/**
 * Merge-a postojeći objekt u localStorage s novim podacima.
 * Radi samo ako su obje strane objekti.
 *
 * @param {string} key
 * @param {Object} newData
 */
export function mergeStorageItem(key, newData) {
  if (typeof newData !== 'object' || newData === null) return;

  const existing = getParsedStorageItem(key);

  if (existing && typeof existing === 'object') {
    const merged = { ...existing, ...newData };
    setStringifiedStorageItem(key, merged);
  } else {
    // Ako ne postoji objekt, samo postavi novi
    setStringifiedStorageItem(key, newData);
  }
}

/**
 * Dohvaća vrijednost bundle kolačića i parsira je kao objekt.
 *
 * @param {string} cookieName - Ime kolačića (npr. 'order_data').
 * @returns {Object|null} - Parsirani objekt ili null ako ne postoji ili nije ispravno.
 */
export function getParsedCookieObject(cookieName) {
  const match = document.cookie.match(new RegExp('(^| )' + cookieName + '=([^;]+)'));
  if (!match) return null;

  try {
    return JSON.parse(decodeURIComponent(match[2]));
  } catch {
    console.warn(`Neuspješno parsiranje JSON kolačića: ${cookieName}`);
    return null;
  }
}

/**
 * Ažurira pojedino polje unutar bundle kolačića.
 * ⚠️ Napomena: Kolačić mora biti prethodno postavljen kao JSON string.
 *
 * @param {string} cookieName - Ime kolačića (npr. 'order_data').
 * @param {string} field - Ključ koji želimo ažurirati.
 * @param {*} value - Nova vrijednost.
 * @param {number} maxAge - Trajanje kolačića u sekundama.
 */
export function updateCookieField(cookieName, field, value, maxAge = 300) {
  const cookieData = getParsedCookieObject(cookieName) || {};
  cookieData[field] = value;

  try {
    const encodedValue = encodeURIComponent(JSON.stringify(cookieData));
    document.cookie = `${cookieName}=${encodedValue}; path=/; max-age=${maxAge}; samesite=strict`;
  } catch (e) {
    console.error(`Greška pri spremanju kolačića ${cookieName}:`, e);
  }
}
