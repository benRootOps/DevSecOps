<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DeliberationResultat extends Model
{
    public $timestamps  = false;
    protected $table    = 'deliberation_resultats';
    protected $fillable = [
        'deliberation_id','etudiant_id','decision',
        'moyenne_finale','mention','credits_valides','observations',
    ];
    protected $casts = [
        'moyenne_finale' => 'float',
        'credits_valides'=> 'integer',
        'cree_le'        => 'datetime',
    ];

    public const DECISIONS = ['Admis', 'Ajourné', 'Rattrapage', 'Exclu', 'Abandonné'];

    public function deliberation(): BelongsTo { return $this->belongsTo(Deliberation::class); }
    public function etudiant(): BelongsTo     { return $this->belongsTo(Etudiant::class); }
}
