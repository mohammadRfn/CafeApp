<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warehouse', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255)->default('Main Storage');
            $table->text('address')->nullable();
            $table->string('phone', 20)->nullable();
            $table->decimal('capacity_kg', 12, 3)->default(10000);
            $table->decimal('current_utilization', 12, 3)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('created_at', 3)->useCurrent();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse');
    }
};
