// resources/js/crypto/media-encrypt.js
// Client-side media encryption: generates a per-file CEK, encrypts the blob,
// wraps the CEK with the workspace DEK, and produces an encrypted thumbnail.

import { base64Encode } from './pbkdf2.js';

const concat = (...arrays) => {
    const total = arrays.reduce((n, a) => n + a.length, 0);
    const out = new Uint8Array(total);
    let offset = 0;
    for (const a of arrays) { out.set(a, offset); offset += a.length; }
    return out;
};

/**
 * Encrypt a File for upload. Returns ciphertext blob + all metadata the server needs.
 */
export async function encryptFile(file, dekHandle) {
    // 1. Per-file content encryption key (CEK)
    const cek = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']
    );
    const iv = crypto.getRandomValues(new Uint8Array(12));

    // 2. Encrypt file bytes with CEK
    const plaintext = await file.arrayBuffer();
    const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, cek, plaintext);

    // 3. Wrap CEK with workspace DEK (IV prepended to wrapped output)
    const rawCek = new Uint8Array(await crypto.subtle.exportKey('raw', cek));
    const cekIv = crypto.getRandomValues(new Uint8Array(12));
    const sealedCek = new Uint8Array(await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: cekIv }, dekHandle, rawCek
    ));
    const cekWrapped = base64Encode(concat(cekIv, sealedCek));

    // 4. Encrypted thumbnail (same CEK, fresh IV)
    let thumbCiphertext = null, thumbIv = null;
    try {
        const thumb = await generateEncryptedThumbnail(file, cek);
        thumbCiphertext = thumb.blob;
        thumbIv = thumb.iv;
    } catch (e) {
        // Thumbnail generation is best-effort
    }

    // 5. Encrypt title (filename) with DEK
    const title = await encryptTextField(file.name, dekHandle);

    return {
        ciphertext: new Blob([ciphertext]),
        thumbnail: thumbCiphertext,
        cek_wrapped: cekWrapped,
        iv: base64Encode(iv),
        thumb_iv: thumbIv,
        mime_type: file.type,
        size: ciphertext.byteLength,
        encrypted_title: title.ciphertext,
        title_iv: title.iv,
    };
}

/**
 * Encrypt a short text field (title/caption) with the workspace DEK.
 */
export async function encryptTextField(plaintext, dekHandle) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const encoded = new TextEncoder().encode(plaintext);
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv }, dekHandle, encoded
    );
    return { ciphertext: base64Encode(new Uint8Array(ciphertext)), iv: base64Encode(iv) };
}

/**
 * Decrypt a text field with the workspace DEK.
 */
export async function decryptTextField(ciphertextB64, ivB64, dekHandle) {
    const { base64Decode } = await import('./pbkdf2.js');
    const iv = base64Decode(ivB64);
    const ciphertext = base64Decode(ciphertextB64);
    const plaintext = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv }, dekHandle, ciphertext
    );
    return new TextDecoder().decode(plaintext);
}

/**
 * Generate an encrypted JPEG thumbnail (max 320px) using the file's CEK.
 * Images → canvas decode. Video → first-frame grab. Everything else → null
 * (grid shows a type icon; has_thumbnail stays false).
 */
async function generateEncryptedThumbnail(file, cek) {
    if (file.type.startsWith('image/')) {
        return await thumbnailFromImage(file, cek);
    }
    if (file.type.startsWith('video/')) {
        return await thumbnailFromVideo(file, cek);
    }
    throw new Error('No thumbnail strategy for ' + file.type);
}

async function thumbnailFromImage(file, cek) {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(320 / bitmap.width, 320 / bitmap.height, 1);
    const w = Math.max(1, Math.round(bitmap.width * scale));
    const h = Math.max(1, Math.round(bitmap.height * scale));

    const canvas = new OffscreenCanvas(w, h);
    const ctx = canvas.getContext('2d');
    ctx.drawImage(bitmap, 0, 0, w, h);
    bitmap.close();

    const thumbBlob = await canvas.convertToBlob({ type: 'image/jpeg', quality: 0.75 });
    return await encryptThumb(thumbBlob, cek);
}

async function thumbnailFromVideo(file, cek) {
    const url = URL.createObjectURL(file);
    try {
        const video = document.createElement('video');
        video.muted = true;
        video.playsInline = true;
        video.preload = 'metadata';
        video.src = url;

        await new Promise((resolve, reject) => {
            video.onloadeddata = () => { video.currentTime = Math.min(0.5, video.duration || 0); };
            video.onseeked = resolve;
            video.onerror = () => reject(new Error('video decode failed'));
        });

        const scale = Math.min(320 / video.videoWidth, 320 / video.videoHeight, 1);
        const w = Math.max(1, Math.round(video.videoWidth * scale));
        const h = Math.max(1, Math.round(video.videoHeight * scale));

        const canvas = document.createElement('canvas');
        canvas.width = w;
        canvas.height = h;
        canvas.getContext('2d').drawImage(video, 0, 0, w, h);

        const thumbBlob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', 0.75));
        return await encryptThumb(thumbBlob, cek);
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function encryptThumb(thumbBlob, cek) {
    const thumbBytes = await thumbBlob.arrayBuffer();
    const thumbIvBytes = crypto.getRandomValues(new Uint8Array(12));
    const thumbCiphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv: thumbIvBytes }, cek, thumbBytes
    );
    return { blob: new Blob([thumbCiphertext]), iv: base64Encode(thumbIvBytes) };
}
