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
        Schema::create('ingredient_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('ingredient_id')->constrained()->cascadeOnDelete();
            $table->string('ingredient_name', 255);  //this
            $table->string('ingredient_code', 100); //this
            $table->string('batch_number', 100)->nullable();
            $table->enum('transaction_type', ['purchase','usage','adjustment','waste','expiry']);
            $table->decimal('input_quantity', 18, 3);
            $table->foreignId('input_unit_id')->constrained('units')->nullable();
            $table->decimal('grams_effect', 18, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'committed', 'cancelled'])
              ->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['created_at', 'transaction_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('ingredient_transactions');
        Schema::enableForeignKeyConstraints();
    }
};
