<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// ══════════════════════════════════════════════════════════════
//  Conflit emploi du temps
// ══════════════════════════════════════════════════════════════
class ConflitEmploiDuTemps extends Model
{
    public $timestamps  = false;
    protected $table    = 'conflits_emploi_temps';
    protected $fillable = ['seance_id','type_conflit','detail','resolu'];
    protected $casts    = ['resolu' => 'boolean'];

    public function seance(): BelongsTo { return $this->belongsTo(Seance::class); }
}
