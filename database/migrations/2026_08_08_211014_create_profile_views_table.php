<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('viewed_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();

            $table->index(['viewed_user_id', 'viewed_at']);
            $table->index(['viewer_id', 'viewed_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profile_views');
    }
};
