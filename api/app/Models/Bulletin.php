<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Bulletin extends Model
{
    public $timestamps  = false;
    protected $table    = 'bulletins';
    protected $fillable = [
        'etudiant_id','semestre_id','session_examen_id',
        'type_bulletin','fichier_url','genere_le','genere_par','est_publie',
    ];
    protected $casts = [
        'genere_le'  => 'datetime',
        'est_publie' => 'boolean',
        'cree_le'    => 'datetime',
    ];

    public function etudiant(): BelongsTo      { return $this->belongsTo(Etudiant::class); }
    public function semestre(): BelongsTo      { return $this->belongsTo(Semestre::class); }
    public function sessionExamen(): BelongsTo { return $this->belongsTo(SessionExamen::class); }
    public function generePar(): BelongsTo     { return $this->belongsTo(Utilisateur::class, 'genere_par'); }
}
