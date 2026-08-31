<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        
        // ── Établissement ────────────────────────────────────────────
        $etabId = DB::table('etablissements')->insertGetId([
            'uuid' => Str::uuid(),
            'nom' => 'Université Test UY1',
            'ville' => 'Yaoundé',
            'pays' => 'Cameroun',
            'email' => 'contact@uy1.test',
            'est_actif' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ── Rôles ────────────────────────────────────────────────────
        $roleSuperAdmin = DB::table('roles')->insertGetId([
            'etablissement_id' => null,
            'nom' => 'Super Admin', 'code' => 'super_admin',
            'est_systeme' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleAdmin = DB::table('roles')->insertGetId([
            'etablissement_id' => $etabId,
            'nom' => 'Admin Universitaire', 'code' => 'admin_universitaire',
            'est_systeme' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleEnseignant = DB::table('roles')->insertGetId([
            'etablissement_id' => $etabId,
            'nom' => 'Enseignant', 'code' => 'enseignant',
            'est_systeme' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $roleEtudiant = DB::table('roles')->insertGetId([
            'etablissement_id' => $etabId,
            'nom' => 'Étudiant', 'code' => 'etudiant',
            'est_systeme' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $uidSuperAdmin = DB::table('utilisateurs')->insertGetId([
            'uuid' => Str::uuid(), 'etablissement_id' => null, 'role_id' => $roleSuperAdmin,
            'nom' => 'Admin', 'prenom' => 'Super', 'email' => 'superadmin@univora.cm',
            'mot_de_passe_hash' => Hash::make('Univora@2026!'),
            'est_actif' => true, 'email_verifie' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $uidAdmin = DB::table('utilisateurs')->insertGetId([
            'uuid' => Str::uuid(), 'etablissement_id' => $etabId, 'role_id' => $roleAdmin,
            'nom' => 'Nguema', 'prenom' => 'Paul', 'email' => 'admin@uy1.cm',
            'mot_de_passe_hash' => Hash::make('Admin@123'),
            'est_actif' => true, 'email_verifie' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $uidEnseignant = DB::table('utilisateurs')->insertGetId([
            'uuid' => Str::uuid(), 'etablissement_id' => $etabId, 'role_id' => $roleEnseignant,
            'nom' => 'Fotso', 'prenom' => 'Jean', 'email' => 'prof@uy1.cm',
            'mot_de_passe_hash' => Hash::make('Prof@123'),
            'est_actif' => true, 'email_verifie' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $uidEtudiant = DB::table('utilisateurs')->insertGetId([
            'uuid' => Str::uuid(), 'etablissement_id' => $etabId, 'role_id' => $roleEtudiant,
            'nom' => 'Mballa', 'prenom' => 'Sarah', 'email' => 'etudiant@uy1.cm',
            'mot_de_passe_hash' => Hash::make('Etud@123'),
            'est_actif' => true, 'email_verifie' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Structure académique ─────────────────────────────────────
        $anneeId = DB::table('annees_academiques')->insertGetId([
            'etablissement_id' => $etabId, 'libelle' => '2026-2027',
            'date_debut' => '2026-09-01', 'date_fin' => '2027-07-31',
            'est_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $faculteId = DB::table('facultes')->insertGetId([
            'etablissement_id' => $etabId, 'nom' => 'Faculté des Sciences', 'code' => 'FS',
            'est_actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $departementId = DB::table('departements')->insertGetId([
            'etablissement_id' => $etabId, 'faculte_id' => $faculteId,
            'nom' => 'Informatique', 'code' => 'INFO',
            'est_actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $filiereId = DB::table('filieres')->insertGetId([
            'etablissement_id' => $etabId, 'departement_id' => $departementId,
            'nom' => 'Génie Logiciel', 'code' => 'GL', 'type_formation' => 'Licence',
            'duree_annees' => 3, 'est_actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $niveauId = DB::table('niveaux')->insertGetId([
            'etablissement_id' => $etabId, 'filiere_id' => $filiereId,
            'libelle' => 'L1', 'ordre' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $semestreId = DB::table('semestres')->insertGetId([
            'etablissement_id' => $etabId, 'annee_academique_id' => $anneeId,
            'libelle' => 'S1', 'date_debut' => '2026-09-01', 'date_fin' => '2027-01-31',
            'est_actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $classeId = DB::table('classes')->insertGetId([
            'etablissement_id' => $etabId, 'niveau_id' => $niveauId, 'annee_academique_id' => $anneeId,
            'nom' => 'L1 GL A', 'capacite_max' => 50, 'est_actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Enseignant & Étudiant ──────────────────────────────────────
        $enseignantId = DB::table('enseignants')->insertGetId([
            'utilisateur_id' => $uidEnseignant, 'etablissement_id' => $etabId,
            'matricule' => 'ENS-001', 'specialite' => 'Génie Logiciel', 'grade' => 'Chargé de cours',
            'type_contrat' => 'Permanent', 'est_actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('etudiants')->insert([
            'uuid' => Str::uuid(), 'utilisateur_id' => $uidEtudiant, 'etablissement_id' => $etabId,
            'matricule' => 'ETU-2026-001', 'nom' => 'Mballa', 'prenom' => 'Sarah',
            'genre' => 'F', 'nationalite' => 'Camerounaise', 'email' => 'etudiant@uy1.cm',
            'est_actif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Unité d'enseignement & Matière ───────────────────────────
        $uniteId = DB::table('unites_enseignement')->insertGetId([
            'etablissement_id' => $etabId, 'semestre_id' => $semestreId,
            'filiere_id' => $filiereId, 'niveau_id' => $niveauId,
            'code' => 'UE-INFO101', 'intitule' => 'Fondamentaux de Programmation',
            'credits' => 6, 'coefficient' => 2, 'est_actif' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $matiereId = DB::table('matieres')->insertGetId([
            'etablissement_id' => $etabId, 'unite_id' => $uniteId,
            'code' => 'INFO101', 'intitule' => 'Algorithmique', 'volume_horaire' => 45,
            'coefficient' => 3, 'type_evaluation' => 'Mixte',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // ── Affectation enseignant ,matière, classe ─────────────────
        DB::table('affectations_cours')->insert([
            'etablissement_id' => $etabId, 'enseignant_id' => $enseignantId,
            'matiere_id' => $matiereId, 'classe_id' => $classeId, 'charge_horaire' => 45,
            'created_at' => now(), 'updated_at' => now(),
        ]);


            // ── Permissions 
        $permissionsCodes = [
            'etablissements.voir', 'etablissements.modifier',
            'utilisateurs.voir', 'utilisateurs.creer', 'utilisateurs.modifier', 'utilisateurs.supprimer', 'utilisateurs.permissions',
            'enseignants.voir', 'enseignants.creer', 'enseignants.modifier',
            'etudiants.creer',
            'demandes.voir', 'demandes.traiter',
            'emploi_temps.voir', 'emploi_temps.creer', 'emploi_temps.modifier', 'emploi_temps.supprimer', 'cours.affecter',
            'presences.voir', 'presences.saisir', 'presences.statistiques',
            'examens.voir', 'examens.creer', 'examens.modifier', 'examens.cloturer',
            'notes.voir', 'notes.saisir', 'notes.valider',
            'deliberations.voir', 'deliberations.creer', 'deliberations.cloturer',
            'bulletins.voir', 'bulletins.generer', 'bulletins.publier',
            'documents.voir', 'documents.generer', 'documents.valider',
            'financier.voir', 'financier.frais.creer', 'financier.frais.modifier', 'financier.versement.voir', 'financier.saisir', 'financier.rapports',
            'plans.voir', 'plans.gerer',
            'abonnements.voir', 'abonnements.souscrire', 'abonnements.gerer',
            'paiements.voir', 'paiements.initier',
            'factures.voir', 'factures.generer',
        ];

        $permId = []; // code => id
        foreach ($permissionsCodes as $code) {
            [$module] = explode('.', $code);
            $permId[$code] = DB::table('permissions')->insertGetId([
                'code' => $code,
                'libelle' => ucfirst(str_replace(['.', '_'], [' ', ' '], $code)),
                'module' => $module,
                'cree_le' => now(),
            ]);
        }

        // ── Attribution par rôle
        // Super Admin : gestion de la PLATEFORME uniquement — jamais les données
        // académiques/financières internes à une université.
        $permsSuperAdmin = [
            'etablissements.voir', 'etablissements.modifier',
            'demandes.voir', 'demandes.traiter',
            'plans.voir', 'plans.gerer',
            'abonnements.voir', 'abonnements.gerer',
            'paiements.voir',
            'factures.voir',
        ];

        // Admin Universitaire : pilotage complet de SA PROPRE université
        // (scopé par etablissement_id côté backend), y compris son abonnement —
        // mais jamais le catalogue de plans ni la gestion d'autres établissements.
        $permsAdminUniv = [
            'utilisateurs.voir', 'utilisateurs.creer', 'utilisateurs.modifier', 'utilisateurs.supprimer', 'utilisateurs.permissions',
            'enseignants.voir', 'enseignants.creer', 'enseignants.modifier',
            'etudiants.creer',
            'demandes.voir', 'demandes.traiter',
            'emploi_temps.voir', 'emploi_temps.creer', 'emploi_temps.modifier', 'emploi_temps.supprimer', 'cours.affecter',
            'presences.voir', 'presences.saisir', 'presences.statistiques',
            'examens.voir', 'examens.creer', 'examens.modifier', 'examens.cloturer',
            'notes.voir', 'notes.saisir', 'notes.valider',
            'deliberations.voir', 'deliberations.creer', 'deliberations.cloturer',
            'bulletins.voir', 'bulletins.generer', 'bulletins.publier',
            'documents.voir', 'documents.generer', 'documents.valider',
            'financier.voir', 'financier.frais.creer', 'financier.frais.modifier', 'financier.versement.voir', 'financier.saisir', 'financier.rapports',
            'abonnements.voir', 'abonnements.souscrire',
            'paiements.initier',
            'factures.voir', 'factures.generer',
        ];

        // Enseignant : consultation/saisie limitées à son propre enseignement
        $permsEnseignant = ['emploi_temps.voir', 'presences.voir', 'presences.saisir', 'notes.voir', 'notes.saisir'];

        // Étudiant : lecture seule de son propre dossier
        $permsEtudiant = ['emploi_temps.voir', 'notes.voir', 'bulletins.voir', 'documents.voir'];

        $attribuer = function (int $roleId, array $codes) use ($permId) {
            DB::table('role_permissions')->insert(array_map(fn ($code) => [
                'role_id' => $roleId,
                'permission_id' => $permId[$code],
                'cree_le' => now(),
            ], $codes));
        };

        $attribuer($roleSuperAdmin, $permsSuperAdmin);
        $attribuer($roleAdmin, $permsAdminUniv);
        $attribuer($roleEnseignant, $permsEnseignant);
        $attribuer($roleEtudiant, $permsEtudiant);
    }
}