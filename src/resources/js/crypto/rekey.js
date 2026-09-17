// resources/js/crypto/rekey.js
// Workspace re-key: generate new DEK, re-wrap all CEKs, re-seal for members.

import { base64Encode, base64Decode } from './pbkdf2.js';

const concat = (...arrays) => {
    const total = arrays.reduce((n, a) => n + a.length, 0);
    const out = new Uint8Array(total);
    let offset = 0;
    for (const a of arrays) { out.set(a, offset); offset += a.length; }
    return out;
};

/**
 * Decrypt + re-encrypt a field with a different key.
 * Input format: [iv(12) | ciphertext] as base64.
 */
async function reencryptField(combinedB64, oldDek, newDek) {
    if (!combinedB64) return null;
    const combined = base64Decode(combinedB64);
    const oldIv = combined.slice(0, 12);
    const sealed = combined.slice(12);
    const raw = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: oldIv }, oldDek, sealed);
    const newIv = crypto.getRandomValues(new Uint8Array(12));
    const newSealed = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: newIv }, newDek, raw);
    return {
        ciphertext: base64Encode(concat(newIv, new Uint8Array(newSealed))),
        iv: base64Encode(newIv),
    };
}

async function rewrapCek(cekWrappedB64, oldDek, newDek) {
    const combined = base64Decode(cekWrappedB64);
    const cekIv = combined.slice(0, 12);
    const sealedCek = combined.slice(12);
    const rawCek = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: cekIv }, oldDek, sealedCek);
    const newCekIv = crypto.getRandomValues(new Uint8Array(12));
    const newSealedCek = await crypto.subtle.encrypt({ name: 'AES-GCM', iv: newCekIv }, newDek, rawCek);
    return base64Encode(concat(newCekIv, new Uint8Array(newSealedCek)));
}

async function importPublicKey(spkiB64) {
    const spki = base64Decode(spkiB64);
    return crypto.subtle.importKey(
        'spki', spki,
        { name: 'RSA-OAEP', hash: 'SHA-256' },
        false, ['encrypt']
    );
}

/**
 * Full re-key: generates new DEK, re-wraps all CEKs, re-encrypts all text
 * fields, seals new DEK for owner + members.
 */
export async function startRekey(oldDekHandle, { media, collections, galleries, members, ownerPublicKey }) {
    // 1. Generate new DEK (extractable so we can seal it)
    const newDek = await crypto.subtle.generateKey(
        { name: 'AES-GCM', length: 256 }, true, ['encrypt', 'decrypt']
    );

    // 2. Re-wrap all media CEKs + re-encrypt text fields
    const mediaWraps = [];
    for (const m of media) {
        const wrap = { media_id: m.id };
        wrap.cek_wrapped = await rewrapCek(m.cek_wrapped, oldDekHandle, newDek);
        if (m.encrypted_title) {
            const t = await reencryptField(m.encrypted_title, oldDekHandle, newDek);
            wrap.encrypted_title = t.ciphertext;
            wrap.title_iv = t.iv;
        }
        if (m.encrypted_caption) {
            const c = await reencryptField(m.encrypted_caption, oldDekHandle, newDek);
            wrap.encrypted_caption = c.ciphertext;
            wrap.caption_iv = c.iv;
        }
        mediaWraps.push(wrap);
    }

    // 3. Re-encrypt collection names/descriptions
    const collectionWraps = [];
    for (const c of collections) {
        const wrap = { collection_id: c.id };
        if (c.encrypted_name) {
            const n = await reencryptField(c.encrypted_name, oldDekHandle, newDek);
            wrap.encrypted_name = n.ciphertext;
            wrap.name_iv = n.iv;
        }
        if (c.encrypted_description) {
            const d = await reencryptField(c.encrypted_description, oldDekHandle, newDek);
            wrap.encrypted_description = d.ciphertext;
            wrap.description_iv = d.iv;
        }
        collectionWraps.push(wrap);
    }

    // 4. Re-encrypt gallery names/descriptions
    const galleryWraps = [];
    for (const g of galleries) {
        const wrap = { gallery_id: g.id };
        if (g.encrypted_name) {
            const n = await reencryptField(g.encrypted_name, oldDekHandle, newDek);
            wrap.encrypted_name = n.ciphertext;
            wrap.name_iv = n.iv;
        }
        if (g.encrypted_description) {
            const d = await reencryptField(g.encrypted_description, oldDekHandle, newDek);
            wrap.encrypted_description = d.ciphertext;
            wrap.description_iv = d.iv;
        }
        galleryWraps.push(wrap);
    }

    // 5. Seal new DEK for owner
    const rawNewDek = await crypto.subtle.exportKey('raw', newDek);
    const ownerPub = await importPublicKey(ownerPublicKey);
    const wrappedForOwner = await crypto.subtle.encrypt(
        { name: 'RSA-OAEP' }, ownerPub, rawNewDek
    );

    // 6. Seal new DEK for each member
    const memberWraps = [];
    for (const member of members) {
        const pub = await importPublicKey(member.public_key);
        const wrapped = await crypto.subtle.encrypt({ name: 'RSA-OAEP' }, pub, rawNewDek);
        memberWraps.push({ member_id: member.id, wrapped_dek: base64Encode(new Uint8Array(wrapped)) });
    }

    return {
        newDekHandle: newDek,
        new_wrapped_dek_for_owner: base64Encode(new Uint8Array(wrappedForOwner)),
        member_wraps: memberWraps,
        media_wraps: mediaWraps,
        collection_wraps: collectionWraps,
        gallery_wraps: galleryWraps,
    };
}
