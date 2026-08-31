<?php

namespace App\Http\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Http\Requests\Role\CreateRoleRequest;
use App\Models\Role;
use App\Models\Utilisateur;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function __construct(private readonly PermissionService $permissionService) {}

    public function index(Request $request): JsonResponse
    {
        $etablissementId = $request->attributes->get('etablissement_id');
        $roles = Role::query()
            ->where(fn($q) => $q->whereNull('etablissement_id')->orWhere('etablissement_id', $etablissementId))
            ->with('permissions')->get();
        return response()->json(['succes' => true, 'donnees' => $roles]);
    }

    public function store(CreateRoleRequest $request): JsonResponse
    {
        try {
            $role = $this->permissionService->creerRole(array_merge(
                $request->validated(),
                ['etablissement_id' => $request->attributes->get('etablissement_id')]
            ));
            return response()->json(['succes' => true, 'message' => 'Rôle créé.', 'donnees' => $role], 201);
        } catch (\RuntimeException $e) {
            return response()->json(['succes' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function show(int $id): JsonResponse
    {
        return response()->json(['succes' => true, 'donnees' => Role::with('permissions')->findOrFail($id)]);
    }

    public function update(CreateRoleRequest $request, int $id): JsonResponse
    {
        try {
            $role = $this->permissionService->mettreAJourRole(Role::findOrFail($id), $request->validated());
            return response()->json(['succes' => true, 'message' => 'Rôle mis à jour.', 'donnees' => $role]);
        } catch (\RuntimeException $e) {
            return response()->json(['succes' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->permissionService->supprimerRole(Role::findOrFail($id));
            return response()->json(['succes' => true, 'message' => 'Rôle supprimé.']);
        } catch (\RuntimeException $e) {
            return response()->json(['succes' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function permissionsUtilisateur(int $id): JsonResponse
    {
        $u = Utilisateur::findOrFail($id);
        return response()->json(['succes' => true, 'donnees' => [
            'overrides'  => $this->permissionService->permissionsUtilisateur($u),
            'effectives' => $this->permissionService->permissionsEffectives($u),
        ]]);
    }

    public function setPermissionUtilisateur(Request $request, int $id): JsonResponse
    {
        $request->validate(['code' => ['required','string','exists:permissions,code'], 'type_acces' => ['required','in:accorder,retirer']]);
        $this->permissionService->definirPermission(Utilisateur::findOrFail($id), $request->input('code'), $request->input('type_acces'), auth()->id());
        return response()->json(['succes' => true, 'message' => "Permission {$request->input('type_acces')}ée."]);
    }

    public function resetPermissionUtilisateur(int $id, string $code): JsonResponse
    {
        $this->permissionService->reinitialiserPermission(Utilisateur::findOrFail($id), $code);
        return response()->json(['succes' => true, 'message' => 'Permission réinitialisée.']);
    }

    public function toutesLesPermissions(): JsonResponse
    {
        return response()->json(['succes' => true, 'donnees' => $this->permissionService->permissionsGroupeesParModule()]);
    }
}
