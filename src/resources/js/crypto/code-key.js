// resources/js/crypto/code-key.js
// Access code key derivation — derives a key from the raw code using PBKDF2,
// then uses it to unwrap the workspace DEK.

import { base64Encode, base64Decode } from './pbkdf2.js';

/**
 * Derive an AES-GCM key from a raw access code using PBKDF2.
 * Mirrors the server's Argon2id hashing but uses PBKDF2 for key derivation
 * (WebCrypto doesn't expose Argon2id).
 */
export async function deriveCodeKey(rawCode, saltB64) {
    const salt = base64Decode(saltB64);
    const enc = new TextEncoder();

    // Normalize: strip dashes, uppercase
    const normalized = rawCode.toUpperCase().replace(/-/g, '');

    const baseKey = await crypto.subtle.importKey(
        'raw',
        enc.encode(normalized),
        'PBKDF2',
        false,
        ['deriveKey']
    );

    return crypto.subtle.deriveKey(
        {
            name: 'PBKDF2',
            salt,
            iterations: 250000,
            hash: 'SHA-256',
        },
        baseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
    );
}

/**
 * Unseal a DEK using a code-derived key.
 * The wrapped_dek has the IV prepended: [iv(12) | ciphertext]
 */
export async function unsealDekWithCode(wrappedDekB64, codeKey) {
    const wrapped = base64Decode(wrappedDekB64);
    const iv = wrapped.slice(0, 12);
    const sealed = wrapped.slice(12);

    const rawDek = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        codeKey,
        sealed
    );

    return crypto.subtle.importKey(
        'raw',
        rawDek,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
    );
}

/**
 * Seal a DEK with a code-derived key (owner-side).
 * Returns base64 of [iv | ciphertext].
 */
export async function sealDekForCode(dekHandle, rawCode, saltB64) {
    const codeKey = await deriveCodeKey(rawCode, saltB64);
    const rawDek = await crypto.subtle.exportKey('raw', dekHandle);
    const iv = crypto.getRandomValues(new Uint8Array(12));

    const sealed = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        codeKey,
        rawDek
    );

    // Prepend IV to ciphertext
    const combined = new Uint8Array(iv.length + sealed.byteLength);
    combined.set(iv, 0);
    combined.set(new Uint8Array(sealed), iv.length);

    return base64Encode(combined);
}
