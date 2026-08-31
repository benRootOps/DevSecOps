<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Frais extends Model
{
    public $timestamps  = false;
    protected $table    = 'frais';
    protected $fillable = [
        'etablissement_id','categorie_frais_id','filiere_id','niveau_id',
        'annee_academique_id','montant_total','nombre_tranches','devise','est_obligatoire',
    ];
    protected $casts = [
        'montant_total'   => 'float',
        'est_obligatoire' => 'boolean',
        'cree_le'         => 'datetime',
    ];

    public function etablissement(): BelongsTo   { return $this->belongsTo(Etablissement::class); }
    public function categorie(): BelongsTo        { return $this->belongsTo(CategorieFrais::class, 'categorie_frais_id'); }
    public function filiere(): BelongsTo          { return $this->belongsTo(Filiere::class); }
    public function niveau(): BelongsTo           { return $this->belongsTo(Niveau::class); }
    public function anneeAcademique(): BelongsTo  { return $this->belongsTo(AnneeAcademique::class); }
    public function tranches(): HasMany           { return $this->hasMany(TranchePaiement::class); }
}
