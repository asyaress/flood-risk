<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RiskScore extends Model
{
    protected $fillable = ['area_id', 'observed_at', 'risk_index', 'risk_level', 'detail_json'];

    protected $casts = [
        'observed_at' => 'datetime',
        'risk_index' => 'float',
        'detail_json' => 'array',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
