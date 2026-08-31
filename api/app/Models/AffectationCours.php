<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Affectation cours
// ══════════════════════════════════════════════════════════════
class AffectationCours extends Model
{
    public $timestamps  = false;
    protected $table    = 'affectations_cours';
    protected $fillable = ['enseignant_id','matiere_id','classe_id','charge_horaire'];

    public function enseignant(): BelongsTo { return $this->belongsTo(Enseignant::class); }
    public function matiere(): BelongsTo    { return $this->belongsTo(Matiere::class); }
    public function classe(): BelongsTo     { return $this->belongsTo(Classe::class); }
    public function seances(): HasMany      { return $this->hasMany(Seance::class, 'affectation_id'); }
}
