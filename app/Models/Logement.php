<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logement extends Model
{
    protected $table = 'logements';

    protected $fillable = [
        'id_utilisateur',
        'id_quartier',
        'description',
        'type_logement',
        'prix',
        'date_publication',
        'status',
        'photo',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'date_publication' => 'date',
        'prix' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class, 'id_quartier', 'id_quartier');
    }
}
