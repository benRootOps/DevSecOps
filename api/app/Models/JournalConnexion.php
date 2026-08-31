<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalConnexion extends Model
{
    protected $table = 'journal_connexions';
    public $timestamps = false;
    protected $fillable = ['utilisateur_id','session_id','type_evenement','ip_adresse','agent_navigateur','detail'];
    protected $casts = ['cree_le' => 'datetime'];

    public function utilisateur(): BelongsTo { return $this->belongsTo(Utilisateur::class); }
    public function session(): BelongsTo     { return $this->belongsTo(SessionUtilisateur::class, 'session_id'); }
}
