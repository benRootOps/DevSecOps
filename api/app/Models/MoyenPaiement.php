<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Moyen de paiement
// ══════════════════════════════════════════════════════════════
class MoyenPaiement extends Model
{
    public $timestamps  = false;
    protected $table    = 'moyens_paiement';
    protected $fillable = ['code','nom','type','fournisseur','portee','configuration','est_actif'];
    protected $casts    = [
        'configuration' => 'array',
        'est_actif'     => 'boolean',
    ];

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TransactionPaiement::class);
    }
}
