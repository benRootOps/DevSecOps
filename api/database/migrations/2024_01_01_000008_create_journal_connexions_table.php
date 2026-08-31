<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_connexions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('sessions')->nullOnDelete();
            $table->string('type_evenement', 30);
            $table->ipAddress('ip_adresse')->nullable();
            $table->text('agent_navigateur')->nullable();
            $table->text('detail')->nullable();
            $table->timestamp('cree_le')->useCurrent();

            $table->index('utilisateur_id');
            $table->index('cree_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_connexions');
    }
};
