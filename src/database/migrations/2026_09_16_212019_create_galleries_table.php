<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('galleries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('collection_id')->constrained('collections')->cascadeOnDelete();
            $table->text('encrypted_name');
            $table->text('name_iv');
            $table->enum('type', ['private', 'shared', 'joint'])->default('private');
            $table->text('encrypted_description')->nullable();
            $table->text('description_iv')->nullable();
            $table->timestamps();
            $table->index(['collection_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('galleries');
    }
};
