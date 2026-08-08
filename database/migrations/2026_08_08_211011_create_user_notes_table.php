<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained('connections')->cascadeOnDelete();
            $table->text('note');
            $table->timestamps();

            $table->unique(['owner_id', 'connection_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notes');
    }
};
