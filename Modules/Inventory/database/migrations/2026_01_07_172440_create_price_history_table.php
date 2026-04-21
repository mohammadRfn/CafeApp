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
        Schema::create('price_history', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->decimal('buy_price', 12, 4);
            $table->decimal('sell_price', 12, 4);
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
    
            $table->index(['ingredient_id', 'valid_from', 'valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_history');
    }
};
