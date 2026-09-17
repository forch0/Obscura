<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->text('encrypted_name');
            $table->text('name_iv');
            $table->text('wrapped_dek_for_owner')->nullable();
            $table->text('wrapped_dek_iv')->nullable();
            $table->unsignedInteger('dek_version')->default(1);
            $table->timestamp('rekeyed_at')->nullable();
            $table->timestamps();
            $table->index('owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
