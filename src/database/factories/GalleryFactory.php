<?php

namespace Database\Factories;

use App\Models\Collection;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryFactory extends Factory
{
    protected $model = Gallery::class;

    public function definition(): array
    {
        return [
            'collection_id' => Collection::factory(),
            'encrypted_name' => base64_encode(random_bytes(32)),
            'name_iv' => base64_encode(random_bytes(12)),
            'type' => Gallery::TYPE_PRIVATE,
            'encrypted_description' => base64_encode(random_bytes(64)),
            'description_iv' => base64_encode(random_bytes(12)),
        ];
    }

    public function shared(): static
    {
        return $this->state(fn () => ['type' => Gallery::TYPE_SHARED]);
    }

    public function joint(): static
    {
        return $this->state(fn () => ['type' => Gallery::TYPE_JOINT]);
    }
}
