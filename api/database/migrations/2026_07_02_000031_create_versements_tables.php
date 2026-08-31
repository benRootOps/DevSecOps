<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Module 11 — Financier scolarité (cf. §22) : versements manuels + reçus.
// Edusphere n'encaisse PAS en ligne : on enregistre les paiements faits à l'université.
return new class extends Migration {
    public function up(): void {
        Schema::create('versements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('etablissement_id')->constrained('etablissements')->cascadeOnDelete();
            $table->foreignId('etudiant_id')->constrained('etudiants')->cascadeOnDelete();
            $table->foreignId('tranche_id')->constrained('tranches_paiement');
            $table->decimal('montant_verse', 12, 2);
            $table->date('date_versement');
            $table->string('mode_paiement', 50)->nullable(); // Espèces / Virement / MTN MoMo / Orange Money / Chèque...
            $table->string('reference', 100)->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('enregistre_par')->constrained('utilisateurs');
            $table->timestamps();
            $table->index('etablissement_id');
            $table->index('etudiant_id');
        });

        Schema::create('recus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versement_id')->unique()->constrained('versements')->cascadeOnDelete();
            $table->string('numero_recu', 50)->unique();
            $table->string('fichier_url', 500)->nullable();
            $table->timestamp('genere_le')->nullable();
            $table->foreignId('genere_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('recus');
        Schema::dropIfExists('versements');
    }
};
