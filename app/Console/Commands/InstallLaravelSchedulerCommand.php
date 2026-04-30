<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class InstallLaravelSchedulerCommand extends Command
{
    protected $signature = 'system:install-scheduler
        {--driver=auto : auto|windows|cron}
        {--task-name=Hayat Travel Solutions Laravel Scheduler : Windows task name}
        {--force : Overwrite existing task/entry when possible}
        {--dry-run : Print actions without changing OS scheduler}';

    protected $description = 'Install OS-level scheduler to run `php artisan schedule:run` every minute.';

    public function handle(): int
    {
        $driver = strtolower((string) $this->option('driver'));
        if ($driver === 'auto') {
            $driver = match (PHP_OS_FAMILY) {
                'Windows' => 'windows',
                'Linux', 'Darwin' => 'cron',
                default => 'unknown',
            };
        }

        return match ($driver) {
            'windows' => $this->installWindowsTask(),
            'cron' => $this->installCronEntry(),
            default => $this->unsupportedDriver($driver),
        };
    }

    private function installWindowsTask(): int
    {
        $taskName = (string) $this->option('task-name');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $runnerPath = storage_path('framework/scheduler-runner.bat');
        $logPath = storage_path('logs/scheduler-task.log');
        $artisanPath = base_path('artisan');
        $basePath = base_path();
        $phpBinary = PHP_BINARY;

        if (! is_dir(dirname($runnerPath))) {
            mkdir(dirname($runnerPath), 0755, true);
        }
        if (! is_dir(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }

        $runnerBody = implode(PHP_EOL, [
            '@echo off',
            'cd /d "'.$basePath.'"',
            '"'.$phpBinary.'" "'.$artisanPath.'" schedule:run >> "'.$logPath.'" 2>&1',
            '',
        ]);

        if ($dryRun) {
            $this->line('Dry run: would write Windows runner at: '.$runnerPath);
            $this->line($runnerBody);
        } else {
            file_put_contents($runnerPath, $runnerBody);
        }

        $args = [
            'schtasks',
            '/Create',
            '/TN',
            $taskName,
            '/SC',
            'MINUTE',
            '/MO',
            '1',
            '/TR',
            $runnerPath,
        ];
        if ($force) {
            $args[] = '/F';
        }

        if ($dryRun) {
            $this->line('Dry run: would run -> '.implode(' ', $args));
            $this->info('Dry run complete.');

            return self::SUCCESS;
        }

        $process = new Process($args);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to create scheduled task.');
            $this->line(trim($process->getErrorOutput()) ?: trim($process->getOutput()));
            $this->line('Tip: run with --force if the task already exists.');

            return self::FAILURE;
        }

        $this->info('Windows scheduled task installed: '.$taskName);
        $this->line('Runs every minute via: '.$runnerPath);

        return self::SUCCESS;
    }

    private function installCronEntry(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $base = base_path();
        $php = PHP_BINARY;
        $line = sprintf(
            '* * * * * cd %s && %s artisan schedule:run >> /dev/null 2>&1',
            escapeshellarg($base),
            escapeshellarg($php)
        );

        $current = '';
        $list = new Process(['crontab', '-l']);
        $list->run();
        if ($list->isSuccessful()) {
            $current = rtrim($list->getOutput());
        }

        if (str_contains($current, 'artisan schedule:run') && ! $force) {
            $this->warn('Crontab already has a scheduler entry. Use --force to replace/append.');

            return self::SUCCESS;
        }

        $newContent = trim($current);
        $newContent = $newContent === '' ? $line : ($newContent.PHP_EOL.$line);
        $newContent .= PHP_EOL;

        if ($dryRun) {
            $this->line('Dry run: would install crontab content:');
            $this->line($newContent);
            $this->info('Dry run complete.');

            return self::SUCCESS;
        }

        $set = new Process(['crontab', '-']);
        $set->setInput($newContent);
        $set->run();

        if (! $set->isSuccessful()) {
            $this->error('Failed to update crontab.');
            $this->line(trim($set->getErrorOutput()) ?: trim($set->getOutput()));

            return self::FAILURE;
        }

        $this->info('Crontab scheduler entry installed.');
        $this->line($line);

        return self::SUCCESS;
    }

    private function unsupportedDriver(string $driver): int
    {
        $this->error('Unsupported scheduler driver: '.$driver);
        $this->line('Supported values: auto, windows, cron');

        return self::FAILURE;
    }
}

