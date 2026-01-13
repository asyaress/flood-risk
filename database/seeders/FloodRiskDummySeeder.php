<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Criteria;
use App\Models\InariskFlood;
use App\Models\PairwiseComparison;
use App\Services\FuzzyAHPService;
use App\Services\RiskScoringService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FloodRiskDummySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Criteria
            $bmkg = Criteria::query()->create(['code' => 'BMKG_RAIN', 'name' => 'Intensitas Hujan (BMKG)']);
            $inar = Criteria::query()->create(['code' => 'INARISK', 'name' => 'Kerentanan/Bahaya (InaRISK)']);
            $sea  = Criteria::query()->create(['code' => 'SEA_LEVEL', 'name' => 'Muka Air Laut/Pasang']);

            // Default pairwise TFN (dummy)
            // BMKG > InaRISK (3)
            PairwiseComparison::query()->create([
                'expert_id' => 1,
                'criteria_i_id' => $bmkg->id,
                'criteria_j_id' => $inar->id,
                'l' => 2, 'm' => 3, 'u' => 4,
            ]);
            // BMKG > Sea (5)
            PairwiseComparison::query()->create([
                'expert_id' => 1,
                'criteria_i_id' => $bmkg->id,
                'criteria_j_id' => $sea->id,
                'l' => 4, 'm' => 5, 'u' => 6,
            ]);
            // InaRISK > Sea (3)
            PairwiseComparison::query()->create([
                'expert_id' => 1,
                'criteria_i_id' => $inar->id,
                'criteria_j_id' => $sea->id,
                'l' => 2, 'm' => 3, 'u' => 4,
            ]);

            // Areas (dummy near Samarinda)
            $baseLat = -0.502;
            $baseLng = 117.153;
            $names = [
                'Samarinda Ulu', 'Samarinda Ilir', 'Samarinda Utara', 'Sungai Kunjang',
                'Samarinda Seberang', 'Loa Janan Ilir', 'Palaran', 'Sungai Pinang',
                'Anggana (sekitar)', 'Muara Kaman (sekitar)'
            ];

            foreach ($names as $idx => $name) {
                Area::query()->create([
                    'name' => $name,
                    'type' => 'kecamatan',
                    'lat' => $baseLat + (mt_rand(-80, 80) / 1000),
                    'lng' => $baseLng + (mt_rand(-80, 80) / 1000),
                ]);
            }

            // InaRISK dummy per area
            $riskSvc = app(RiskScoringService::class);
            foreach (Area::query()->get() as $area) {
                $raw = mt_rand(30, 95); // 0..100
                InariskFlood::query()->create([
                    'area_id' => $area->id,
                    'value_raw' => $raw,
                    'score' => $riskSvc->scoreInarisk((float)$raw),
                ]);
            }
        });

        // compute initial weights & generate first dummy ingest
        $svc = app(FuzzyAHPService::class);
        [$ids, $matrix] = $svc->buildMatrix(1);
        $res = $svc->computeWeights($matrix);

        $version = 1;
        foreach ($ids as $idx => $criteriaId) {
            \App\Models\CriteriaWeight::query()->create([
                'criteria_id' => $criteriaId,
                'version' => $version,
                'l' => $res['fuzzy'][$idx]['l'],
                'm' => $res['fuzzy'][$idx]['m'],
                'u' => $res['fuzzy'][$idx]['u'],
                'weight_crisp' => $res['crisp'][$idx],
            ]);
        }

        // Initial ingest at top of hour
        $at = now()->minute(0)->second(0);
        \App\Http\Controllers\DummyDataController::class;
        app(\App\Http\Controllers\DummyDataController::class)
            ->ingest(\Illuminate\Http\Request::create('/api/dummy/ingest', 'POST', ['observed_at' => $at->toIso8601String()]), app(RiskScoringService::class));
    }
}
