<?php

namespace Tests\Feature;

use App\Authentication\AuthenticatedClerkIdentity;
use App\Authentication\Clerk\ClerkUserGateway;
use App\Authentication\Clerk\ClerkUserSnapshot;
use App\Authentication\LocalUserProvisioner;
use App\Models\User;
use App\Support\RoleName;
use Database\Seeders\RbacSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use Tests\TestCase;
use Throwable;

class ConcurrentJitProvisioningTest extends TestCase
{
    public function test_concurrent_first_authenticated_requests_create_only_one_local_customer(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the process-level concurrency harness.');
        }

        if (! filter_var(env('RUN_MYSQL_CONCURRENCY_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('MySQL concurrency integration tests are disabled in the canonical suite.');
        }

        $database = 'furniture_jit_'.str_replace('-', '', (string) Str::uuid());
        $barrier = sys_get_temp_dir().'/furniture-jit-barrier-'.Str::uuid();
        $target = 'user_concurrency_test_001';
        $identity = new AuthenticatedClerkIdentity($target, 'sess_concurrency', 'https://clerk.test');

        try {
            $connection = config('database.connections.mysql');
            $server = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $connection['host'], $connection['port']),
                $connection['username'],
                $connection['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
            $server->exec('CREATE DATABASE `'.$database.'`');
            config(['database.connections.concurrency_mysql' => array_merge($connection, ['database' => $database])]);
            Artisan::call('migrate:fresh', ['--database' => 'concurrency_mysql', '--force' => true]);
            config(['database.default' => 'concurrency_mysql']);
            Artisan::call('db:seed', ['--class' => RbacSeeder::class, '--database' => 'concurrency_mysql', '--force' => true]);

            DB::purge('concurrency_mysql');
            config(['database.default' => 'concurrency_mysql']);
            $this->assertSame(0, User::on('concurrency_mysql')->where('clerk_user_id', $target)->count());
            DB::disconnect('concurrency_mysql');

            $children = [];
            for ($worker = 0; $worker < 2; $worker++) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid, 'Unable to fork concurrency worker.');

                if ($pid === 0) {
                    $this->runWorker($database, $barrier, $worker, $identity);
                }

                $children[] = $pid;
            }

            foreach ([0, 1] as $worker) {
                $this->waitFor($barrier.'.ready.'.$worker);
            }
            file_put_contents($barrier.'.go', 'go');

            $statuses = [];
            foreach ($children as $pid) {
                pcntl_waitpid($pid, $status);
                $this->assertTrue(pcntl_wifexited($status));
                $statuses[$pid] = pcntl_wexitstatus($status);
            }

            $results = array_map(
                fn (int $worker): array => json_decode((string) file_get_contents($barrier.'.result.'.$worker), true, flags: JSON_THROW_ON_ERROR),
                [0, 1],
            );

            foreach ($results as $index => $result) {
                $this->assertSame(0, $statuses[$children[$index]], json_encode($result, JSON_THROW_ON_ERROR));
            }

            $this->assertSame('success', $results[0]['status']);
            $this->assertSame('success', $results[1]['status']);
            $this->assertSame($results[0]['user_id'], $results[1]['user_id']);

            config(['database.default' => 'concurrency_mysql']);
            DB::purge('concurrency_mysql');
            $user = User::on('concurrency_mysql')->where('clerk_user_id', $target)->firstOrFail();

            $this->assertSame(1, User::on('concurrency_mysql')->where('clerk_user_id', $target)->count());
            $this->assertSame(1, $user->customerProfile()->count());
            $this->assertTrue($user->hasRole(RoleName::CUSTOMER->value));
            $this->assertFalse($user->hasRole(RoleName::STAFF->value));
            $this->assertFalse($user->hasRole(RoleName::ADMIN->value));
            $this->assertSame('concurrency@example.test', $user->email);
        } finally {
            DB::purge('concurrency_mysql');
            try {
                $server ??= null;
                $server?->exec('DROP DATABASE IF EXISTS `'.$database.'`');
            } catch (Throwable) {
            }
            foreach (glob($barrier.'.*') ?: [] as $file) {
                @unlink($file);
            }
        }
    }

    private function runWorker(string $database, string $barrier, int $worker, AuthenticatedClerkIdentity $identity): never
    {
        DB::purge('concurrency_mysql');
        config(['database.default' => 'concurrency_mysql']);
        DB::reconnect('concurrency_mysql');
        file_put_contents($barrier.'.ready.'.$worker, 'ready');
        $this->waitFor($barrier.'.go');

        try {
            $user = (new LocalUserProvisioner(new ConcurrentClerkUserGateway))->resolve($identity);
            file_put_contents($barrier.'.result.'.$worker, json_encode(['status' => 'success', 'user_id' => $user->getKey()], JSON_THROW_ON_ERROR));
            exit(0);
        } catch (Throwable $exception) {
            file_put_contents($barrier.'.result.'.$worker, json_encode([
                'status' => 'failure',
                'error' => $exception::class.': '.$exception->getMessage(),
            ], JSON_THROW_ON_ERROR));
            exit(1);
        }
    }

    private function waitFor(string $path): void
    {
        $deadline = microtime(true) + 10;
        while (! file_exists($path) && microtime(true) < $deadline) {
            usleep(1000);
        }

        $this->assertFileExists($path);
    }
}

final class ConcurrentClerkUserGateway implements ClerkUserGateway
{
    public function getById(string $clerkUserId): ClerkUserSnapshot
    {
        return new ClerkUserSnapshot($clerkUserId, 'concurrency@example.test', 'Concurrency Customer', null, true);
    }
}
