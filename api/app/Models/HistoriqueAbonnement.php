<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Historique abonnement
// ══════════════════════════════════════════════════════════════
class HistoriqueAbonnement extends Model
{
    public $timestamps  = false;
    protected $table    = 'historique_abonnements';
    protected $fillable = ['abonnement_id','ancien_statut','nouveau_statut','motif','effectue_par'];
    protected $casts    = ['cree_le' => 'datetime'];

    public function abonnement(): BelongsTo  { return $this->belongsTo(Abonnement::class); }
    public function effectuePar(): BelongsTo { return $this->belongsTo(Utilisateur::class, 'effectue_par'); }
}
