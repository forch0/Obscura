<?php

namespace Tests\Unit\Services;

use App\Models\RekeyJob;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Rekey\RekeyLockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekeyLockServiceTest extends TestCase
{
    use RefreshDatabase;

    private RekeyLockService $locks;

    protected function setUp(): void
    {
        parent::setUp();
        $this->locks = new RekeyLockService();
    }

    public function test_acquire_returns_true(): void
    {
        $this->assertTrue($this->locks->acquire('ws-1'));
        $this->locks->release('ws-1');
    }

    public function test_double_acquire_returns_false(): void
    {
        $this->locks->acquire('ws-1');
        $this->assertFalse($this->locks->acquire('ws-1'));
        $this->locks->release('ws-1');
    }

    public function test_release_allows_reacquire(): void
    {
        $this->locks->acquire('ws-1');
        $this->locks->release('ws-1');
        $this->assertTrue($this->locks->acquire('ws-1'));
        $this->locks->release('ws-1');
    }

    public function test_different_workspaces_dont_block(): void
    {
        $this->locks->acquire('ws-1');
        $this->assertTrue($this->locks->acquire('ws-2'));
        $this->locks->release('ws-1');
        $this->locks->release('ws-2');
    }

    public function test_is_locked(): void
    {
        $this->assertFalse($this->locks->isLocked('ws-1'));
        $this->locks->acquire('ws-1');
        $this->assertTrue($this->locks->isLocked('ws-1'));
        $this->locks->release('ws-1');
        $this->assertFalse($this->locks->isLocked('ws-1'));
    }
}
