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
        Schema::create('box_stock', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('box_id')->constrained('boxes')->cascadeOnDelete();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('reserved_quantity', 12, 3)->default(0);
            $table->timestamp('updated_at', 3)->useCurrentOnUpdate()->useCurrent();

            $table->index(['box_id', 'quantity']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('box_stock');
    }
};
