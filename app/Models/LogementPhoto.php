<?php

namespace App\Models;

use Database\Factories\LogementPhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogementPhoto extends Model
{
    /** @use HasFactory<LogementPhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'logement_id',
        'chemin',
        'ordre',
    ];

    public function logement(): BelongsTo
    {
        return $this->belongsTo(Logement::class);
    }
}
