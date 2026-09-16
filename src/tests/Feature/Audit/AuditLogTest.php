<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Audit\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_records_actor_and_subject(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $logger = app(AuditLogger::class);
        $logger->log($user, $workspace, 'workspace.created', ['source' => 'test']);

        $log = AuditLog::first();
        $this->assertNotNull($log);
        $this->assertEquals($user::class, $log->actor_type);
        $this->assertEquals($user->id, $log->actor_id);
        $this->assertEquals($workspace::class, $log->subject_type);
        $this->assertEquals($workspace->id, $log->subject_id);
        $this->assertEquals('workspace.created', $log->action);
        $this->assertEquals(['source' => 'test'], $log->context);
    }

    public function test_audit_log_records_ip_address(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        $logger = app(AuditLogger::class);
        $logger->log($user, $workspace, 'workspace.viewed');

        $log = AuditLog::first();
        $this->assertNotNull($log->ip_address);
    }

    public function test_audit_log_id_is_uuid(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        app(AuditLogger::class)->log($user, $workspace, 'test.action');

        $log = AuditLog::first();
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $log->id
        );
    }

    public function test_audit_log_context_defaults_to_empty_array(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        app(AuditLogger::class)->log($user, $workspace, 'test.nocontext');

        $log = AuditLog::first();
        $this->assertIsArray($log->context);
        $this->assertEmpty($log->context);
    }

    public function test_audit_log_actor_relation_works(): void
    {
        $user = User::factory()->create();
        $workspace = Workspace::factory()->create(['owner_id' => $user->id]);

        app(AuditLogger::class)->log($user, $workspace, 'test.relations');

        $log = AuditLog::first();
        $this->assertEquals($user->id, $log->actor->id);
        $this->assertEquals($workspace->id, $log->subject->id);
    }
}
