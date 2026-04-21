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
        Schema::create('box_ingredients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('box_id')->constrained('boxes')->cascadeOnDelete();
            $table->foreignId('ingredient_id')->constrained('ingredients')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->decimal('required_quantity', 15, 6); 
            $table->decimal('waste_factor', 5, 3)->default(0.050); 
            $table->timestamps(3);

            $table->index(['box_id', 'ingredient_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('box_ingredients');
    }
};
