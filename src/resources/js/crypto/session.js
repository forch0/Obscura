// resources/js/crypto/session.js
// Holds the user's RSA private key in memory for the session.
// The raw PKCS8 bytes are kept in sessionStorage (cleared on tab close)
// so the key survives page navigations without re-unsealing.

import { base64Encode, base64Decode } from './pbkdf2.js';

const STORAGE_KEY = 'obscura_pkcs8';

let privateKeyHandle = null;

export function setPrivateKeyHandle(key) {
    privateKeyHandle = key;
}

export function getPrivateKeyHandle() {
    return privateKeyHandle;
}

/**
 * Store the private key: keep the CryptoKey handle in memory AND
 * persist the raw PKCS8 bytes to sessionStorage so page reloads work.
 */
export async function storePrivateKey(cryptoKey) {
    privateKeyHandle = cryptoKey;
    const pkcs8 = await crypto.subtle.exportKey('pkcs8', cryptoKey);
    sessionStorage.setItem(STORAGE_KEY, base64Encode(new Uint8Array(pkcs8)));
}

/**
 * Get the private key handle. If not in memory, restore from
 * sessionStorage (re-imports the PKCS8 bytes as a non-extractable key).
 */
export async function restorePrivateKey() {
    if (privateKeyHandle) return privateKeyHandle;

    const b64 = sessionStorage.getItem(STORAGE_KEY);
    if (!b64) return null;

    const pkcs8 = base64Decode(b64);
    privateKeyHandle = await crypto.subtle.importKey(
        'pkcs8',
        pkcs8,
        { name: 'RSA-OAEP', hash: 'SHA-256' },
        true,
        ['decrypt']
    );
    return privateKeyHandle;
}

export function clearPrivateKey() {
    privateKeyHandle = null;
    sessionStorage.removeItem(STORAGE_KEY);
}

export function hasPrivateKey() {
    return privateKeyHandle !== null || sessionStorage.getItem(STORAGE_KEY) !== null;
}
