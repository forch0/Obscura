<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('viewer'); // editor | viewer
            $table->timestamps();
            $table->unique(['gallery_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_members');
    }
};
