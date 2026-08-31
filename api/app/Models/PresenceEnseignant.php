<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Présence enseignant
// ══════════════════════════════════════════════════════════════
class PresenceEnseignant extends Model
{
    public $timestamps  = false;
    protected $table    = 'presences_enseignants';
    protected $fillable = ['seance_id','enseignant_id','statut','remplacant_id','observations','saisie_par'];
    protected $casts    = ['cree_le' => 'datetime'];

    public const STATUTS = ['Présent', 'Absent', 'Remplacé'];

    public function seance(): BelongsTo      { return $this->belongsTo(Seance::class); }
    public function enseignant(): BelongsTo  { return $this->belongsTo(Enseignant::class); }
    public function remplacant(): BelongsTo  { return $this->belongsTo(Enseignant::class, 'remplacant_id'); }
    public function saisie(): BelongsTo      { return $this->belongsTo(Utilisateur::class, 'saisie_par'); }
}


// ──────────────────────────────────────────────────────────────
//  MODÈLES — Module F : Abonnements & Paiements SaaS
// ──────────────────────────────────────────────────────────────
