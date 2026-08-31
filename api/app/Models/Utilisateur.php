<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;


class Utilisateur extends Authenticatable implements JWTSubject
{
    protected $table = 'utilisateurs';
    protected $fillable = [
        'etablissement_id','role_id','nom','prenom','email','mot_de_passe_hash',
        'telephone','photo_url','genre','date_naissance','est_actif','email_verifie',
        'token_verification','token_reset_mdp','token_reset_expire',
        'derniere_connexion','tentatives_connexion','bloque_jusqu_a',
    ];
    protected $hidden = ['mot_de_passe_hash','token_verification','token_reset_mdp'];
    protected $casts = [
        'est_actif'           => 'boolean',
        'email_verifie'       => 'boolean',
        'date_naissance'      => 'date',
        'token_reset_expire'  => 'datetime',
        'derniere_connexion'  => 'datetime',
        'bloque_jusqu_a'      => 'datetime',
        'tentatives_connexion'=> 'integer',
    ];

    public function getAuthPassword(): string { return $this->mot_de_passe_hash; }

    // JWT
    public function getJWTIdentifier(): mixed { return $this->getKey(); }
    public function getJWTCustomClaims(): array {
        return [
            'uuid'             => $this->uuid,
            'email'            => $this->email,
            'role'             => $this->role?->code,
            'etablissement_id' => $this->etablissement_id,
        ];
    }

    protected static function boot(): void {
        parent::boot();
        static::creating(fn($m) => $m->uuid = (string) Str::uuid());
    }

    // Relations
    public function etablissement(): BelongsTo  { return $this->belongsTo(Etablissement::class); }
    public function role(): BelongsTo           { return $this->belongsTo(Role::class); }
    public function permissionsGranulaires(): BelongsToMany {
        return $this->belongsToMany(Permission::class, 'utilisateur_permissions')
                    ->withPivot('type_acces','accorde_par','accorde_le');
    }
    public function sessions(): HasMany         { return $this->hasMany(SessionUtilisateur::class, 'utilisateur_id'); }
    public function journalConnexions(): HasMany { return $this->hasMany(JournalConnexion::class, 'utilisateur_id'); }

    // Helpers
    public function estBloque(): bool      { return $this->bloque_jusqu_a?->isFuture() ?? false; }
    public function estSuperAdmin(): bool  { return $this->role?->code === 'super_admin'; }
    public function nomComplet(): string   { return "{$this->prenom} {$this->nom}"; }
}
