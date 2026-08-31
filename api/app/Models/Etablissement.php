<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Etablissement extends Model
{
    protected $table = 'etablissements';
    protected $fillable = ['nom','adresse','ville','pays','telephone','email','est_actif'];
    protected $casts = ['est_actif' => 'boolean'];

    protected static function boot(): void {
        parent::boot();
        static::creating(fn($m) => $m->uuid = (string) Str::uuid());
    }

    public function utilisateurs(): HasMany { return $this->hasMany(Utilisateur::class, 'etablissement_id'); }
    public function roles(): HasMany        { return $this->hasMany(Role::class, 'etablissement_id'); }
    public function demandes(): HasMany     { return $this->hasMany(DemandeCompte::class, 'etablissement_id'); }

    public function estValide(): bool { return $this->est_actif; }
}
