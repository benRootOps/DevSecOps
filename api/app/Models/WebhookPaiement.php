<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Webhook paiement
// ══════════════════════════════════════════════════════════════
class WebhookPaiement extends Model
{
    public $timestamps  = false;
    protected $table    = 'webhooks_paiement';
    protected $fillable = [
        'moyen_paiement_id','transaction_id','evenement','reference_externe',
        'signature','est_signature_valide','est_traite','payload','adresse_ip',
        'recu_le','traite_le',
    ];
    protected $casts = [
        'payload'             => 'array',
        'est_signature_valide'=> 'boolean',
        'est_traite'          => 'boolean',
        'recu_le'             => 'datetime',
        'traite_le'           => 'datetime',
    ];

    public function moyenPaiement(): BelongsTo { return $this->belongsTo(MoyenPaiement::class); }
    public function transaction(): BelongsTo   { return $this->belongsTo(TransactionPaiement::class); }
}
