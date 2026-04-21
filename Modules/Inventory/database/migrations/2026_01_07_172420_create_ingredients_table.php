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
        Schema::create('ingredients', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('category_id')
            ->nullable()
            ->constrained('ingredient_categories')
            ->nullOnDelete();

            $table->foreignId('supplier_id')
            ->nullable()
            ->constrained('suppliers')
            ->nullOnDelete();

            $table->string('ingredient_name', 255);       
            $table->string('ingredient_code', 100); 
            $table->string('barcode', 50)->unique()->nullable();
            $table->text('description')->nullable();
            $table->decimal('min_stock', 15, 3)->default(0);
            $table->decimal('reorder_point', 15, 3)->default(0);
            $table->decimal('safety_stock', 15, 3)->default(0);
            $table->enum('abc_class', ['A','B','C'])->default('C');
            $table->boolean('is_perishable')->default(false);
            $table->integer('shelf_life_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps(3);

            $table->index(['ingredient_code', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints(); 
        Schema::dropIfExists('ingredients');
        Schema::enableForeignKeyConstraints();   
    }
};
