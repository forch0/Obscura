<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('gallery_id')->constrained('galleries')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('blob_path');                    // relative path in storage/app/private/
            $table->string('thumb_path')->nullable();       // encrypted thumbnail path
            $table->text('cek_wrapped');                    // CEK sealed with workspace DEK (base64)
            $table->string('iv');                           // AES-GCM IV for the blob (base64)
            $table->string('thumb_iv')->nullable();         // IV for thumbnail
            $table->string('mime_type');                    // plaintext for Content-Type on stream
            $table->unsignedBigInteger('size');             // ciphertext size (bytes)
            $table->text('encrypted_title')->nullable();    // title encrypted with workspace DEK
            $table->text('title_iv')->nullable();           // IV for encrypted title
            $table->text('encrypted_caption')->nullable();  // caption encrypted with workspace DEK
            $table->text('caption_iv')->nullable();         // IV for encrypted caption
            $table->timestamps();
            $table->index('gallery_id');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
