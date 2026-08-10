<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // The provider's stable subject ("sub") claim -- matching on this
            // rather than email survives an email change on the provider's
            // side and, for Apple, doesn't depend on the user having shared
            // their real address instead of a private relay one.
            $table->string('provider_id')->nullable()->after('auth_provider');

            $table->unique(['auth_provider', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['auth_provider', 'provider_id']);
            $table->dropColumn('provider_id');
        });
    }
};
