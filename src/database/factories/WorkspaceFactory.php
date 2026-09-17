<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Workspace>
 */
class WorkspaceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'encrypted_name' => base64_encode(random_bytes(32)),
            'name_iv' => base64_encode(random_bytes(12)),
            'wrapped_dek_for_owner' => base64_encode(random_bytes(256)),
            'wrapped_dek_iv' => base64_encode(random_bytes(12)),
            'dek_version' => 1,
        ];
    }

    public function withoutDek(): static
    {
        return $this->state(fn (array $attributes) => [
            'wrapped_dek_for_owner' => null,
            'wrapped_dek_iv' => null,
        ]);
    }
}
