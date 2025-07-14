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
        Schema::create('amistades', function (Blueprint $table) {
            $table->bigIncrements('idAmistad')->comment('Unique ID for the friendship');
            $table->unsignedBigInteger('idUsuario')->comment('ID of the first user in the friendship');
            $table->unsignedBigInteger('idAmigo')->comment('ID of the second user in the friendship');
            $table->timestamps();

            // Foreign keys
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios')->onDelete('cascade');
            $table->foreign('idAmigo')->references('idUsuario')->on('usuarios')->onDelete('cascade');

            // Unique constraint to prevent duplicate friendships
            $table->unique(['idUsuario', 'idAmigo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amistades');
    }
};