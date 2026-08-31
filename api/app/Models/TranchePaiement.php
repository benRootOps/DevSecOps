<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TranchePaiement extends Model
{
    public $timestamps  = false;
    protected $table    = 'tranches_paiement';
    protected $fillable = ['frais_id','numero','libelle','montant','date_echeance'];
    protected $casts    = [
        'montant'       => 'float',
        'date_echeance' => 'date',
        'cree_le'       => 'datetime',
    ];

    public function frais(): BelongsTo        { return $this->belongsTo(Frais::class); }
    public function versements(): HasMany     { return $this->hasMany(Versement::class, 'tranche_id'); }

    public function montantVerse(): float
    {
        return $this->versements()->sum('montant_verse');
    }

    public function estSolde(): bool
    {
        return $this->montantVerse() >= $this->montant;
    }
}
