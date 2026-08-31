<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $table = 'roles';
    protected $fillable = ['etablissement_id','nom','code','description','est_systeme'];
    protected $casts = ['est_systeme' => 'boolean'];

    public function etablissement(): BelongsTo { return $this->belongsTo(Etablissement::class); }
    public function permissions(): BelongsToMany {
        return $this->belongsToMany(Permission::class, 'role_permissions')->withPivot('cree_le');
    }
    public function utilisateurs(): HasMany { return $this->hasMany(Utilisateur::class, 'role_id'); }
    public function codesPermissions(): array { return $this->permissions()->pluck('code')->toArray(); }
}
