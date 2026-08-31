<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Salle
// ══════════════════════════════════════════════════════════════
class Salle extends Model
{
    public $timestamps  = false;
    protected $table    = 'salles';
    protected $fillable = ['etablissement_id','nom','batiment','capacite','type_salle','est_disponible'];
    protected $casts    = ['est_disponible' => 'boolean'];

    public function etablissement(): BelongsTo { return $this->belongsTo(Etablissement::class); }
    public function seances(): HasMany         { return $this->hasMany(Seance::class); }
}
