// resources/js/crypto/recovery.js

import { deriveWrappingKey, base64Encode, base64Decode } from './pbkdf2.js';

const CHARSET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

export function generateRecoveryCode() {
    const length = 24;
    const groupSize = 4;
    let code = '';
    for (let i = 0; i < length; i++) {
        code += CHARSET[crypto.getRandomValues(new Uint32Array(1))[0] % CHARSET.length];
    }
    return code.match(new RegExp(`.{1,${groupSize}}`, 'g')).join('-');
}

export async function deriveRecoveryWrappingKey(recoveryCode, salt) {
    const enc = new TextEncoder();
    const baseKey = await crypto.subtle.importKey(
        'raw',
        enc.encode(recoveryCode),
        'PBKDF2',
        false,
        ['deriveKey']
    );

    return crypto.subtle.deriveKey(
        { name: 'PBKDF2', salt, iterations: 250000, hash: 'SHA-256' },
        baseKey,
        { name: 'AES-GCM', length: 256 },
        false,
        ['encrypt', 'decrypt']
    );
}

export async function sealPrivateKeyWithRecoveryCode(privPkcs8Bytes, recoveryCode) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const wrappingKey = await deriveRecoveryWrappingKey(recoveryCode, salt);
    const sealedPriv = await crypto.subtle.encrypt(
        { name: 'AES-GCM', iv },
        wrappingKey,
        privPkcs8Bytes
    );

    return {
        encryptedPrivateKeyRecovery: base64Encode(sealedPriv),
        recoveryCodeSalt: base64Encode(salt),
        recoveryIv: base64Encode(iv),
        recoveryCode: recoveryCode,
        recoveryCodeHash: base64Encode(
            new Uint8Array(
                await crypto.subtle.digest('SHA-256',
                    new TextEncoder().encode(recoveryCode + base64Encode(salt))
                )
            )
        ),
    };
}

export async function unsealPrivateKeyWithRecoveryCode(recoveryCode, sealedPrivB64, saltB64, ivB64) {
    const salt = base64Decode(saltB64);
    const iv = base64Decode(ivB64);
    const sealedPriv = base64Decode(sealedPrivB64);

    const wrappingKey = await deriveRecoveryWrappingKey(recoveryCode, salt);
    return crypto.subtle.decrypt(
        { name: 'AES-GCM', iv },
        wrappingKey,
        sealedPriv
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
