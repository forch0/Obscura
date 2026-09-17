// resources/js/crypto/dek.js
// Workspace Data Encryption Key (DEK) generation, sealing, and unsealing.

import { base64Encode, base64Decode } from './pbkdf2.js';

/**
 * Import an RSA-OAEP public key from base64-encoded SPKI.
 */
export async function importPublicKey(publicKeyB64) {
    const raw = base64Decode(publicKeyB64);
    return crypto.subtle.importKey(
        'spki',
        raw,
        { name: 'RSA-OAEP', hash: 'SHA-256' },
        true,
        ['encrypt']
    );
}

/**
 * Generate a new AES-GCM 256 DEK and seal it to the owner's public key.
 * Returns { wrappedDek, dekHandle }.
 */
export async function generateAndSealDek(ownerPublicKeyB64) {
    // 1. Generate AES-GCM 256 DEK (extractable so we can wrap it)
    const dek = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 },
        true,
        ['encrypt', 'decrypt']
    );

    // 2. Import owner's public key
    const pubKey = await importPublicKey(ownerPublicKeyB64);

    // 3. Export raw DEK bytes
    const rawDek = await crypto.subtle.exportKey('raw', dek);

    // 4. Seal DEK with RSA-OAEP (no IV needed for RSA-OAEP)
    const sealed = await crypto.subtle.encrypt(
        { name: 'RSA-OAEP' },
        pubKey,
        rawDek
    );

    return {
        wrappedDek: base64Encode(new Uint8Array(sealed)),
        dekHandle: dek,
    };
}

/**
 * Unseal a DEK using the owner's private key handle.
 * Returns an extractable AES-GCM CryptoKey — extractable is required so the
 * DEK can be re-wrapped for members, access codes, and re-key operations.
 */
export async function unsealDek(sealedDekB64, ownerPrivateKeyHandle) {
    const sealed = base64Decode(sealedDekB64);
    const rawDek = await crypto.subtle.decrypt(
        { name: 'RSA-OAEP' },
        ownerPrivateKeyHandle,
        sealed
    );
    return crypto.subtle.importKey(
        'raw',
        rawDek,
        { name: 'AES-GCM', length: 256 },
        true, // extractable — needed for re-wrapping (access codes, members, rekey)
        ['encrypt', 'decrypt']
    );
}

/**
 * Encrypt a workspace name (or any plaintext) with the DEK.
 * Returns { encryptedName, nameIv }.
 */
export async function encryptName(name, dekHandle) {
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const enc = new TextEncoder();
    const ciphertext = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        dekHandle,
        enc.encode(name)
    );
    return {
        encryptedName: base64Encode(new Uint8Array(ciphertext)),
        nameIv: base64Encode(iv),
    };
}

/**
 * Decrypt a workspace name using the DEK.
 */
export async function decryptName(encryptedNameB64, dekHandle, nameIvB64) {
    const ciphertext = base64Decode(encryptedNameB64);
    const iv = base64Decode(nameIvB64);
    const plaintext = await crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        dekHandle,
        ciphertext
    );
    return new TextDecoder().decode(plaintext);
}
