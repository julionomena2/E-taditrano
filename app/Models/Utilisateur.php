<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Utilisateur extends Authenticatable
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
        'photo',
    ];

    protected $hidden = [
        'mot_de_passe',
    ];

    protected function casts(): array
    {
        return [
            'mot_de_passe' => 'hashed',
        ];
    }

    public function getAuthPasswordName(): string
    {
        return 'mot_de_passe';
    }

    public function logements(): HasMany
    {
        return $this->hasMany(Logement::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function messagesEnvoyes(): HasMany
    {
        return $this->hasMany(Message::class, 'expediteur_id', 'id_utilisateur');
    }

    public function messagesRecus(): HasMany
    {
        return $this->hasMany(Message::class, 'destinataire_id', 'id_utilisateur');
    }
}
