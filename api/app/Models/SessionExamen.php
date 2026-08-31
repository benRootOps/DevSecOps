<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// ══════════════════════════════════════════════════════════════
//  SessionExamen
// ══════════════════════════════════════════════════════════════
class SessionExamen extends Model
{
    public $timestamps  = false;
    protected $table    = 'sessions_examen';
    protected $fillable = [
        'etablissement_id','semestre_id','libelle','type_session',
        'date_debut','date_fin','est_cloturee',
    ];
    protected $casts = [
        'date_debut'   => 'date',
        'date_fin'     => 'date',
        'est_cloturee' => 'boolean',
        'cree_le'      => 'datetime',
    ];

    public function etablissement(): BelongsTo { return $this->belongsTo(Etablissement::class); }
    public function semestre(): BelongsTo      { return $this->belongsTo(Semestre::class); }
    public function notes(): HasMany           { return $this->hasMany(Note::class); }
    public function examens(): HasMany         { return $this->hasMany(Examen::class); }
    public function moyennesUe(): HasMany      { return $this->hasMany(MoyenneUe::class, 'session_examen_id'); }
    public function moyennesSemestre(): HasMany{ return $this->hasMany(MoyenneSemestre::class, 'session_examen_id'); }
    public function deliberations(): HasMany   { return $this->hasMany(Deliberation::class); }
}
