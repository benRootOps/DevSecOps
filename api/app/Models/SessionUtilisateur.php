<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SessionUtilisateur extends Model
{
    protected $table = 'sessions';
    public $timestamps = false;
    protected $fillable = ['utilisateur_id','token','ip_adresse','agent_navigateur','appareil','localisation','est_active','expire_le','ferme_le'];
    protected $casts = ['est_active' => 'boolean','expire_le' => 'datetime','ferme_le' => 'datetime','cree_le' => 'datetime'];

    protected static function boot(): void {
        parent::boot();
        static::creating(fn($m) => $m->uuid = (string) Str::uuid());
    }

    public function utilisateur(): BelongsTo { return $this->belongsTo(Utilisateur::class); }
    public function estExpiree(): bool        { return $this->expire_le->isPast(); }
}
