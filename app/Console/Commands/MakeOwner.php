<?php

namespace App\Console\Commands;

use App\Services\AuthService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Create the one owner account. Refuses once an admin exists, and never
 * seeds a default password — that is how the previous build leaked
 * 'ricetrading123' into the repo (invariant 4).
 */
class MakeOwner extends Command
{
    protected $signature = 'mavies:make-owner
                            {--name= : Owner full name}
                            {--email= : Owner email}
                            {--password= : Owner password (omit to be prompted securely)}';

    protected $description = 'Create the owner admin account (only runs when no admin exists).';

    public function handle(AuthService $auth): int
    {
        if ($auth->ownerExists()) {
            $this->error('An owner already exists. Use the Staff screen to add more.');
            return self::FAILURE;
        }

        $name = $this->option('name') ?: $this->ask('Owner full name');
        $email = $this->option('email') ?: $this->ask('Owner email');
        $password = $this->option('password') ?: $this->secret('Owner password (min 8 chars, a letter and a number)');

        try {
            $admin = $auth->createOwner($name, $email, $password);
        } catch (Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info("Owner created: {$admin->admin_name} <{$admin->email}>");

        return self::SUCCESS;
    }
}
