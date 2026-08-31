<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 5 — Étudiants : documents (cf. bd-Edusphere_v2_0, §13).
// Sous-ressource d'un étudiant : isolation tenant via le parent.
return new class extends Migration {
    public function up(): void {
        Schema::create('documents_etudiants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->string('type_document', 100);  // 'Certificat de naissance', 'Baccalauréat', 'Photo'...
            $table->string('intitule', 200)->nullable();
            $table->string('fichier_url', 500);
            $table->foreignId('telecharge_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamps();
            $table->index('etudiant_id');
        });
    }
    public function down(): void { Schema::dropIfExists('documents_etudiants'); }
};
