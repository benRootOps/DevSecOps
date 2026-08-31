<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 10 — Documents officiels (cf. bd-Edusphere_v2_0, §21).
// Métadonnées seulement ; le PDF sera généré à la demande via un moteur de templates.
return new class extends Migration {
    public function up(): void {
        Schema::create('documents_officiels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->nullable()->constrained('annees_academiques')->nullOnDelete();
            $table->string('type_document', 100); // Certificat scolarité / Attestation inscription / Attestation réussite / Relevé de notes / Diplôme...
            $table->string('numero_document', 50)->unique();
            $table->string('fichier_url', 500)->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('genere_le')->nullable();
            $table->foreignId('genere_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->foreignId('valide_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('valide_le')->nullable();
            $table->boolean('est_valide')->default(false);
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('etudiant_id');
            $table->index(['etablissement_id', 'type_document']);
        });
    }
    public function down(): void { Schema::dropIfExists('documents_officiels'); }
};
