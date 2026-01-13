<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PairwiseComparison extends Model
{
    protected $fillable = [
        'expert_id',
        'criteria_i_id',
        'criteria_j_id',
        'l', 'm', 'u',
    ];

    protected $casts = [
        'l' => 'float',
        'm' => 'float',
        'u' => 'float',
    ];

    public function criteriaI()
    {
        return $this->belongsTo(Criteria::class, 'criteria_i_id');
    }

    public function criteriaJ()
    {
        return $this->belongsTo(Criteria::class, 'criteria_j_id');
    }
}
