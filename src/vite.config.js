import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/crypto/keypair.js',
                'resources/js/crypto/pbkdf2.js',
                'resources/js/crypto/session.js',
                'resources/js/crypto/recovery.js',
                'resources/js/crypto/dek.js',
                'resources/js/crypto/workspace-session.js',
                'resources/js/crypto/code-key.js',
            ],
            refresh: true,
        }),
    ],
    build: {
        rollupOptions: {
            preserveEntrySignatures: 'strict',
            output: {
                entryFileNames: 'assets/[name]-[hash].js',
                chunkFileNames: 'assets/[name]-[hash].js',
            },
        },
    },
});
