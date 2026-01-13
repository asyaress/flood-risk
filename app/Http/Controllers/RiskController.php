<?php

namespace App\Http\Controllers;

use App\Models\RiskScore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\TaggableStore;

class RiskController extends Controller
{
    private function cache(array $tags = [])
    {
        $store = Cache::getStore();
        if (!empty($tags) && $store instanceof TaggableStore) {
            return Cache::tags($tags);
        }
        return Cache::store();
    }

    private function cacheRemember(array $tags, string $key, int $ttlSeconds, \Closure $callback)
    {
        try {
            return $this->cache($tags)->remember($key, $ttlSeconds, $callback);
        } catch (\Throwable $e) {
            return $callback();
        }
    }

    public function latest()
    {
        return response()->json($this->cacheRemember(['risk'], 'risk.latest', 10, function () {
            $latestAt = RiskScore::query()->max('observed_at');
            if (!$latestAt) {
                return ['observed_at' => null, 'items' => []];
            }

            $items = RiskScore::query()
                ->with('area')
                ->where('observed_at', $latestAt)
                ->orderBy('risk_index', 'desc')
                ->get()
                ->map(fn($r) => [
                    'area_id' => $r->area_id,
                    'area_name' => $r->area->name,
                    'lat' => $r->area->lat,
                    'lng' => $r->area->lng,
                    'observed_at' => $r->observed_at?->toIso8601String(),
                    'risk_index' => (float) $r->risk_index,
                    'risk_level' => $r->risk_level,
                    'detail' => $r->detail_json,
                ])->values();

            return [
                'observed_at' => $latestAt,
                'items' => $items,
            ];
        }));
    }

    public function history(Request $request)
    {
        $areaId = (int)$request->query('area_id');
        if (!$areaId) return response()->json(['items' => []]);

        $key = "risk.history.area.{$areaId}";
        $payload = $this->cacheRemember(['risk'], $key, 60, function () use ($areaId) {
            return RiskScore::query()
                ->where('area_id', $areaId)
                ->orderBy('observed_at', 'asc')
                ->limit(200)
                ->get()
                ->map(fn($r) => [
                    'observed_at' => $r->observed_at?->toIso8601String(),
                    'risk_index' => (float) $r->risk_index,
                    'risk_level' => $r->risk_level,
                ])->values();
        });

        return response()->json(['items' => $payload]);
    }
}
