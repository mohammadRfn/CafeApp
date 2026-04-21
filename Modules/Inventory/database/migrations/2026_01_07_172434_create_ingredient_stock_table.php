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
        Schema::create('ingredient_stock', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity_grams', 18, 3)->default(0);     
            $table->decimal('available_grams', 18, 3)->default(0);
            $table->decimal('reserved_grams', 18, 3)->default(0);
            $table->decimal('avg_cost_per_gram', 12, 6)->default(0);
            $table->timestamp('last_updated', 3)->useCurrentOnUpdate();
    
            $table->index('available_grams');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingredient_stock');
    }
};
