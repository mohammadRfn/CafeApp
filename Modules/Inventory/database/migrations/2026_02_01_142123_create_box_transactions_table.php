<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('box_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('box_id')->constrained()->cascadeOnDelete();
            $table->string('entity_name', 255);
            $table->string('entity_code', 100)->nullable();
            $table->string('batch_number', 100)->nullable();
            $table->enum('transaction_type', ['purchase','usage','adjustment','waste','expiry','reserve','release']);
            $table->decimal('input_quantity', 18, 3);
            $table->unsignedBigInteger('input_unit_id')->nullable();
            $table->decimal('quantity_effect', 18, 3);
            $table->decimal('unit_cost', 12, 4)->nullable();
            $table->decimal('total_cost', 15, 4)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'committed', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('created_at', 3)->useCurrent();

            $table->index(['created_at', 'transaction_type']);
            $table->index('box_id');
        });
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('box_transactions');
        Schema::enableForeignKeyConstraints();
    }
};
