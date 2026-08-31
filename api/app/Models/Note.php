<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// ══════════════════════════════════════════════════════════════
//  Note
// ══════════════════════════════════════════════════════════════
class Note extends Model
{
    public $timestamps  = false;
    protected $table    = 'notes';
    protected $fillable = [
        'etudiant_id','matiere_id','session_examen_id','type_note',
        'valeur','bareme','observation','saisie_par','valide_par','est_validee',
    ];
    protected $casts = [
        'valeur'       => 'float',
        'bareme'       => 'float',
        'est_validee'  => 'boolean',
        'cree_le'      => 'datetime',
        'mis_a_jour_le'=> 'datetime',
    ];

    public const TYPES = ['CC', 'TP', 'Examen', 'Rattrapage'];

    public function etudiant(): BelongsTo       { return $this->belongsTo(Etudiant::class); }
    public function matiere(): BelongsTo        { return $this->belongsTo(Matiere::class); }
    public function sessionExamen(): BelongsTo  { return $this->belongsTo(SessionExamen::class); }
    public function saisiePar(): BelongsTo      { return $this->belongsTo(Utilisateur::class, 'saisie_par'); }
    public function validePar(): BelongsTo      { return $this->belongsTo(Utilisateur::class, 'valide_par'); }

    /** Note ramenée sur 20 */
    public function getNoteSur20Attribute(): float
    {
        if ($this->bareme <= 0) return 0;
        return round($this->valeur * 20 / $this->bareme, 2);
    }
}
