<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique()->after('email');
            $table->string('auth_provider')->nullable()->after('password');
            $table->boolean('is_admin')->default(false)->after('auth_provider');
            $table->boolean('is_verified')->default(false)->after('is_admin');
            $table->boolean('is_premium')->default(false)->after('is_verified');
            $table->string('status')->default('active')->after('is_premium');
            $table->timestamp('last_active_at')->nullable()->after('status');
            $table->softDeletes();

            $table->index('status');
            $table->index('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['last_active_at']);
            $table->dropSoftDeletes();
            $table->dropColumn([
                'phone', 'auth_provider', 'is_admin', 'is_verified',
                'is_premium', 'status', 'last_active_at',
            ]);
        });
    }
};
