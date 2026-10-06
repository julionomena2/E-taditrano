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
    Schema::create('quartiers', function (Blueprint $table) {
        $table->string('id_quartier', 20)->primary();
        $table->string('nom_quartier', 100);
        $table->string('id_ville', 5);

        $table->foreign('id_ville')
              ->references('id_ville')
              ->on('villes')
              ->onDelete('cascade')
              ->onUpdate('cascade');

        $table->timestamps();
    });
}
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quartiers');
    }
};
