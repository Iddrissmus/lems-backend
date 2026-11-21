<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Remembers writes by their Idempotency-Key header so a retried request does
// nothing twice (docs/api/demo-contract.md section 1). Rows live 24 hours.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('user_id')->constrained('users');
            $table->string('key', 100);
            $table->string('method', 10);
            $table->string('path');
            $table->char('request_hash', 64);
            // Null while the request is still running.
            $table->unsignedSmallInteger('status')->nullable();
            // The saved response, ENCRYPTED: some responses carry a one-time temporary password.
            $table->longText('response')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'key']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
