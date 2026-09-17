<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_access_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('scope');                        // workspace | collection | gallery
            $table->uuid('scope_id')->nullable();           // null when scope=workspace
            $table->unsignedSmallInteger('permissions');    // bitmask: view=1, upload=2, comment=4
            $table->string('code_hash');                    // Argon2id hash of raw code
            $table->string('code_salt');                    // base64, used for hash + key derivation
            $table->string('code_prefix', 8)->index();      // first 8 chars of hash for lookup optimization
            $table->text('wrapped_dek')->nullable();        // DEK sealed with code-derived key
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('max_uses')->default(0);
            $table->unsignedInteger('use_count')->default(0);
            $table->foreignUuid('created_by')->constrained('users');
            $table->string('label')->nullable();
            $table->timestamps();

            $table->index(['scope', 'scope_id']);
            $table->index(['workspace_id', 'revoked_at']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_access_codes');
    }
};
