<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\BmkgRain;
use App\Models\SeaLevel;
use App\Services\RiskScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DummyDataController extends Controller
{
    /**
     * POST /api/dummy/ingest
     * body optional: { observed_at: ISO8601 }
     */
    public function ingest(Request $request, RiskScoringService $risk)
    {
        $observedAt = $request->input('observed_at')
            ? Carbon::parse($request->input('observed_at'))
            : now()->minute(0)->second(0);

        $areas = Area::query()->get();

        DB::transaction(function () use ($areas, $observedAt, $risk) {
            // sea level (single value for all areas)
            $seaRaw = random_int(20, 120); // cm
            $seaScore = $risk->scoreSeaLevel((float)$seaRaw);
            SeaLevel::query()->updateOrCreate(
                ['observed_at' => $observedAt],
                ['value_raw' => $seaRaw, 'score' => $seaScore]
            );

            foreach ($areas as $area) {
                $rainRaw = random_int(0, 40); // mm/h
                $rainScore = $risk->scoreBmkgRain((float)$rainRaw);

                BmkgRain::query()->updateOrCreate(
                    ['area_id' => $area->id, 'observed_at' => $observedAt],
                    ['value_raw' => $rainRaw, 'score' => $rainScore]
                );
            }

            $risk->computeForTimestamp($observedAt);
        });

        return response()->json(['ok' => true, 'observed_at' => $observedAt->toIso8601String()]);
    }
}
