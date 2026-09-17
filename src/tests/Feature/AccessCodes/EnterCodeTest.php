<?php

namespace Tests\Feature\AccessCodes;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use App\Services\AccessCode\CodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterCodeTest extends TestCase
{
    use RefreshDatabase;

    private string $rawCode;

    private function createCode(array $overrides = []): WorkspaceAccessCode
    {
        $owner = User::factory()->withKeypair()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $codes = app(CodeGeneratorService::class);
        $generated = $codes->generate();
        $this->rawCode = $generated['raw'];

        return WorkspaceAccessCode::create(array_merge([
            'workspace_id' => $workspace->id,
            'scope' => 'workspace',
            'permissions' => 1,
            'code_hash' => $generated['hash'],
            'code_salt' => $generated['salt'],
            'code_prefix' => $generated['prefix'],
            'wrapped_dek' => base64_encode(random_bytes(300)),
            'expires_at' => now()->addHour(),
            'created_by' => $owner->id,
        ], $overrides));
    }

    public function test_enter_screen_renders(): void
    {
        $response = $this->get(route('enter'));

        $response->assertStatus(200);
        $response->assertSee('Access Code');
    }

    public function test_valid_code_returns_scoped_session(): void
    {
        $code = $this->createCode();

        $response = $this->post(route('enter'), ['code' => $this->rawCode]);

        $response->assertRedirect(route('access.view'));
        $response->assertCookie('access_code_session');
    }

    public function test_invalid_code_rejected(): void
    {
        $response = $this->post(route('enter'), ['code' => 'FAKE-CODE-HERE']);

        $response->assertSessionHasErrors('code');
    }

    public function test_expired_code_rejected(): void
    {
        $code = $this->createCode(['expires_at' => now()->subHour()]);

        $response = $this->post(route('enter'), ['code' => $this->rawCode]);

        $response->assertSessionHasErrors('code');
    }

    public function test_revoked_code_rejected(): void
    {
        $code = $this->createCode();
        $code->revoke();

        $response = $this->post(route('enter'), ['code' => $this->rawCode]);

        $response->assertSessionHasErrors('code');
    }

    public function test_max_uses_enforced(): void
    {
        $code = $this->createCode(['max_uses' => 1]);

        // First use succeeds
        $response = $this->post(route('enter'), ['code' => $this->rawCode]);
        $response->assertRedirect(route('access.view'));

        // Second use fails
        $response = $this->post(route('enter'), ['code' => $this->rawCode]);
        $response->assertSessionHasErrors('code');
    }

    public function test_use_count_increments(): void
    {
        $code = $this->createCode();

        $this->post(route('enter'), ['code' => $this->rawCode]);

        $code->refresh();
        $this->assertEquals(1, $code->use_count);
    }

    public function test_access_view_requires_session(): void
    {
        $this->get(route('access.view'))
            ->assertRedirect(route('enter'));
    }

    public function test_access_view_renders_with_valid_session(): void
    {
        $code = $this->createCode();
        $sessions = app(\App\Services\AccessCode\CodeSessionService::class);
        $payload = $sessions->issue($code, $this->rawCode);

        $this->withCookie($sessions->cookieName(), $sessions->encode($payload))
            ->get(route('access.view'))
            ->assertStatus(200);
    }

    public function test_out_of_scope_media_is_forbidden(): void
    {
        $code = $this->createCode(['scope' => 'gallery', 'scope_id' => 'other-gallery-id']);
        $sessions = app(\App\Services\AccessCode\CodeSessionService::class);
        $payload = $sessions->issue($code, $this->rawCode);

        $otherGallery = \App\Models\Gallery::factory()->create();
        $media = \App\Models\Media::factory()->create(['gallery_id' => $otherGallery->id]);

        $this->withCookie($sessions->cookieName(), $sessions->encode($payload))
            ->get("/access/media/{$media->id}/blob")
            ->assertStatus(403);
    }
}
