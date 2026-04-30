<?php

use App\Automation\Jobs\DispatchInquiryFollowUpReminderJob;
use App\Models\BackupRun;
use App\Models\InquiryFollowUp;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Artisan::command('automation:reminders:run', function () {
    $lookAheadMinutes = max(1, (int) config('automation.reminder_lookahead_minutes', 30));
    $cutoff = now()->addMinutes($lookAheadMinutes);

    $followUps = InquiryFollowUp::query()
        ->whereNull('completed_at')
        ->whereNull('reminder_sent_at')
        ->whereNotNull('due_at')
        ->where('due_at', '<=', $cutoff)
        ->limit(250)
        ->pluck('id');

    foreach ($followUps as $followUpId) {
        DispatchInquiryFollowUpReminderJob::dispatch((int) $followUpId);
    }

    $this->info(sprintf('Queued %d reminder job(s).', $followUps->count()));
})->purpose('Queue reminders for due inquiry follow-ups');

Artisan::command('safety:backup:run {--disk=local} {--type=database}', function () {
    $disk = (string) $this->option('disk');
    $type = (string) $this->option('type');

    $run = BackupRun::query()->create([
        'requested_by_user_id' => auth()->id(),
        'status' => 'running',
        'backup_type' => $type,
        'disk' => $disk,
        'started_at' => now(),
    ]);

    try {
        $path = 'backups/'.$type.'/backup-'.now()->format('Ymd_His').'.json';
        $payload = json_encode([
            'backup_run_id' => $run->id,
            'type' => $type,
            'generated_at' => now()->toIso8601String(),
            'note' => 'Placeholder backup artifact. Wire real dump/archives per environment tooling.',
        ], JSON_PRETTY_PRINT);

        Storage::disk($disk)->put($path, (string) $payload);
        $size = (int) Storage::disk($disk)->size($path);

        $run->update([
            'status' => 'completed',
            'path' => $path,
            'file_size_bytes' => $size,
            'completed_at' => now(),
        ]);

        $this->info("Backup completed: {$path}");
    } catch (\Throwable $e) {
        $run->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
            'completed_at' => now(),
        ]);
        $this->error('Backup failed: '.$e->getMessage());
    }
})->purpose('Run safety backup and log run');

Schedule::command('automation:reminders:run')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('safety:backup:run --disk=local --type=database')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Keep airport global index cache hot so first autocomplete query is fast.
Schedule::command('airports:warm-global-index')
    ->everyThirtyMinutes()
    ->withoutOverlapping();
