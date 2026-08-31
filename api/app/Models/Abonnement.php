<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Abonnement
// ══════════════════════════════════════════════════════════════
class Abonnement extends Model
{
    public $timestamps  = false;
    protected $table    = 'abonnements';
    protected $fillable = [
        'etablissement_id','plan_id','statut','montant','devise',
        'date_debut','date_fin','renouvellement_auto','souscrit_par',
        'annule_le','motif_annulation',
    ];
    protected $casts = [
        'montant'            => 'float',
        'date_debut'         => 'date',
        'date_fin'           => 'date',
        'annule_le'          => 'datetime',
        'renouvellement_auto'=> 'boolean',
        'cree_le'            => 'datetime',
        'mis_a_jour_le'      => 'datetime',
    ];

    public const STATUTS = ['en_attente','actif','suspendu','expire','annule'];

    public function etablissement(): BelongsTo { return $this->belongsTo(Etablissement::class); }
    public function plan(): BelongsTo          { return $this->belongsTo(PlanAbonnement::class, 'plan_id'); }
    public function souscritPar(): BelongsTo   { return $this->belongsTo(Utilisateur::class, 'souscrit_par'); }

    public function historique(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(HistoriqueAbonnement::class);
    }
    public function factures(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Facture::class);
    }
    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransactionPaiement::class);
    }

    public function estActif(): bool   { return $this->statut === 'actif'; }
    public function estExpire(): bool  { return $this->statut === 'expire'; }
    public function estEssai(): bool   { return $this->plan->essai_jours > 0 && now()->lt($this->date_debut->addDays($this->plan->essai_jours)); }
}
