<?php

namespace App\Console\Commands;

use App\Http\Controllers\DummyDataController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class DummyIngestCommand extends Command
{
    protected $signature = 'dummy:ingest {--at=}';
    protected $description = 'Generate dummy BMKG + sea level data, then compute risk.';

    public function handle(DummyDataController $ctrl): int
    {
        $req = Request::create('/api/dummy/ingest', 'POST', [
            'observed_at' => $this->option('at'),
        ]);

        $resp = $ctrl->ingest($req, app(\App\Services\RiskScoringService::class));
        $this->info($resp->getContent());
        return self::SUCCESS;
    }
}
