<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Créneau horaire
// ══════════════════════════════════════════════════════════════
class CreneauHoraire extends Model
{
    public $timestamps  = false;
    protected $table    = 'creneaux_horaires';
    protected $fillable = ['etablissement_id','heure_debut','heure_fin','libelle','ordre'];

    public function etablissement(): BelongsTo { return $this->belongsTo(Etablissement::class); }
    public function seances(): HasMany         { return $this->hasMany(Seance::class, 'creneau_id'); }
}
