<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// ══════════════════════════════════════════════════════════════
//  Examen
// ══════════════════════════════════════════════════════════════
class Examen extends Model
{
    public $timestamps  = false;
    protected $table    = 'examens';
    protected $fillable = [
        'session_examen_id','matiere_id','classe_id','salle_id',
        'date_examen','heure_debut','heure_fin','surveillant_id',
        'coefficient','bareme','observations',
    ];
    protected $casts = [
        'date_examen' => 'date',
        'coefficient' => 'float',
        'bareme'      => 'float',
        'cree_le'     => 'datetime',
    ];

    public function sessionExamen(): BelongsTo { return $this->belongsTo(SessionExamen::class); }
    public function matiere(): BelongsTo       { return $this->belongsTo(Matiere::class); }
    public function classe(): BelongsTo        { return $this->belongsTo(Classe::class); }
    public function salle(): BelongsTo         { return $this->belongsTo(Salle::class); }
    public function surveillant(): BelongsTo   { return $this->belongsTo(Enseignant::class, 'surveillant_id'); }
}
