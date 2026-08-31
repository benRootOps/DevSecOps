<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 5 — Étudiants : dossier étudiant (cf. bd-Edusphere_v2_0, §13).
return new class extends Migration {
    public function up(): void {
        Schema::create('etudiants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('utilisateur_id')->nullable()->unique()->constrained('utilisateurs')->nullOnDelete();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->string('matricule', 50);
            $table->string('nom', 100);
            $table->string('prenom', 100);
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance', 150)->nullable();
            $table->string('genre', 20)->nullable();
            $table->string('nationalite', 100)->default('Camerounaise');
            $table->string('email', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->text('adresse')->nullable();
            $table->string('photo_url', 500)->nullable();
            $table->string('tuteur_nom', 200)->nullable();
            $table->string('tuteur_telephone', 30)->nullable();
            $table->boolean('est_actif')->default(true);
            $table->timestamps();
            $table->unique(['etablissement_id', 'matricule']);
            $table->index('etablissement_id');
        });
    }
    public function down(): void { Schema::dropIfExists('etudiants'); }
};
