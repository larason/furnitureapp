<?php

namespace App\Console\Commands;

use App\Authentication\BootstrapInitialAdmin;
use App\Exceptions\InitialAdminBootstrapException;
use Illuminate\Console\Command;
use Throwable;

final class BootstrapInitialAdminCommand extends Command
{
    protected $signature = 'admin:bootstrap
        {clerk-user-id : Verified Clerk user ID for the initial Admin}
        {--confirm : Explicitly confirm non-interactive initial Admin bootstrap}';

    protected $description = 'Bootstrap the one permitted initial Admin from a verified Clerk identity';

    public function handle(BootstrapInitialAdmin $bootstrap): int
    {
        $clerkUserId = trim((string) $this->argument('clerk-user-id'));
        $this->line("Target Clerk identity: {$clerkUserId}");

        if (! $this->option('confirm') && ! $this->confirm('Bootstrap this identity as the initial Admin?')) {
            $this->warn('Initial Admin bootstrap cancelled.');

            return self::FAILURE;
        }

        try {
            $bootstrap->bootstrap($clerkUserId);
        } catch (InitialAdminBootstrapException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable) {
            $this->error('Initial Admin bootstrap failed.');

            return self::FAILURE;
        }

        $this->info('Initial Admin bootstrap completed.');

        return self::SUCCESS;
    }
}
