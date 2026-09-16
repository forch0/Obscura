<?php

namespace Tests\Feature\Media;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\Media;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaTest extends TestCase
{
    use RefreshDatabase;

    private function makeGallery(string $type = 'private'): array
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => $type]);

        return compact('owner', 'workspace', 'collection', 'gallery');
    }

    private function uploadPayload(Gallery $gallery): array
    {
        return [
            'ciphertext' => UploadedFile::fake()->createWithContent('blob.enc', random_bytes(1024)),
            'cek_wrapped' => base64_encode(random_bytes(44)),
            'iv' => base64_encode(random_bytes(12)),
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'encrypted_title' => base64_encode(random_bytes(32)),
            'title_iv' => base64_encode(random_bytes(12)),
        ];
    }

    public function test_owner_can_upload_media(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'gallery' => $gallery] = $this->makeGallery();

        $response = $this->actingAs($owner)->postJson(
            route('media.store', $gallery),
            $this->uploadPayload($gallery)
        );

        $response->assertStatus(201);
        $response->assertJsonStructure(['media_id']);
        $this->assertDatabaseHas('media', ['gallery_id' => $gallery->id]);
    }

    public function test_server_stores_only_ciphertext(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'gallery' => $gallery] = $this->makeGallery();

        $this->actingAs($owner)->postJson(route('media.store', $gallery), $this->uploadPayload($gallery));

        $media = Media::first();
        $this->assertNotNull($media->cek_wrapped);
        $this->assertNotNull($media->iv);
        // blob_path points to .enc file — ciphertext only
        $this->assertStringEndsWith('.enc', $media->blob_path);
    }

    public function test_upload_logs_audit(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'gallery' => $gallery] = $this->makeGallery();

        $this->actingAs($owner)->postJson(route('media.store', $gallery), $this->uploadPayload($gallery));

        $this->assertDatabaseHas('audit_logs', ['action' => 'media.uploaded']);
    }

    public function test_super_admin_cannot_upload(): void
    {
        Storage::fake('local');
        ['gallery' => $gallery] = $this->makeGallery();
        $admin = User::factory()->superAdmin()->withKeypair()->create();

        $response = $this->actingAs($admin)->postJson(route('media.store', $gallery), $this->uploadPayload($gallery));

        $response->assertStatus(403);
    }

    public function test_super_admin_cannot_view_media(): void
    {
        ['owner' => $owner, 'gallery' => $gallery] = $this->makeGallery();
        $media = Media::factory()->create(['gallery_id' => $gallery->id, 'uploaded_by' => $owner->id]);
        $admin = User::factory()->superAdmin()->withKeypair()->create();

        $response = $this->actingAs($admin)->get(route('media.blob', $media));

        $response->assertStatus(403);
    }

    public function test_joint_gallery_editor_can_upload(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'workspace' => $workspace, 'gallery' => $gallery] = $this->makeGallery('joint');
        $editor = User::factory()->withKeypair()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $editor->id, 'role' => 'editor']);
        GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $editor->id, 'role' => 'editor']);

        $response = $this->actingAs($editor)->postJson(route('media.store', $gallery), $this->uploadPayload($gallery));

        $response->assertStatus(201);
    }

    public function test_viewer_cannot_upload(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'workspace' => $workspace, 'gallery' => $gallery] = $this->makeGallery('joint');
        $viewer = User::factory()->withKeypair()->create();
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $viewer->id, 'role' => 'viewer']);

        $response = $this->actingAs($viewer)->postJson(route('media.store', $gallery), $this->uploadPayload($gallery));

        $response->assertStatus(403);
    }

    public function test_owner_can_delete_media(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'workspace' => $workspace, 'gallery' => $gallery] = $this->makeGallery();
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'uploaded_by' => $owner->id,
            'blob_path' => "private/{$workspace->id}/{$gallery->id}/test.enc",
        ]);
        Storage::disk('local')->put($media->blob_path, random_bytes(512));

        $response = $this->actingAs($owner)->deleteJson(route('media.destroy', $media));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('local')->assertMissing($media->blob_path);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.deleted']);
    }

    public function test_media_index_returns_metadata_only(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'gallery' => $gallery] = $this->makeGallery();
        Media::factory()->create(['gallery_id' => $gallery->id, 'uploaded_by' => $owner->id]);

        $response = $this->actingAs($owner)->getJson(route('media.index', $gallery));

        $response->assertStatus(200);
        $media = $response->json('media.0');
        $this->assertArrayHasKey('encrypted_title', $media);
        $this->assertArrayHasKey('mime_type', $media);
        $this->assertArrayNotHasKey('cek_wrapped', $media); // hidden from listing
        $this->assertArrayNotHasKey('blob_path', $media);
    }

    public function test_blob_streams_ciphertext_with_headers(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'workspace' => $workspace, 'gallery' => $gallery] = $this->makeGallery();
        $path = "private/{$workspace->id}/{$gallery->id}/test.enc";
        Storage::disk('local')->put($path, random_bytes(256));

        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'uploaded_by' => $owner->id,
            'blob_path' => $path,
        ]);

        $response = $this->actingAs($owner)->get(route('media.blob', $media));

        $response->assertStatus(200);
        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
        $this->assertNotNull($response->headers->get('X-Cek-Wrapped'));
        $this->assertNotNull($response->headers->get('X-Blob-Iv'));
    }

    public function test_blob_missing_file_returns_404(): void
    {
        Storage::fake('local');
        ['owner' => $owner, 'gallery' => $gallery] = $this->makeGallery();
        $media = Media::factory()->create([
            'gallery_id' => $gallery->id,
            'uploaded_by' => $owner->id,
            'blob_path' => 'private/nonexistent/file.enc',
        ]);

        $response = $this->actingAs($owner)->get(route('media.blob', $media));

        $response->assertStatus(404);
    }

    public function test_owner_can_rename_media(): void
    {
        ['owner' => $owner, 'gallery' => $gallery] = $this->makeGallery();
        $media = Media::factory()->create(['gallery_id' => $gallery->id, 'uploaded_by' => $owner->id]);

        $response = $this->actingAs($owner)->putJson(route('media.update', $media), [
            'encrypted_title' => base64_encode('new-title'),
            'title_iv' => base64_encode(random_bytes(12)),
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('audit_logs', ['action' => 'media.renamed']);
    }
}
