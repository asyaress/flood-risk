<?php

namespace App\Http\Controllers;

use App\Models\Area;

class AreaController extends Controller
{
    public function index()
    {
        $areas = Area::query()->orderBy('id')->get();

        // GeoJSON Point Features
        $features = $areas->map(fn($a) => [
            'type' => 'Feature',
            'properties' => [
                'id' => $a->id,
                'name' => $a->name,
                'type' => $a->type,
            ],
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [(float)$a->lng, (float)$a->lat],
            ],
        ])->values();

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }
}
