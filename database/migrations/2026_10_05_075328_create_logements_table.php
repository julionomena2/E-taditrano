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
        Schema::create('logements', function (Blueprint $table) {
            $table->id();
            $table->string('id_utilisateur', 50);
            $table->foreign('id_utilisateur')
                ->references('id_utilisateur')
                ->on('utilisateurs')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->string('id_quartier', 20);
            $table->foreign('id_quartier')
                ->references('id_quartier')
                ->on('quartiers')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->text('description');
            $table->string('type_logement', 100);
            $table->unsignedInteger('prix');
            $table->date('date_publication');
            $table->string('status', 100);
            $table->string('photo', 1000)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logements');
    }
};
