<?php

namespace App\Services;

use App\Events\ConnexionEchouee;
use App\Events\ConnexionReussie;
use App\Jobs\LogConnexionJob;
use App\Models\SessionUtilisateur;
use App\Models\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthService
{
    private const MAX_TENTATIVES = 5;
    private const DUREE_BLOCAGE  = 900;

    public function __construct(
        private readonly JwtService        $jwtService,
        private readonly PermissionService $permissionService,
    ) {}

    public function login(string $email, string $motDePasse, Request $request): array
    {
        $cleRL = 'login:' . $request->ip() . ':' . $email;

        if (RateLimiter::tooManyAttempts($cleRL, self::MAX_TENTATIVES)) {
            $sec = RateLimiter::availableIn($cleRL);
            throw new \RuntimeException("Trop de tentatives. Réessayez dans {$sec} secondes.", 429);
        }

        $utilisateur = Utilisateur::where('email', $email)->first();

        if (!$utilisateur || !Hash::check($motDePasse, $utilisateur->mot_de_passe_hash)) {
            RateLimiter::hit($cleRL, self::DUREE_BLOCAGE);
            if ($utilisateur) {
                LogConnexionJob::dispatch($utilisateur->id, null, 'connexion_echouee', $request->ip(), $request->userAgent(), 'Mot de passe incorrect');
                event(new ConnexionEchouee($utilisateur, $request));
            }
            throw new \RuntimeException('Identifiants incorrects.', 401);
        }

        if (!$utilisateur->est_actif) {
            throw new \RuntimeException('Ce compte est désactivé.', 403);
        }

        if ($utilisateur->etablissement_id !== null) {
            $etab = $utilisateur->etablissement;
            if (!$etab || !$etab->estValide()) {
                throw new \RuntimeException("L'abonnement de votre établissement est inactif.", 403);
            }
        }

        RateLimiter::clear($cleRL);

        $token        = $this->jwtService->genererToken($utilisateur);
        $refreshToken = $this->jwtService->genererRefreshToken($utilisateur);

        $session = SessionUtilisateur::create([
            'utilisateur_id'   => $utilisateur->id,
            'token'            => $token,
            'ip_adresse'       => $request->ip(),
            'agent_navigateur' => $request->userAgent(),
            'appareil'         => str_contains($request->userAgent() ?? '', 'Mobile') ? 'Mobile' : 'Desktop',
            'est_active'       => true,
            'expire_le'        => now()->addMinutes(config('jwt.ttl', 60)),
        ]);

        $utilisateur->updateQuietly(['derniere_connexion' => now()]);

        LogConnexionJob::dispatch($utilisateur->id, $session->id, 'connexion_reussie', $request->ip(), $request->userAgent());
        event(new ConnexionReussie($utilisateur, $session, $request));

        $permissions = $this->permissionService->permissionsEffectives($utilisateur);

        return [
            'access_token'  => $token,
            'refresh_token' => $refreshToken,
            'token_type'    => 'Bearer',
            'expire_dans'   => config('jwt.ttl', 60) * 60,
            'utilisateur'   => [
                'id'               => $utilisateur->id,
                'uuid'             => $utilisateur->uuid,
                'nom'              => $utilisateur->nom,
                'prenom'           => $utilisateur->prenom,
                'email'            => $utilisateur->email,
                'genre'            => $utilisateur->genre,
                'photo_url'        => $utilisateur->photo_url,
                'est_actif'        => $utilisateur->est_actif,
                'etablissement_id' => $utilisateur->etablissement_id,
                'role'             => [
                    'id'          => $utilisateur->role->id,
                    'code'        => $utilisateur->role->code,
                    'nom'         => $utilisateur->role->nom,
                    'est_systeme' => $utilisateur->role->est_systeme,
                ],
            ],
            'permissions' => $permissions,
        ];
    }

    public function logout(Utilisateur $utilisateur, string $token, Request $request): void
    {
        $this->jwtService->invaliderToken();
        SessionUtilisateur::where('token', $token)->where('utilisateur_id', $utilisateur->id)
            ->update(['est_active' => false, 'ferme_le' => now()]);
        LogConnexionJob::dispatch($utilisateur->id, null, 'deconnexion', $request->ip(), $request->userAgent());
    }

    public function rafraichirToken(Utilisateur $utilisateur, string $ancienToken): array
    {
        $nouveauToken = $this->jwtService->rafraichirToken();
        if (!$nouveauToken) throw new \RuntimeException('Token invalide ou expiré.', 401);
        SessionUtilisateur::where('token', $ancienToken)
            ->update(['token' => $nouveauToken, 'expire_le' => now()->addMinutes(config('jwt.ttl', 60))]);
        return ['access_token' => $nouveauToken, 'token_type' => 'Bearer', 'expire_dans' => config('jwt.ttl', 60) * 60];
    }

    public function moi(Utilisateur $utilisateur): array
    {
        $utilisateur->load('role', 'etablissement');
        return [
            'utilisateur' => [
                'id'               => $utilisateur->id,
                'uuid'             => $utilisateur->uuid,
                'nom'              => $utilisateur->nom,
                'prenom'           => $utilisateur->prenom,
                'email'            => $utilisateur->email,
                'genre'            => $utilisateur->genre,
                'photo_url'        => $utilisateur->photo_url,
                'telephone'        => $utilisateur->telephone,
                'est_actif'        => $utilisateur->est_actif,
                'email_verifie'    => $utilisateur->email_verifie,
                'derniere_connexion'=> $utilisateur->derniere_connexion,
                'etablissement_id' => $utilisateur->etablissement_id,
                'etablissement'    => $utilisateur->etablissement ? [
                    'id'  => $utilisateur->etablissement->id,
                    'nom' => $utilisateur->etablissement->nom,
                ] : null,
                'role' => [
                    'id'          => $utilisateur->role->id,
                    'code'        => $utilisateur->role->code,
                    'nom'         => $utilisateur->role->nom,
                    'est_systeme' => $utilisateur->role->est_systeme,
                ],
            ],
            'permissions' => $this->permissionService->permissionsEffectives($utilisateur),
        ];
    }
}
