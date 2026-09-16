<?php

namespace Tests\Unit\Services;

use App\Models\Collection;
use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use App\Models\WorkspaceMember;
use App\Services\AccessCode\CodeGeneratorService;
use App\Services\Authorization\AuthorizationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationResolverTest extends TestCase
{
    use RefreshDatabase;

    private AuthorizationResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AuthorizationResolver();
    }

    public function test_super_admin_can_do_anything(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $workspace = Workspace::factory()->create();
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);

        $this->assertTrue($this->resolver->check($admin, $workspace, 'view'));
        $this->assertTrue($this->resolver->check($admin, $collection, 'update'));
        $this->assertTrue($this->resolver->check($admin, $gallery, 'delete'));
    }

    public function test_owner_can_do_anything(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);

        $this->assertTrue($this->resolver->check($owner, $workspace, 'view'));
        $this->assertTrue($this->resolver->check($owner, $collection, 'update'));
        $this->assertTrue($this->resolver->check($owner, $gallery, 'delete'));
    }

    public function test_workspace_member_can_view_collections(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'viewer']);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);

        $this->assertTrue($this->resolver->check($member, $collection, 'view'));
        $this->assertFalse($this->resolver->check($member, $collection, 'update'));
        $this->assertFalse($this->resolver->check($member, $collection, 'delete'));
    }

    public function test_private_gallery_denies_workspace_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'viewer']);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => 'private']);

        $this->assertFalse($this->resolver->check($member, $gallery, 'view'));
    }

    public function test_shared_gallery_allows_view_for_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'viewer']);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => 'shared']);

        $this->assertTrue($this->resolver->check($member, $gallery, 'view'));
        $this->assertFalse($this->resolver->check($member, $gallery, 'update'));
    }

    public function test_joint_gallery_editor_can_edit(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $editor->id, 'role' => 'editor']);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => 'joint']);
        GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $editor->id, 'role' => 'editor']);

        $this->assertTrue($this->resolver->check($editor, $gallery, 'view'));
        $this->assertTrue($this->resolver->check($editor, $gallery, 'update'));
        $this->assertTrue($this->resolver->check($editor, $gallery, 'upload'));
    }

    public function test_joint_gallery_viewer_cannot_edit(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $viewer->id, 'role' => 'viewer']);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id, 'type' => 'joint']);
        GalleryMember::create(['gallery_id' => $gallery->id, 'user_id' => $viewer->id, 'role' => 'viewer']);

        $this->assertTrue($this->resolver->check($viewer, $gallery, 'view'));
        $this->assertFalse($this->resolver->check($viewer, $gallery, 'update'));
        $this->assertFalse($this->resolver->check($viewer, $gallery, 'upload'));
    }

    public function test_stranger_denied(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);

        $this->assertFalse($this->resolver->check($stranger, $collection, 'view'));
        $this->assertFalse($this->resolver->check($stranger, $gallery, 'view'));
    }

    public function test_workspace_scoped_code_can_view(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);

        $codes = app(CodeGeneratorService::class)->generate();
        $code = WorkspaceAccessCode::create([
            'workspace_id' => $workspace->id,
            'scope' => 'workspace',
            'permissions' => 1, // view only
            'code_hash' => $codes['hash'],
            'code_salt' => $codes['salt'],
            'code_prefix' => $codes['prefix'],
            'expires_at' => now()->addHour(),
            'created_by' => $owner->id,
        ]);

        request()->merge(['access_code' => $code]);

        $this->assertTrue($this->resolver->check(User::factory()->create(), $collection, 'view'));
        $this->assertTrue($this->resolver->check(User::factory()->create(), $gallery, 'view'));
        $this->assertFalse($this->resolver->check(User::factory()->create(), $gallery, 'upload'));
    }

    public function test_collection_scoped_code_covers_galleries(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);
        $otherCollection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $otherGallery = Gallery::factory()->create(['collection_id' => $otherCollection->id]);

        $codes = app(CodeGeneratorService::class)->generate();
        $code = WorkspaceAccessCode::create([
            'workspace_id' => $workspace->id,
            'scope' => 'collection',
            'scope_id' => $collection->id,
            'permissions' => 1,
            'code_hash' => $codes['hash'],
            'code_salt' => $codes['salt'],
            'code_prefix' => $codes['prefix'],
            'expires_at' => now()->addHour(),
            'created_by' => $owner->id,
        ]);

        request()->merge(['access_code' => $code]);

        $this->assertTrue($this->resolver->check(User::factory()->create(), $collection, 'view'));
        $this->assertTrue($this->resolver->check(User::factory()->create(), $gallery, 'view'));
        $this->assertFalse($this->resolver->check(User::factory()->create(), $otherCollection, 'view'));
        $this->assertFalse($this->resolver->check(User::factory()->create(), $otherGallery, 'view'));
    }

    public function test_gallery_scoped_code_covers_only_that_gallery(): void
    {
        $owner = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $collection = Collection::factory()->create(['workspace_id' => $workspace->id]);
        $gallery = Gallery::factory()->create(['collection_id' => $collection->id]);
        $otherGallery = Gallery::factory()->create(['collection_id' => $collection->id]);

        $codes = app(CodeGeneratorService::class)->generate();
        $code = WorkspaceAccessCode::create([
            'workspace_id' => $workspace->id,
            'scope' => 'gallery',
            'scope_id' => $gallery->id,
            'permissions' => 1,
            'code_hash' => $codes['hash'],
            'code_salt' => $codes['salt'],
            'code_prefix' => $codes['prefix'],
            'expires_at' => now()->addHour(),
            'created_by' => $owner->id,
        ]);

        request()->merge(['access_code' => $code]);

        $this->assertTrue($this->resolver->check(User::factory()->create(), $gallery, 'view'));
        $this->assertFalse($this->resolver->check(User::factory()->create(), $otherGallery, 'view'));
    }
}
