// resources/js/crypto/media-decrypt.js
// Decrypt a media blob: unwrap CEK with workspace DEK, then AES-GCM decrypt.

import { base64Decode } from './pbkdf2.js';

/**
 * Fetch + decrypt a media blob. Returns an object URL for rendering.
 * The response carries X-Cek-Wrapped and X-Blob-Iv headers from the server.
 */
export async function fetchAndDecryptMedia(url, dekHandle) {
    const response = await fetch(url, { credentials: 'same-origin' });
    if (!response.ok) throw new Error(`Blob fetch failed: ${response.status}`);

    const cekWrappedB64 = response.headers.get('X-Cek-Wrapped');
    const ivB64 = response.headers.get('X-Blob-Iv');
    if (!cekWrappedB64 || !ivB64) throw new Error('Missing decryption headers');

    const ciphertext = await response.arrayBuffer();
    const plaintext = await decryptBlob(ciphertext, cekWrappedB64, ivB64, dekHandle);

    return URL.createObjectURL(new Blob([plaintext]));
}

/**
 * Decrypt a ciphertext blob given the wrapped CEK + IV.
 */
export async function decryptBlob(ciphertext, cekWrappedB64, ivB64, dekHandle) {
    // Unwrap CEK with workspace DEK
    const cekCombined = base64Decode(cekWrappedB64);
    const cekIv = cekCombined.slice(0, 12);
    const sealedCek = cekCombined.slice(12);
    const rawCek = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv: cekIv }, dekHandle, sealedCek
    );
    const cek = await crypto.subtle.importKey(
        'raw', rawCek, { name: 'AES-GCM', length: 256 }, false, ['decrypt']
    );

    // Decrypt the blob
    const iv = base64Decode(ivB64);
    return crypto.subtle.decrypt({ name: 'AES-GCM', iv }, cek, ciphertext);
}
