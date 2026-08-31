<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Utilisateur;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    private const CACHE_TTL    = 300;
    private const CACHE_PREFIX = 'edusphere:permissions:';

    public function permissionsEffectives(Utilisateur $utilisateur): array
    {
        return Cache::remember(
            self::CACHE_PREFIX . $utilisateur->id,
            self::CACHE_TTL,
            fn() => $this->calculerPermissions($utilisateur)
        );
    }

    private function calculerPermissions(Utilisateur $utilisateur): array
    {
        $permissionsRole = Permission::query()
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->where('role_permissions.role_id', $utilisateur->role_id)
            ->pluck('permissions.code')
            ->toArray();

        $overrides = DB::table('utilisateur_permissions')
            ->join('permissions', 'permissions.id', '=', 'utilisateur_permissions.permission_id')
            ->where('utilisateur_permissions.utilisateur_id', $utilisateur->id)
            ->select('permissions.code', 'utilisateur_permissions.type_acces')
            ->get();

        $accordees  = $overrides->where('type_acces', 'accorder')->pluck('code')->toArray();
        $retirees   = $overrides->where('type_acces', 'retirer')->pluck('code')->toArray();
        $effectives = array_unique(array_merge($permissionsRole, $accordees));

        return array_values(array_diff($effectives, $retirees));
    }

    public function peut(Utilisateur $utilisateur, string $code): bool
    {
        return in_array($code, $this->permissionsEffectives($utilisateur), true);
    }

    public function peutTout(Utilisateur $utilisateur, array $codes): bool
    {
        $effectives = $this->permissionsEffectives($utilisateur);
        foreach ($codes as $code) {
            if (!in_array($code, $effectives, true)) return false;
        }
        return true;
    }

    public function definirPermission(Utilisateur $utilisateur, string $codePermission, string $typeAcces, int $accordePar): void
    {
        $permission = Permission::where('code', $codePermission)->firstOrFail();
        DB::table('utilisateur_permissions')->updateOrInsert(
            ['utilisateur_id' => $utilisateur->id, 'permission_id' => $permission->id],
            ['type_acces' => $typeAcces, 'accorde_par' => $accordePar, 'accorde_le' => now()]
        );
        $this->invaliderCache($utilisateur->id);
    }

    public function reinitialiserPermission(Utilisateur $utilisateur, string $codePermission): void
    {
        $permission = Permission::where('code', $codePermission)->firstOrFail();
        DB::table('utilisateur_permissions')
            ->where('utilisateur_id', $utilisateur->id)
            ->where('permission_id', $permission->id)
            ->delete();
        $this->invaliderCache($utilisateur->id);
    }

    public function invaliderCache(int $utilisateurId): void
    {
        Cache::forget(self::CACHE_PREFIX . $utilisateurId);
    }

    public function invaliderCacheRole(int $roleId): void
    {
        Utilisateur::where('role_id', $roleId)->pluck('id')
            ->each(fn($id) => Cache::forget(self::CACHE_PREFIX . $id));
    }

    public function creerRole(array $donnees): Role
    {
        return DB::transaction(function () use ($donnees) {
            $role = Role::create($donnees);
            if (!empty($donnees['permissions'])) {
                $this->syncPermissionsRole($role, $donnees['permissions']);
            }
            return $role->load('permissions');
        });
    }

    public function mettreAJourRole(Role $role, array $donnees): Role
    {
        if ($role->est_systeme) throw new \RuntimeException('Les rôles système ne peuvent pas être modifiés.');
        return DB::transaction(function () use ($role, $donnees) {
            $role->update($donnees);
            if (isset($donnees['permissions'])) {
                $this->syncPermissionsRole($role, $donnees['permissions']);
                $this->invaliderCacheRole($role->id);
            }
            return $role->fresh('permissions');
        });
    }

    public function supprimerRole(Role $role): void
    {
        if ($role->est_systeme) throw new \RuntimeException('Les rôles système ne peuvent pas être supprimés.');
        if ($role->utilisateurs()->exists()) throw new \RuntimeException('Ce rôle est assigné à des utilisateurs actifs.');
        $this->invaliderCacheRole($role->id);
        $role->delete();
    }

    private function syncPermissionsRole(Role $role, array $codePermissions): void
    {
        $ids = Permission::whereIn('code', $codePermissions)->pluck('id');
        $role->permissions()->sync(
            $ids->mapWithKeys(fn($id) => [$id => ['cree_le' => now()]])->toArray()
        );
    }

    public function toutesLesPermissions(): \Illuminate\Support\Collection
    {
        return Cache::remember('edusphere:all_permissions', 3600, fn() =>
            Permission::orderBy('module')->orderBy('code')->get()
        );
    }

    public function permissionsGroupeesParModule(): \Illuminate\Support\Collection
    {
        return $this->toutesLesPermissions()->groupBy('module');
    }

    public function permissionsUtilisateur(Utilisateur $utilisateur): \Illuminate\Support\Collection
    {
        return DB::table('utilisateur_permissions')
            ->join('permissions', 'permissions.id', '=', 'utilisateur_permissions.permission_id')
            ->where('utilisateur_permissions.utilisateur_id', $utilisateur->id)
            ->select('permissions.code','permissions.libelle','permissions.module',
                     'utilisateur_permissions.type_acces','utilisateur_permissions.accorde_le')
            ->get();
    }
}
