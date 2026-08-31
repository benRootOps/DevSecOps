<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('utilisateur_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utilisateur_id')->constrained('utilisateurs')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('type_acces', 10)->default('accorder');
            $table->foreignId('accorde_par')->nullable()->constrained('utilisateurs')->nullOnDelete();
            $table->timestamp('accorde_le')->useCurrent();
            $table->unique(['utilisateur_id', 'permission_id']);
            $table->index('utilisateur_id');
        });
    }
    public function down(): void { Schema::dropIfExists('utilisateur_permissions'); }
};
