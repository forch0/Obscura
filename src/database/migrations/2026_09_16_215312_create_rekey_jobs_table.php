<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekey_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignUuid('initiated_by')->constrained('users');
            $table->unsignedInteger('total_media')->default(0);
            $table->unsignedInteger('processed_media')->default(0);
            $table->string('status')->default('pending'); // pending | in_progress | completed | failed
            $table->json('member_wraps')->nullable();
            $table->json('media_wraps')->nullable();
            $table->json('collection_wraps')->nullable();
            $table->json('gallery_wraps')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekey_jobs');
    }
};
