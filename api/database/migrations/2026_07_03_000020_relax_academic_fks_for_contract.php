<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Contrat d'API §4 : les payloads minimaux du contrat ne fournissent pas
 * l'année académique (classes) ni la filière/niveau (UE). On rend ces FK
 * nullables — additif, sans changement de logique ; elles restent renseignables.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE classes MODIFY annee_academique_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE unites_enseignement MODIFY filiere_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE unites_enseignement MODIFY niveau_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE classes MODIFY annee_academique_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE unites_enseignement MODIFY filiere_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE unites_enseignement MODIFY niveau_id BIGINT UNSIGNED NOT NULL');
    }
};