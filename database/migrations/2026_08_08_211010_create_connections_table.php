<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connections are stored canonically with user_one_id < user_two_id so a pair
     * can only ever produce a single row (enforced by the composite unique index).
     *
     * Design note: the spec offered an optional `event_connections` pivot. It is
     * deliberately omitted -- a connection is made at exactly one place/time, so
     * `connections.event_id` already models "where we met" without a join table.
     */
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('connection_request_id')->nullable()->constrained('connection_requests')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['user_one_id', 'user_two_id']);
            $table->index('user_two_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
