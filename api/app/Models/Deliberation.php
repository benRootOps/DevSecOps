<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deliberation extends Model
{
    public $timestamps  = false;
    protected $table    = 'deliberations';
    protected $fillable = [
        'session_examen_id','classe_id','tenue_le','president_jury',
        'proces_verbal_url','observations','est_close',
    ];
    protected $casts = [
        'tenue_le'  => 'date',
        'est_close' => 'boolean',
        'cree_le'   => 'datetime',
    ];

    public function sessionExamen(): BelongsTo { return $this->belongsTo(SessionExamen::class); }
    public function classe(): BelongsTo        { return $this->belongsTo(Classe::class); }
    public function presidentJury(): BelongsTo { return $this->belongsTo(Enseignant::class, 'president_jury'); }
    public function resultats(): HasMany       { return $this->hasMany(DeliberationResultat::class); }
}
