<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->string('expediteur_id', 50);
            $table->string('destinataire_id', 50);
            $table->text('contenu');
            $table->timestamps();

            $table->foreign('expediteur_id')
                ->references('id_utilisateur')
                ->on('utilisateurs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreign('destinataire_id')
                ->references('id_utilisateur')
                ->on('utilisateurs')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->index(['expediteur_id', 'destinataire_id', 'created_at']);
            $table->index(['destinataire_id', 'expediteur_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
