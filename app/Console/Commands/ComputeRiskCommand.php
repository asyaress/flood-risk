<?php

namespace App\Console\Commands;

use App\Services\RiskScoringService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ComputeRiskCommand extends Command
{
    protected $signature = 'risk:compute {--at=}';
    protected $description = 'Compute risk scores for a timestamp (default: latest hour).';

    public function handle(RiskScoringService $risk): int
    {
        $at = $this->option('at')
            ? Carbon::parse($this->option('at'))
            : now()->minute(0)->second(0);

        $risk->computeForTimestamp($at);

        $this->info('OK computed risk for '.$at->toIso8601String());
        return self::SUCCESS;
    }
}
