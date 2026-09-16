// resources/js/crypto/session.js

let privateKeyHandle = null;

export function setPrivateKeyHandle(key) {
    privateKeyHandle = key;
}

export function getPrivateKeyHandle() {
    return privateKeyHandle;
}

export function clearPrivateKeyHandle() {
    privateKeyHandle = null;
}

export function hasPrivateKey() {
    return privateKeyHandle !== null;
}
