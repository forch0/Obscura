<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EncryptedBlobStorage
{
    private const DISK = 'local';
    private const BASE_DIR = 'private';

    /**
     * Build the relative storage path for a media blob.
     * Format: private/{workspace_id}/{gallery_id}/{media_id}.enc
     */
    public function blobPath(string $workspaceId, string $galleryId, string $mediaId): string
    {
        return self::BASE_DIR . "/{$workspaceId}/{$galleryId}/{$mediaId}.enc";
    }

    /**
     * Build the relative storage path for a thumbnail blob.
     */
    public function thumbPath(string $workspaceId, string $galleryId, string $mediaId): string
    {
        return self::BASE_DIR . "/{$workspaceId}/{$galleryId}/{$mediaId}_thumb.enc";
    }

    /**
     * Write ciphertext to the private disk.
     */
    public function write(string $path, $contents): bool
    {
        return Storage::disk(self::DISK)->put($path, $contents);
    }

    /**
     * Check if a blob exists.
     */
    public function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path);
    }

    /**
     * Get the absolute path (for streamed responses).
     */
    public function absolutePath(string $path): string
    {
        return Storage::disk(self::DISK)->path($path);
    }

    /**
     * Read blob contents (small files / tests only — prefer stream for large).
     */
    public function read(string $path): string
    {
        return Storage::disk(self::DISK)->get($path);
    }

    /**
     * Stream a blob as a download response (ciphertext — application/octet-stream).
     */
    public function stream(string $path): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($path, null, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'no-store',
        ]);
    }

    /**
     * Delete a blob if it exists.
     */
    public function delete(?string $path): void
    {
        if ($path && Storage::disk(self::DISK)->exists($path)) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
