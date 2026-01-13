<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InariskFlood extends Model
{
    protected $fillable = ['area_id', 'value_raw', 'score'];

    protected $casts = [
        'value_raw' => 'float',
        'score' => 'float',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }
}
