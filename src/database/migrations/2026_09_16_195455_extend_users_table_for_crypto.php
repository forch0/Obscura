<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('public_key')->nullable()->after('is_super_admin');
            $table->text('encrypted_private_key')->nullable()->after('public_key');
            $table->text('encrypted_private_key_recovery')->nullable()->after('encrypted_private_key');
            $table->string('recovery_code_hash')->nullable()->after('encrypted_private_key_recovery');
            $table->string('recovery_code_salt')->nullable()->after('recovery_code_hash');
            $table->timestamp('recovery_code_used_at')->nullable()->after('recovery_code_salt');
            $table->string('keypair_salt')->nullable()->after('recovery_code_used_at');
            $table->timestamp('keypair_created_at')->nullable()->after('keypair_salt');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('is_super_admin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_super_admin']);
            $table->dropColumn([
                'public_key',
                'encrypted_private_key',
                'encrypted_private_key_recovery',
                'recovery_code_hash',
                'recovery_code_salt',
                'recovery_code_used_at',
                'keypair_salt',
                'keypair_created_at',
            ]);
        });
    }
};
