<?php

namespace Tests\Unit\Services;

use App\Services\Storage\EncryptedBlobStorage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EncryptedBlobStorageTest extends TestCase
{
    private EncryptedBlobStorage $blobs;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->blobs = new EncryptedBlobStorage();
    }

    public function test_blob_path_format(): void
    {
        $path = $this->blobs->blobPath('ws-1', 'gal-1', 'med-1');
        $this->assertEquals('private/ws-1/gal-1/med-1.enc', $path);
    }

    public function test_thumb_path_format(): void
    {
        $path = $this->blobs->thumbPath('ws-1', 'gal-1', 'med-1');
        $this->assertEquals('private/ws-1/gal-1/med-1_thumb.enc', $path);
    }

    public function test_write_and_read(): void
    {
        $path = $this->blobs->blobPath('ws', 'gal', 'med');
        $this->blobs->write($path, 'ciphertext-bytes');

        $this->assertTrue($this->blobs->exists($path));
        $this->assertEquals('ciphertext-bytes', $this->blobs->read($path));
    }

    public function test_delete_removes_blob(): void
    {
        $path = $this->blobs->blobPath('ws', 'gal', 'med');
        $this->blobs->write($path, 'data');
        $this->blobs->delete($path);

        $this->assertFalse($this->blobs->exists($path));
    }

    public function test_delete_null_is_noop(): void
    {
        $this->blobs->delete(null);
        $this->assertTrue(true); // no exception
    }

    public function test_blobs_stored_under_private_dir(): void
    {
        $path = $this->blobs->blobPath('ws', 'gal', 'med');
        $this->assertStringStartsWith('private/', $path);
    }
}
