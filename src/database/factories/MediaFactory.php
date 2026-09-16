<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'uploaded_by' => User::factory(),
            'blob_path' => 'private/' . fake()->uuid() . '/' . fake()->uuid() . '/' . fake()->uuid() . '.enc',
            'thumb_path' => null,
            'cek_wrapped' => base64_encode(random_bytes(44)),
            'iv' => base64_encode(random_bytes(12)),
            'thumb_iv' => null,
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(1024, 5242880),
            'encrypted_title' => base64_encode(random_bytes(32)),
            'title_iv' => base64_encode(random_bytes(12)),
            'encrypted_caption' => null,
            'caption_iv' => null,
        ];
    }

    public function withThumbnail(): static
    {
        return $this->state(fn () => [
            'thumb_path' => 'private/' . fake()->uuid() . '/' . fake()->uuid() . '/' . fake()->uuid() . '_thumb.enc',
            'thumb_iv' => base64_encode(random_bytes(12)),
        ]);
    }
}
