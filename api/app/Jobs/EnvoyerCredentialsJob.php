<?php

namespace App\Jobs;

use App\Mail\CredentialsMail;
use App\Models\Utilisateur;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Job : Envoi des credentials par email.
 * Dispatché après création d'un utilisateur ou réinitialisation de mot de passe.
 * Exécuté en queue (non bloquant pour la requête HTTP).
 */
class EnvoyerCredentialsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;        // 3 tentatives avant d'abandonner
    public int $backoff = 60;     // 60s entre chaque tentative

    public function __construct(
        public readonly Utilisateur $utilisateur,
        public readonly string      $motDePasse,
        public readonly bool        $estReinitialisation = false,
    ) {}

    public function handle(): void
    {
        Mail::to($this->utilisateur->email)
            ->send(new CredentialsMail(
                $this->utilisateur,
                $this->motDePasse,
                $this->estReinitialisation
            ));
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error("EnvoyerCredentialsJob échoué pour {$this->utilisateur->email} : {$e->getMessage()}");
    }
}
