<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('avatar_url')->nullable();
            $table->string('professional_title')->nullable();
            $table->text('bio')->nullable();
            $table->string('industry')->nullable();
            $table->string('location_text')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_discoverable')->default(false);
            $table->unsignedInteger('discovery_radius_meters')->default(500);
            $table->json('looking_for')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('github_url')->nullable();
            $table->string('website_url')->nullable();
            $table->unsignedTinyInteger('profile_completion_percent')->default(0);
            $table->timestamps();

            $table->index(['is_discoverable', 'latitude', 'longitude'], 'profiles_discovery_index');
            $table->index('industry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
