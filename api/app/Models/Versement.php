<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Versement extends Model
{
    public $timestamps  = false;
    protected $table    = 'versements';
    protected $fillable = [
        'etudiant_id','tranche_id','montant_verse',
        'date_versement','mode_paiement','reference','observations','enregistre_par',
    ];
    protected $casts = [
        'montant_verse'   => 'float',
        'date_versement'  => 'date',
        'cree_le'         => 'datetime',
    ];

    public function etudiant(): BelongsTo    { return $this->belongsTo(Etudiant::class); }
    public function tranche(): BelongsTo     { return $this->belongsTo(TranchePaiement::class, 'tranche_id'); }
    public function enregistrePar(): BelongsTo { return $this->belongsTo(Utilisateur::class, 'enregistre_par'); }
    public function recu(): HasOne           { return $this->hasOne(Recu::class); }
}
