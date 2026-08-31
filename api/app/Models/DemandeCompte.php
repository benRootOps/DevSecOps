<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DemandeCompte extends Model
{
    protected $table = 'demandes_compte';
    public $timestamps = false;

    protected $fillable = [
        'type_demande','donnees','etablissement_id','statut',
        'traite_par','traite_le','motif_rejet',
        'utilisateur_cree_id','etablissement_cree_id',
    ];

    protected $casts = [
        'donnees'    => 'array',
        'soumis_le'  => 'datetime',
        'traite_le'  => 'datetime',
    ];

    protected static function boot(): void {
        parent::boot();
        static::creating(fn($m) => $m->uuid = (string) Str::uuid());
    }

    public function etablissement(): BelongsTo       { return $this->belongsTo(Etablissement::class); }
    public function traitePar(): BelongsTo            { return $this->belongsTo(Utilisateur::class, 'traite_par'); }
    public function utilisateurCree(): BelongsTo      { return $this->belongsTo(Utilisateur::class, 'utilisateur_cree_id'); }
    public function etablissementCree(): BelongsTo    { return $this->belongsTo(Etablissement::class, 'etablissement_cree_id'); }

    public function estEnAttente(): bool { return $this->statut === 'en_attente'; }
    public function estValidee(): bool   { return $this->statut === 'validee'; }
    public function estRejetee(): bool   { return $this->statut === 'rejetee'; }
}
