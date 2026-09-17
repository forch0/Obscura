<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class PromoteSuperAdmin extends Command
{
    protected $signature = 'admin:promote {email}';
    protected $description = 'Promote a user to Super Admin';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("No user found with email: {$email}");
            return self::FAILURE;
        }

        $user->update(['is_super_admin' => true]);

        $this->info("{$email} is now a Super Admin.");

        return self::SUCCESS;
    }
}
