<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->text('encrypted_name');
            $table->text('name_iv');
            $table->text('encrypted_description')->nullable();
            $table->text('description_iv')->nullable();
            $table->timestamps();
            $table->index('workspace_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
