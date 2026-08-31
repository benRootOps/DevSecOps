<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Plan d'abonnement
// ══════════════════════════════════════════════════════════════
class PlanAbonnement extends Model
{
    public $timestamps  = false;
    protected $table    = 'plans_abonnement';
    protected $fillable = [
        'code','nom','description','prix','devise','periodicite',
        'duree_jours','max_utilisateurs','max_etudiants',
        'essai_jours','est_public','est_actif',
    ];
    protected $casts = [
        'prix'       => 'float',
        'est_public' => 'boolean',
        'est_actif'  => 'boolean',
        'cree_le'    => 'datetime',
    ];

    public const PERIODICITES = ['mensuel','trimestriel','semestriel','annuel'];

    public function abonnements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Abonnement::class, 'plan_id');
    }
}
