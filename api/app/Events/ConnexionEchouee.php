<?php

namespace App\Events;

use App\Models\Utilisateur;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\SerializesModels;

class ConnexionEchouee
{
    use Dispatchable, SerializesModels;
    public function __construct(
        public readonly Utilisateur $utilisateur,
        public readonly Request     $request,
    ) {}
}
