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
        Schema::create('units', function (Blueprint $table) {
             $table->bigIncrements('id');
             $table->string('name', 100)->unique();
             $table->string('symbol', 10);
             $table->decimal('conversion_factor', 20, 8);  
             $table->tinyInteger('precision_digits')->default(3);
             $table->timestamp('created_at', 3)->useCurrent();
             $table->index('symbol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
