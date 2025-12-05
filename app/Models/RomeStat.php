<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RomeStat extends Model
{
    protected $fillable = [
        'rome_code_id',
        'run_id',
        'execution_datetime',
        'avg_salary',
        'urgent_rate',
        'avg_days_open',
        'offer_count',
    ];

    public function romeCode()
    {
        return $this->belongsTo(RomeCode::class);
    }

    public function run()
    {
        return $this->belongsTo(RomeStatsRun::class, 'run_id');
    }
}

