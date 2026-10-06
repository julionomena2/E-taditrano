<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quartier extends Model
{
    protected $table = 'quartiers';

    protected $primaryKey = 'id_quartier';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_quartier',
        'nom_quartier',
        'id_ville',
    ];

    public function ville(): BelongsTo
    {
        return $this->belongsTo(Ville::class, 'id_ville', 'id_ville');
    }

    public function logements(): HasMany
    {
        return $this->hasMany(Logement::class, 'id_quartier', 'id_quartier');
    }

    public function etablissements(): HasMany
    {
        return $this->hasMany(Etablissement::class, 'id_quartier', 'id_quartier');
    }
}
