<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Présence étudiant
// ══════════════════════════════════════════════════════════════
class Presence extends Model
{
    public $timestamps  = false;
    protected $table    = 'presences';
    protected $fillable = ['seance_id','etudiant_id','statut','motif','saisie_par'];
    protected $casts    = ['cree_le' => 'datetime'];

    public const STATUTS = ['Présent', 'Absent', 'Retard', 'Excusé'];

    public function seance(): BelongsTo   { return $this->belongsTo(Seance::class); }
    public function etudiant(): BelongsTo { return $this->belongsTo(Etudiant::class); }
    public function saisie(): BelongsTo   { return $this->belongsTo(Utilisateur::class, 'saisie_par'); }
}
