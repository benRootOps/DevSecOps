<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Facture
// ══════════════════════════════════════════════════════════════
class Facture extends Model
{
    public $timestamps  = false;
    protected $table    = 'factures';
    protected $fillable = [
        'numero_facture','etablissement_id','abonnement_id','statut',
        'montant_ht','taux_taxe','montant_taxe','montant_ttc','devise',
        'date_emission','date_echeance','payee_le','fichier_url',
    ];
    protected $casts = [
        'montant_ht'   => 'float',
        'taux_taxe'    => 'float',
        'montant_taxe' => 'float',
        'montant_ttc'  => 'float',
        'date_emission'=> 'date',
        'date_echeance'=> 'date',
        'payee_le'     => 'datetime',
        'cree_le'      => 'datetime',
        'mis_a_jour_le'=> 'datetime',
    ];

    public const STATUTS = ['brouillon','emise','payee','partiellement_payee','impayee','annulee'];

    public function etablissement(): BelongsTo  { return $this->belongsTo(Etablissement::class); }
    public function abonnement(): BelongsTo     { return $this->belongsTo(Abonnement::class); }
    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransactionPaiement::class, 'facture_id');
    }

    public function estPayee(): bool { return $this->statut === 'payee'; }
}
