<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

trait RunsConcurrentWorkers
{
    private const RESULT_FILE_PREFIX = '/result-';

    abstract protected function databaseConnectionName(): string;

    /** @param callable(): string ...$workers */
    /** @return list<string> */
    protected function runConcurrentWorkers(callable ...$workers): array
    {
        $barrier = sys_get_temp_dir().'/concurrent-workers-'.bin2hex(random_bytes(8));
        mkdir($barrier, 0777, true);
        $pids = [];

        try {
            foreach ($workers as $index => $worker) {
                $pid = pcntl_fork();
                $this->assertNotSame(-1, $pid);

                if ($pid === 0) {
                    exit($this->runConcurrentWorker($barrier, $index, $worker));
                }

                $pids[] = $pid;
            }

            $this->awaitConcurrentWorkersReady($barrier, count($workers));
            touch($barrier.'/go');
            $this->reapConcurrentWorkers($pids, $barrier);

            $results = [];
            foreach (array_keys($workers) as $index) {
                $results[] = $this->concurrentWorkerResult($barrier, $index);
            }

            return $results;
        } finally {
            $this->terminateConcurrentWorkers($pids);
            $this->destroyConcurrentBarrier($barrier);
            DB::purge($this->databaseConnectionName());
        }
    }

    private function runConcurrentWorker(string $barrier, int $index, callable $worker): int
    {
        try {
            DB::purge($this->databaseConnectionName());
            DB::connection($this->databaseConnectionName())->selectOne('select 1');
            touch($barrier.'/ready-'.$index);
            $this->awaitConcurrentBarrier($barrier);
            file_put_contents($barrier.self::RESULT_FILE_PREFIX.$index, (string) $worker());

            return 0;
        } catch (\Throwable $exception) {
            @file_put_contents($barrier.self::RESULT_FILE_PREFIX.$index, 'crash:'.$exception::class.':'.$exception->getMessage());

            return 1;
        }
    }

    /** @param list<int> $pids */
    private function reapConcurrentWorkers(array $pids, string $barrier): void
    {
        $deadline = microtime(true) + 30;

        foreach ($pids as $index => $pid) {
            $status = 0;
            $result = pcntl_waitpid($pid, $status, WNOHANG);

            while ($result === 0 && microtime(true) <= $deadline) {
                usleep(5_000);
                $result = pcntl_waitpid($pid, $status, WNOHANG);
            }

            if ($result === 0) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
                $this->fail('Concurrency worker did not complete in time.');
            }

            $this->assertTrue(
                pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0,
                'Concurrency worker failed: '.$this->concurrentWorkerResult($barrier, $index),
            );
        }
    }

    private function concurrentWorkerResult(string $barrier, int $index): string
    {
        $file = $barrier.self::RESULT_FILE_PREFIX.$index;

        return is_file($file) ? (string) file_get_contents($file) : '';
    }

    /** @param list<int> $pids */
    private function terminateConcurrentWorkers(array $pids): void
    {
        foreach ($pids as $pid) {
            $status = 0;

            if (pcntl_waitpid($pid, $status, WNOHANG) === 0) {
                posix_kill($pid, SIGKILL);
                pcntl_waitpid($pid, $status);
            }
        }
    }

    private function destroyConcurrentBarrier(string $barrier): void
    {
        foreach (glob($barrier.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($barrier);
    }

    private function awaitConcurrentBarrier(string $barrier): void
    {
        $deadline = microtime(true) + 10;

        while (! is_file($barrier.'/go')) {
            if (microtime(true) > $deadline) {
                throw new ConcurrentWorkerTimeout('Barrier timeout.');
            }

            usleep(200);
        }
    }

    private function awaitConcurrentWorkersReady(string $barrier, int $count): void
    {
        $deadline = microtime(true) + 10;

        while (count(glob($barrier.'/ready-*') ?: []) < $count) {
            $crashes = glob($barrier.self::RESULT_FILE_PREFIX.'*') ?: [];
            if ($crashes !== []) {
                $result = (string) file_get_contents($crashes[0]);

                throw new ConcurrentWorkerTimeout('Worker failed before ready: '.$result);
            }

            if (microtime(true) > $deadline) {
                throw new ConcurrentWorkerTimeout('Workers did not become ready in time.');
            }

            usleep(200);
        }
    }
}
