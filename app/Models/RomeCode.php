<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RomeCode extends Model
{
    protected $fillable = ['code', 'label'];

    public function stats()
    {
        return $this->hasMany(RomeStat::class);
    }
}

