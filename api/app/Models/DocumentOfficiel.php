<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DocumentOfficiel extends Model
{
    public $timestamps  = false;
    protected $table    = 'documents_officiels';
    protected $fillable = [
        'etudiant_id','etablissement_id','type_document',
        'annee_academique_id','numero_document','fichier_url',
        'genere_par','valide_par','est_valide','observations',
    ];
    protected $casts = [
        'est_valide' => 'boolean',
        'genere_le'  => 'datetime',
        'cree_le'    => 'datetime',
    ];

    public function etudiant(): BelongsTo        { return $this->belongsTo(Etudiant::class); }
    public function etablissement(): BelongsTo   { return $this->belongsTo(Etablissement::class); }
    public function anneeAcademique(): BelongsTo { return $this->belongsTo(AnneeAcademique::class); }
    public function generePar(): BelongsTo       { return $this->belongsTo(Utilisateur::class, 'genere_par'); }
    public function validePar(): BelongsTo       { return $this->belongsTo(Utilisateur::class, 'valide_par'); }
}


// ══════════════════════════════════════════════════════════════
//  MODULE E — FINANCIER SCOLARITÉ
// ══════════════════════════════════════════════════════════════
