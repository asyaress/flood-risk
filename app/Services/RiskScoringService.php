<?php

namespace App\Services;

use App\Models\Area;
use App\Models\BmkgRain;
use App\Models\Criteria;
use App\Models\CriteriaWeight;
use App\Models\InariskFlood;
use App\Models\RiskScore;
use App\Models\SeaLevel;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggableStore;

class RiskScoringService
{
    /**
     * Get a cache repository. If the configured store supports tags (Redis does), tags will be used.
     */
    private function cache(array $tags = [])
    {
        $store = Cache::getStore();
        if (!empty($tags) && $store instanceof TaggableStore) {
            return Cache::tags($tags);
        }
        return Cache::store();
    }

    private function cacheRemember(array $tags, string $key, int $ttlSeconds, callable $callback)
    {
        try {
            return $this->cache($tags)->remember($key, $ttlSeconds, $callback);
        } catch (\Throwable $e) {
            // If Redis is down/misconfigured in dev, fall back to direct computation.
            return $callback();
        }
    }

    private function cacheFlush(array $tags): void
    {
        try {
            $this->cache($tags)->flush();
        } catch (\Throwable $e) {
            // ignore
        }
    }
    public function scoreBmkgRain(float $mmPerHour): float
    {
        foreach (config('risk.bmkg_rain_mm_per_hour') as $rule) {
            if ($mmPerHour <= $rule['max']) return (float)$rule['score'];
        }
        return 1.0;
    }

    public function scoreSeaLevel(float $cm): float
    {
        foreach (config('risk.sea_level_cm') as $rule) {
            if ($cm <= $rule['max']) return (float)$rule['score'];
        }
        return 1.0;
    }

    public function scoreInarisk(float $raw): float
    {
        $max = (float)config('risk.inarisk_scale_max', 100);
        if ($max <= 0) return 0.0;
        $val = $raw / $max;
        return max(0.0, min(1.0, $val));
    }

    public function levelFromIndex(float $riskIndex): string
    {
        foreach (config('risk.risk_levels') as $rule) {
            if ($riskIndex <= $rule['max']) return (string)$rule['level'];
        }
        return 'HIGH';
    }

    /**
     * Compute risk for all areas at observedAt.
     */
    public function computeForTimestamp(Carbon $observedAt): void
    {
        $weights = $this->latestWeightsByCode();
        $areas = Area::query()->orderBy('id')->get();

        $sea = SeaLevel::query()->where('observed_at', $observedAt)->first();
        if (!$sea) return;

        foreach ($areas as $area) {
            $bmkg = BmkgRain::query()->where('area_id', $area->id)->where('observed_at', $observedAt)->first();
            $inarisk = InariskFlood::query()->where('area_id', $area->id)->first();

            if (!$bmkg || !$inarisk) continue;

            $scores = [
                'BMKG_RAIN' => (float)$bmkg->score,
                'INARISK' => (float)$inarisk->score,
                'SEA_LEVEL' => (float)$sea->score,
            ];

            $riskIndex = 0.0;
            foreach ($scores as $code => $s) {
                $riskIndex += ($weights[$code] ?? 0.0) * $s;
            }

            $riskLevel = $this->levelFromIndex($riskIndex);

            RiskScore::query()->updateOrCreate(
                ['area_id' => $area->id, 'observed_at' => $observedAt],
                [
                    'risk_index' => $riskIndex,
                    'risk_level' => $riskLevel,
                    'detail_json' => [
                        'scores' => $scores,
                        'weights' => $weights,
                    ],
                ]
            );
        }

        // Invalidate cached risk payloads (latest + history) because we just wrote new results.
        $this->cacheFlush(['risk']);
    }

    public function latestWeightsByCode(): array
    {
        $latestVersion = (int) (CriteriaWeight::query()->max('version') ?? 1);
        $key = "ahp.weights.v{$latestVersion}";

        return $this->cacheRemember(['ahp'], $key, 3600, function () use ($latestVersion) {
            $criteria = Criteria::query()->whereNull('parent_id')->get()->keyBy('id');

            $weights = CriteriaWeight::query()
                ->where('version', $latestVersion)
                ->get();

            $out = [];
            foreach ($weights as $w) {
                $code = $criteria[$w->criteria_id]->code ?? null;
                if ($code) {
                    $out[$code] = (float) $w->weight_crisp;
                }
            }
            return $out;
        });
    }
}
