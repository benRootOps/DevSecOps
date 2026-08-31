<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// ══════════════════════════════════════════════════════════════
//  MoyenneUe
// ══════════════════════════════════════════════════════════════
class MoyenneUe extends Model
{
    public $timestamps  = false;
    protected $table    = 'moyennes_ue';
    protected $fillable = [
        'etudiant_id','unite_id','session_examen_id',
        'moyenne','credits_obtenus','est_validee',
    ];
    protected $casts = [
        'moyenne'        => 'float',
        'est_validee'    => 'boolean',
        'calcule_le'     => 'datetime',
    ];

    public function etudiant(): BelongsTo      { return $this->belongsTo(Etudiant::class); }
    public function unite(): BelongsTo         { return $this->belongsTo(UniteEnseignement::class, 'unite_id'); }
    public function sessionExamen(): BelongsTo { return $this->belongsTo(SessionExamen::class); }
}
