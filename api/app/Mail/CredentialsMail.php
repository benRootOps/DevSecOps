<?php

namespace App\Mail;

use App\Models\Utilisateur;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email de bienvenue avec credentials.
 * Envoyé à la création d'un compte ou après réinitialisation du mot de passe.
 *
 * Vue : resources/views/emails/credentials.blade.php
 */
class CredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Utilisateur $utilisateur,
        public readonly string      $motDePasse,
        public readonly bool        $estReinitialisation = false,
    ) {}

    public function envelope(): Envelope
    {
        $sujet = $this->estReinitialisation
            ? 'Edusphere — Réinitialisation de votre mot de passe'
            : 'Edusphere — Bienvenue ! Vos identifiants de connexion';

        return new Envelope(subject: $sujet);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.credentials');
    }

    public function attachments(): array
    {
        return [];
    }
}
