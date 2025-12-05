<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RomeStatsRun extends Model
{
    use HasFactory;

    /**
     * Champs pouvant être remplis en masse (fillable)
     */
    protected $fillable = [
        'started_at',
        'finished_at',
        'comment',
    ];

    /**
     * Cast des propriétés pour auto-transformer
     * les dates en objets Carbon.
     */
    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
    ];

    /**
     * Un run contient plusieurs enregistrements statistiques,
     * un par code ROME.
     */
    public function stats()
    {
        return $this->hasMany(RomeStat::class, 'run_id');
    }

    /**
     * Calculer le nombre total d'enregistrements statistiques
     * associés à ce run.
     */
    public function statsCount()
    {
        return $this->stats()->count();
    }

    /**
     * Savoir si le run est terminé.
     */
    public function isFinished()
    {
        return !is_null($this->finished_at);
    }

    /**
     * Calculer la durée du run (en secondes).
     */
    public function durationInSeconds()
    {
        if (!$this->finished_at) {
            return null;
        }

        return $this->finished_at->diffInSeconds($this->started_at);
    }
}

