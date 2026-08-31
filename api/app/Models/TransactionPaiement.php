<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Transaction paiement
// ══════════════════════════════════════════════════════════════
class TransactionPaiement extends Model
{
    public $timestamps  = false;
    protected $table    = 'transactions_paiement';
    protected $fillable = [
        'reference_interne','reference_externe','type_transaction',
        'transaction_parent_id','etablissement_id','abonnement_id',
        'facture_id','moyen_paiement_id','montant','devise','statut',
        'numero_telephone','message_passerelle','payload_passerelle','initiee_par',
        'initiee_le','confirmee_le',
    ];
    protected $casts = [
        'montant'            => 'float',
        'payload_passerelle' => 'array',
        'initiee_le'         => 'datetime',
        'confirmee_le'       => 'datetime',
        'cree_le'            => 'datetime',
        'mis_a_jour_le'      => 'datetime',
    ];

    public const STATUTS = ['initiee','en_attente','reussie','echouee','annulee','expiree','remboursee'];

    public function etablissement(): BelongsTo  { return $this->belongsTo(Etablissement::class); }
    public function abonnement(): BelongsTo     { return $this->belongsTo(Abonnement::class); }
    public function facture(): BelongsTo        { return $this->belongsTo(Facture::class); }
    public function moyenPaiement(): BelongsTo  { return $this->belongsTo(MoyenPaiement::class); }
    public function initieePar(): BelongsTo     { return $this->belongsTo(Utilisateur::class, 'initiee_par'); }
    public function parent(): BelongsTo         { return $this->belongsTo(self::class, 'transaction_parent_id'); }

    public function estReussie(): bool      { return $this->statut === 'reussie'; }
    public function estRemboursement(): bool { return $this->type_transaction === 'remboursement'; }
}
