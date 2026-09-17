// resources/js/crypto/keypair.js

import { deriveWrappingKey, base64Encode, base64Decode, concatBytes } from './pbkdf2.js';

export async function generateAndSealKeypair(password) {
    // 1. Generate RSA-OAEP keypair (2048-bit)
    const keypair = await crypto.subtle.generateKey(
        { name: 'RSA-OAEP', modulusLength: 2048, publicExponent: new Uint8Array([1, 0, 1]), hash: 'SHA-256' },
        true,
        ['encrypt', 'decrypt']
    );

    // 2. Export public key as SPKI DER -> base64
    const pubSpki = await crypto.subtle.exportKey('spki', keypair.publicKey);
    const publicKeyB64 = base64Encode(pubSpki);

    // 3. Export private key as PKCS#8 DER
    const privPkcs8 = await crypto.subtle.exportKey('pkcs8', keypair.privateKey);
    const privPkcs8Bytes = new Uint8Array(privPkcs8);

    // 4. Derive wrapping key from password
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const wrappingKey = await deriveWrappingKey(password, salt);

    // 5. Seal private key with AES-GCM
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const sealedPriv = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        wrappingKey,
        privPkcs8
    );

    return {
        publicKey: publicKeyB64,
        encryptedPrivateKey: base64Encode(sealedPriv),
        salt: base64Encode(salt),
        iv: base64Encode(iv),
        privateKeyPkcs8: privPkcs8Bytes,
        privateKeyHandle: keypair.privateKey,
    };
}

export async function unsealPrivateKey(password, sealedPrivB64, saltB64, ivB64) {
    const salt = base64Decode(saltB64);
    const iv = base64Decode(ivB64);
    const sealedPriv = base64Decode(sealedPrivB64);

    const wrappingKey = await deriveWrappingKey(password, salt);
    const privPkcs8 = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        wrappingKey,
        sealedPriv
    );

    return crypto.subtle.importKey(
        'pkcs8',
        privPkcs8,
        { name: 'RSA-OAEP', hash: 'SHA-256' },
        false,
        ['decrypt']
    );
}

export async function sealPrivateKeyWithPassword(privPkcs8Bytes, password) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const wrappingKey = await deriveWrappingKey(password, salt);
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const sealedPriv = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        wrappingKey,
        privPkcs8Bytes
    );

    return {
        sealedPriv: base64Encode(sealedPriv),
        salt: base64Encode(salt),
        iv: base64Encode(iv),
    };
}
