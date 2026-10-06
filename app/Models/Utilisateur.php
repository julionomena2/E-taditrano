<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Utilisateur extends Model
{
    protected $table = 'utilisateurs';

    protected $primaryKey = 'id_utilisateur';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id_utilisateur',
        'nom',
        'prenom',
        'mot_de_passe',
        'telephone',
    ];

    public function logements(): HasMany
    {
        return $this->hasMany(Logement::class, 'id_utilisateur', 'id_utilisateur');
    }
}
