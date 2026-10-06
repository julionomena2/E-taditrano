<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ville extends Model
{
    protected $table = 'villes';

    protected $primaryKey = 'id_ville';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_ville',
        'nom_Ville',
    ];

    public function quartiers(): HasMany
    {
        return $this->hasMany(Quartier::class, 'id_ville', 'id_ville');
    }
}
