<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('demandes_compte', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->default(\Illuminate\Support\Str::uuid());

            // 'etablissement' | 'enseignant' | 'etudiant'
            $table->string('type_demande', 30);

            // Toutes les données du formulaire soumis
            $table->json('donnees');

            // NULL si type_demande = 'etablissement'
            $table->foreignId('etablissement_id')
                  ->nullable()
                  ->constrained('etablissements')
                  ->nullOnDelete();

            // 'en_attente' | 'validee' | 'rejetee'
            $table->string('statut', 20)->default('en_attente');

            $table->timestamp('soumis_le')->useCurrent();

            // Qui a traité (super admin ou admin universitaire)
            $table->foreignId('traite_par')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->text('motif_rejet')->nullable();

            // Comptes créés après validation
            $table->foreignId('utilisateur_cree_id')
                  ->nullable()
                  ->constrained('utilisateurs')
                  ->nullOnDelete();
            $table->foreignId('etablissement_cree_id')
                  ->nullable()
                  ->constrained('etablissements')
                  ->nullOnDelete();

            $table->index('statut');
            $table->index('type_demande');
            $table->index('etablissement_id');
            $table->index('soumis_le');
        });
    }
    public function down(): void { Schema::dropIfExists('demandes_compte'); }
};
