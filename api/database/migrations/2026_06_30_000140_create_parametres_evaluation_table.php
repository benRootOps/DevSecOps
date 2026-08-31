<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 8 — CONFIG (extension hors schéma BD) : paramètres d'évaluation par établissement.
// Modifiable uniquement par le directeur/délégué (champ `verrouille` + policy à venir).
return new class extends Migration {
    public function up(): void {
        Schema::create('parametres_evaluation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->unique()->constrained('etablissements')->cascadeOnDelete();
            $table->decimal('poids_cc', 4, 3)->default(0.400);          // pondération contrôle continu
            $table->decimal('poids_examen', 4, 3)->default(0.600);      // pondération examen
            $table->decimal('bareme', 5, 2)->default(20);               // note sur 20
            $table->decimal('echelle_secondaire', 5, 2)->default(5);    // 2e échelle (sur 5)
            $table->decimal('seuil_validation_ue', 5, 2)->default(10);  // moyenne UE pour valider
            $table->decimal('seuil_compensation', 5, 2)->default(7);    // note comblable en délibération
            $table->decimal('note_eliminatoire', 5, 2)->nullable();     // sous ce seuil → échec automatique
            $table->boolean('verrouille')->default(false);              // true = seul le directeur modifie
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('parametres_evaluation'); }
};
