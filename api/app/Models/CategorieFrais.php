<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CategorieFrais extends Model
{
    public $timestamps  = false;
    protected $table    = 'categories_frais';
    protected $fillable = ['etablissement_id','libelle','description','est_actif'];
    protected $casts    = ['est_actif' => 'boolean', 'cree_le' => 'datetime'];

    public function etablissement(): BelongsTo { return $this->belongsTo(Etablissement::class); }
    public function frais(): HasMany           { return $this->hasMany(Frais::class, 'categorie_frais_id'); }
}
