<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BmkgRain extends Model
{

protected $table = 'bmkg_rains';
    protected $fillable = ['area_id', 'observed_at', 'value_raw', 'score'];

    protected $casts = [
        'observed_at' => 'datetime',
        'value_raw' => 'float',
        'score' => 'float',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
