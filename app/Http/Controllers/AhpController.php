<?php

namespace App\Http\Controllers;

use App\Models\Criteria;
use App\Models\CriteriaWeight;
use App\Models\PairwiseComparison;
use App\Models\SeaLevel;
use App\Services\FuzzyAHPService;
use App\Services\RiskScoringService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggableStore;

class AhpController extends Controller
{
    private function cache(array $tags = [])
    {
        $store = Cache::getStore();
        if (!empty($tags) && $store instanceof TaggableStore) {
            return Cache::tags($tags);
        }
        return Cache::store();
    }

    private function cacheFlush(array $tags): void
    {
        try {
            $this->cache($tags)->flush();
        } catch (\Throwable $e) {
            // ignore
        }
    }
    public function criteria()
    {
        return Criteria::query()->whereNull('parent_id')->orderBy('id')->get();
    }

    public function matrix(Request $request, FuzzyAHPService $svc)
    {
        $expertId = (int) $request->query('expert_id', 1);

        [$ids, $matrix] = $svc->buildMatrix($expertId);
        $criteria = Criteria::query()->whereIn('id', $ids)->orderBy('id')->get();

        return response()->json([
            'expert_id' => $expertId,
            'criteria' => $criteria,
            'matrix' => $matrix,
        ]);
    }


    /**
     * Save pairwise TFN inputs.
     * body: { expert_id: 1, items: [{criteria_i_id,criteria_j_id,l,m,u}, ...] }
     */
    public function saveMatrix(Request $request)
    {
        $data = $request->validate([
            'expert_id' => 'required|integer',
            'items' => 'required|array',
            'items.*.criteria_i_id' => 'required|integer',
            'items.*.criteria_j_id' => 'required|integer',
            'items.*.l' => 'required|numeric|min:0.0001',
            'items.*.m' => 'required|numeric|min:0.0001',
            'items.*.u' => 'required|numeric|min:0.0001',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                PairwiseComparison::query()->updateOrCreate(
                    [
                        'expert_id' => $data['expert_id'],
                        'criteria_i_id' => $item['criteria_i_id'],
                        'criteria_j_id' => $item['criteria_j_id'],
                    ],
                    [
                        'l' => $item['l'],
                        'm' => $item['m'],
                        'u' => $item['u'],
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * Compute weights, store as a new version, and recompute risk for the latest timestamp (if exists).
     */
    public function compute(Request $request, FuzzyAHPService $svc, RiskScoringService $risk)
    {
        $expertId = (int) ($request->input('expert_id') ?? $request->query('expert_id', 1));
        [$ids, $matrix] = $svc->buildMatrix($expertId);
        $res = $svc->computeWeights($matrix);

        $version = (CriteriaWeight::query()->max('version') ?? 0) + 1;

        DB::transaction(function () use ($ids, $res, $version) {
            foreach ($ids as $idx => $criteriaId) {
                $f = $res['fuzzy'][$idx];
                CriteriaWeight::query()->create([
                    'criteria_id' => $criteriaId,
                    'version' => $version,
                    'l' => $f['l'],
                    'm' => $f['m'],
                    'u' => $f['u'],
                    'weight_crisp' => $res['crisp'][$idx],
                ]);
            }
        });

        // Invalidate cached AHP weights + risk payloads.
        $this->cacheFlush(['ahp']);
        $this->cacheFlush(['risk']);

        // Recompute risk for latest observed_at so dashboard langsung berubah
        $latestAt = SeaLevel::query()->max('observed_at');
        if ($latestAt) {
            $risk->computeForTimestamp(Carbon::parse($latestAt));
        }

        $criteria = Criteria::query()->whereIn('id', $ids)->orderBy('id')->get()->values();

        $out = [];
        foreach ($criteria as $idx => $c) {
            $out[] = [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'weight' => $res['crisp'][$idx],
            ];
        }

        return response()->json([
            'version' => $version,
            'cr' => $res['cr'],
            'weights' => $out,
        ]);
    }

    public function latestWeights()
    {
        $version = CriteriaWeight::query()->max('version');
        if (!$version)
            return response()->json(['version' => null, 'weights' => []]);

        $criteria = Criteria::query()->whereNull('parent_id')->get()->keyBy('id');
        $weights = CriteriaWeight::query()->where('version', $version)->get();

        $out = $weights->map(function ($w) use ($criteria) {
            $c = $criteria[$w->criteria_id] ?? null;
            return [
                'id' => $w->criteria_id,
                'code' => $c?->code,
                'name' => $c?->name,
                'weight' => (float) $w->weight_crisp,
            ];
        })->values();

        return response()->json(['version' => $version, 'weights' => $out]);
    }

    public function pairwise(Request $request)
    {
        $expertId = (int) $request->query('expert_id', 1);

        return PairwiseComparison::query()
            ->where('expert_id', $expertId)
            ->orderBy('criteria_i_id')
            ->orderBy('criteria_j_id')
            ->get();
    }

}
