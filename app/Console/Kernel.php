<?php

namespace App\Console;

use App\Console\Commands\ComputeRiskCommand;
use App\Console\Commands\DummyIngestCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        ComputeRiskCommand::class,
        DummyIngestCommand::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        // Example schedule (optional): generate dummy data hourly
        // $schedule->command('dummy:ingest')->hourly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
