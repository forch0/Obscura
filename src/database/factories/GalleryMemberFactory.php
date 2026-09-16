<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\GalleryMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class GalleryMemberFactory extends Factory
{
    protected $model = GalleryMember::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'user_id' => User::factory(),
            'role' => GalleryMember::ROLE_VIEWER,
        ];
    }

    public function editor(): static
    {
        return $this->state(fn () => ['role' => GalleryMember::ROLE_EDITOR]);
    }
}
