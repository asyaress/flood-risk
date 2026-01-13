<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeaLevel extends Model
{
    protected $fillable = ['observed_at', 'value_raw', 'score'];

    protected $casts = [
        'observed_at' => 'datetime',
        'value_raw' => 'float',
        'score' => 'float',
    ];
}
