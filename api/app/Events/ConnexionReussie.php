<?php

namespace App\Events;

use App\Models\SessionUtilisateur;
use App\Models\Utilisateur;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class ConnexionReussie
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly Utilisateur        $utilisateur,
        public readonly SessionUtilisateur $session,
        public readonly Request            $request,
    ) {}
}
