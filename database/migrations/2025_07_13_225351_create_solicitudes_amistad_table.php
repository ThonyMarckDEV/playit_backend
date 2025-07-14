<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('solicitudes_amistad', function (Blueprint $table) {
            $table->bigIncrements('idSolicitudAmistad');
            $table->unsignedBigInteger('idUsuario')->comment('User who sent the friend request');
            $table->unsignedBigInteger('idAmigo')->comment('User who received the friend request');
            $table->enum('status', ['0', '1', '2'])->default('0')->comment('0: Pending, 1: Accepted, 2: Rejected');
            $table->timestamps();

            // Foreign keys
            $table->foreign('idUsuario')->references('idUsuario')->on('usuarios')->onDelete('cascade');
            $table->foreign('idAmigo')->references('idUsuario')->on('usuarios')->onDelete('cascade');

            // Unique constraint to prevent duplicate friend requests
            $table->unique(['idUsuario', 'idAmigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes_amistad');
    }
};