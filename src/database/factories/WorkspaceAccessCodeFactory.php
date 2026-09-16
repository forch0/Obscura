<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceAccessCode;
use App\Services\AccessCode\CodeGeneratorService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WorkspaceAccessCode>
 */
class WorkspaceAccessCodeFactory extends Factory
{
    protected $model = WorkspaceAccessCode::class;

    public function definition(): array
    {
        $codes = app(CodeGeneratorService::class)->generate();

        return [
            'workspace_id' => Workspace::factory(),
            'scope' => WorkspaceAccessCode::SCOPE_WORKSPACE,
            'scope_id' => null,
            'permissions' => WorkspaceAccessCode::PERMISSION_VIEW,
            'code_hash' => $codes['hash'],
            'code_salt' => $codes['salt'],
            'code_prefix' => $codes['prefix'],
            'wrapped_dek' => base64_encode(random_bytes(300)),
            'expires_at' => now()->addDay(),
            'revoked_at' => null,
            'max_uses' => 0,
            'use_count' => 0,
            'created_by' => User::factory(),
            'label' => fake()->optional()->words(2, true),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subHour()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function oneUse(): static
    {
        return $this->state(fn () => ['max_uses' => 1]);
    }

    public function withPermissions(int $permissions): static
    {
        return $this->state(fn () => ['permissions' => $permissions]);
    }

    public function withScope(string $scope, ?string $scopeId = null): static
    {
        return $this->state(fn () => ['scope' => $scope, 'scope_id' => $scopeId]);
    }
}
