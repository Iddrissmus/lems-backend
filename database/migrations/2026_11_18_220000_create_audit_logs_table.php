<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique(); // public id
            $table->string('event', 64)->index(); // e.g. user.roles_changed
            // Who did it. Null for the system or someone not signed in.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // What it was done to: a short type ("user") and that record's public id.
            $table->string('subject_type', 32)->nullable();
            $table->char('subject_id', 26)->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            // Append-only: rows are never updated, so there is no updated_at.
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
