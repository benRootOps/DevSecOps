<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edusphere — Vos identifiants</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f4f6fa; margin: 0; padding: 0; color: #1a1d27; }
        .wrapper { max-width: 560px; margin: 40px auto; }
        .header { background: #6366F1; padding: 32px 40px; border-radius: 12px 12px 0 0; text-align: center; }
        .header h1 { color: #fff; margin: 0; font-size: 24px; letter-spacing: -0.5px; }
        .header p  { color: #c7d2fe; margin: 6px 0 0; font-size: 13px; }
        .body { background: #fff; padding: 36px 40px; border: 1px solid #e5e7eb; }
        .greeting { font-size: 16px; font-weight: 600; margin-bottom: 12px; }
        .intro { color: #4b5563; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
        .creds { background: #f8f9ff; border: 1px solid #e0e7ff; border-radius: 8px; padding: 20px 24px; margin-bottom: 24px; }
        .creds table { width: 100%; border-collapse: collapse; }
        .creds td { padding: 8px 0; font-size: 14px; }
        .creds td:first-child { color: #6b7280; width: 140px; }
        .creds td:last-child { font-weight: 600; color: #1a1d27; font-family: monospace; font-size: 15px; }
        .url { background: #f8f9ff; border: 1px solid #e0e7ff; border-radius: 8px; padding: 14px 20px; margin-bottom: 24px; text-align: center; }
        .url a { color: #6366F1; font-size: 14px; text-decoration: none; font-weight: 600; }
        .warning { background: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 14px 18px; font-size: 13px; color: #92400e; margin-bottom: 24px; }
        .warning strong { display: block; margin-bottom: 4px; }
        .footer { background: #f4f6fa; padding: 20px 40px; border-radius: 0 0 12px 12px; text-align: center; font-size: 12px; color: #9ca3af; border: 1px solid #e5e7eb; border-top: none; }
    </style>
</head>
<body>
<div class="wrapper">

    <div class="header">
        <h1>Edusphere</h1>
        <p>Plateforme de gestion universitaire</p>
    </div>

    <div class="body">
        <div class="greeting">
            Bonjour {{ $utilisateur->prenom }} {{ $utilisateur->nom }},
        </div>

        <div class="intro">
            @if($estReinitialisation)
                Votre mot de passe a été réinitialisé par un administrateur.
                Voici vos nouveaux identifiants de connexion.
            @else
                Votre compte Edusphere vient d'être créé.
                Voici vos identifiants pour accéder à la plateforme.
            @endif
        </div>

        <div class="creds">
            <table>
                <tr>
                    <td>Adresse email</td>
                    <td>{{ $utilisateur->email }}</td>
                </tr>
                <tr>
                    <td>Mot de passe</td>
                    <td>{{ $motDePasse }}</td>
                </tr>
                <tr>
                    <td>Rôle</td>
                    <td>{{ $utilisateur->role->nom ?? '—' }}</td>
                </tr>
                @if($utilisateur->etablissement)
                <tr>
                    <td>Établissement</td>
                    <td>{{ $utilisateur->etablissement->nom }}</td>
                </tr>
                @endif
            </table>
        </div>

        <div class="url">
            <a href="{{ config('app.frontend_url', config('app.url')) }}">
                🔗 Accéder à Edusphere
            </a>
        </div>

        <div class="warning">
            <strong>⚠️ Important</strong>
            Veuillez changer votre mot de passe dès votre première connexion.
            Ne partagez jamais vos identifiants.
        </div>

        <div style="font-size: 13px; color: #6b7280; line-height: 1.6;">
            Si vous n'êtes pas à l'origine de cette demande ou si vous pensez
            avoir reçu cet email par erreur, veuillez contacter votre administrateur.
        </div>
    </div>

    <div class="footer">
        © {{ date('Y') }} Edusphere — Tous droits réservés<br>
        Cet email est généré automatiquement, merci de ne pas y répondre.
    </div>

</div>
</body>
</html>
