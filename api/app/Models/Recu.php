<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Recu extends Model
{
    public $timestamps  = false;
    protected $table    = 'recus';
    protected $fillable = ['versement_id','numero_recu','fichier_url','genere_par'];
    protected $casts    = ['genere_le' => 'datetime'];

    public function versement(): BelongsTo { return $this->belongsTo(Versement::class); }
    public function generePar(): BelongsTo { return $this->belongsTo(Utilisateur::class, 'genere_par'); }
}
