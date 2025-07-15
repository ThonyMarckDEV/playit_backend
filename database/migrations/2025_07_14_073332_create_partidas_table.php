<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partidas', function (Blueprint $table) {
            $table->bigIncrements('idPartida')->comment('Unique match ID');
            $table->string('juego')->comment('Game name, e.g., Triki');
            $table->unsignedBigInteger('idUsuario')->comment('ID of the first player');
            $table->unsignedBigInteger('idAmigo')->nullable()->comment('ID of the second player (friend)');
            $table->unsignedBigInteger('idGanador')->nullable()->comment('ID of the winner, null if draw');
            $table->unsignedTinyInteger('estado')->default(1)->comment('1: Playing, 2: Finished');
            $table->timestamps();

            // Foreign keys
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios')->onDelete('cascade');
            $table->foreign('idAmigo')->references('idUsuario')->on('usuarios')->onDelete('set null');
            $table->foreign('idGanador')->references('idUsuario')->on('usuarios')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partidas');
    }
};