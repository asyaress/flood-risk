<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CriteriaWeight extends Model
{
    protected $fillable = ['criteria_id', 'version', 'l', 'm', 'u', 'weight_crisp'];

    protected $casts = [
        'l' => 'float',
        'm' => 'float',
        'u' => 'float',
        'weight_crisp' => 'float',
    ];

    public function criteria()
    {
        return $this->belongsTo(Criteria::class);
    }
}
