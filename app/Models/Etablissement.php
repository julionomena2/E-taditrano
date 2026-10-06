<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Etablissement extends Model
{
    protected $table = 'etablissements';

    protected $fillable = [
        'nom_etablissement',
        'id_quartier',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function quartier(): BelongsTo
    {
        return $this->belongsTo(Quartier::class, 'id_quartier', 'id_quartier');
    }
}
