<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

// ══════════════════════════════════════════════════════════════
//  MoyenneSemestre
// ══════════════════════════════════════════════════════════════
class MoyenneSemestre extends Model
{
    public $timestamps  = false;
    protected $table    = 'moyennes_semestre';
    protected $fillable = [
        'etudiant_id','semestre_id','session_examen_id',
        'moyenne_generale','total_credits','credits_obtenus',
        'rang','mention','est_valide',
    ];
    protected $casts = [
        'moyenne_generale' => 'float',
        'est_valide'       => 'boolean',
        'calcule_le'       => 'datetime',
    ];

    public const MENTIONS = ['Insuffisant', 'Passable', 'Assez bien', 'Bien', 'Très bien', 'Excellent'];

    public function etudiant(): BelongsTo      { return $this->belongsTo(Etudiant::class); }
    public function semestre(): BelongsTo      { return $this->belongsTo(Semestre::class); }
    public function sessionExamen(): BelongsTo { return $this->belongsTo(SessionExamen::class); }

    public static function calculerMention(float $moyenne): string
    {
        return match(true) {
            $moyenne >= 16 => 'Très bien',
            $moyenne >= 14 => 'Bien',
            $moyenne >= 12 => 'Assez bien',
            $moyenne >= 10 => 'Passable',
            default        => 'Insuffisant',
        };
    }
}
