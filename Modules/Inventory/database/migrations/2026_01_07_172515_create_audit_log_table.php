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
        Schema::create('audit_log', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('table_name', 64);

            $table->unsignedBigInteger('record_id');

            $table->enum('action', ['INSERT', 'UPDATE', 'DELETE']);

            $table->json('old_values')->nullable();

            $table->json('new_values')->nullable();

            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['table_name', 'record_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_log');
    }
};
