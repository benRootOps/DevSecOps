<?php

namespace App\Jobs;

use App\Models\JournalConnexion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LogConnexionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 5;

    public function __construct(
        private readonly int     $utilisateurId,
        private readonly ?int    $sessionId,
        private readonly string  $typeEvenement,
        private readonly ?string $ipAdresse,
        private readonly ?string $agentNavigateur,
        private readonly ?string $detail = null,
    ) {
        $this->onQueue('auth');
    }

    public function handle(): void
    {
        JournalConnexion::create([
            'utilisateur_id'   => $this->utilisateurId,
            'session_id'       => $this->sessionId,
            'type_evenement'   => $this->typeEvenement,
            'ip_adresse'       => $this->ipAdresse,
            'agent_navigateur' => $this->agentNavigateur,
            'detail'           => $this->detail,
        ]);
    }
}
