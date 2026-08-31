<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Séance
// ══════════════════════════════════════════════════════════════
class Seance extends Model
{
    public $timestamps   = false;
    protected $table     = 'seances';
    protected $fillable  = [
        'etablissement_id','affectation_id','salle_id','classe_id',
        'semestre_id','creneau_id','jour_semaine','type_seance',
        'date_specifique','est_annule','motif_annulation',
    ];
    protected $casts = [
        'est_annule'      => 'boolean',
        'date_specifique' => 'date',
        'cree_le'         => 'datetime',
        'mis_a_jour_le'   => 'datetime',
    ];

    // Noms des jours pour affichage
    public const JOURS = [
        1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi',
        4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi',
    ];

    public function etablissement(): BelongsTo { return $this->belongsTo(Etablissement::class); }
    public function affectation(): BelongsTo   { return $this->belongsTo(AffectationCours::class, 'affectation_id'); }
    public function salle(): BelongsTo         { return $this->belongsTo(Salle::class); }
    public function classe(): BelongsTo        { return $this->belongsTo(Classe::class); }
    public function semestre(): BelongsTo      { return $this->belongsTo(Semestre::class); }
    public function creneau(): BelongsTo       { return $this->belongsTo(CreneauHoraire::class, 'creneau_id'); }
    public function conflits(): HasMany        { return $this->hasMany(ConflitEmploiDuTemps::class); }
    public function presences(): HasMany       { return $this->hasMany(Presence::class); }
    public function presencesEnseignants(): HasMany { return $this->hasMany(PresenceEnseignant::class); }

    public function getNomJourAttribute(): string
    {
        return self::JOURS[$this->jour_semaine] ?? '';
    }
}
