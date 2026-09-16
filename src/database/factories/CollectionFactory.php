<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

class CollectionFactory extends Factory
{
    protected $model = Collection::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'encrypted_name' => base64_encode(random_bytes(32)),
            'name_iv' => base64_encode(random_bytes(12)),
            'encrypted_description' => base64_encode(random_bytes(64)),
            'description_iv' => base64_encode(random_bytes(12)),
        ];
    }
}
