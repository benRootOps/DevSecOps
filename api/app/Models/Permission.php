<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'permissions';
    public $timestamps = false;
    protected $fillable = ['code','libelle','module','description'];

    public function roles(): BelongsToMany {
        return $this->belongsToMany(Role::class, 'role_permissions')->withPivot('cree_le');
    }
    public function utilisateurs(): BelongsToMany {
        return $this->belongsToMany(Utilisateur::class, 'utilisateur_permissions')
                    ->withPivot('type_acces','accorde_par','accorde_le');
    }
}
