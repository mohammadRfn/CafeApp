<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventory_usages', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════
            // Primary Key
            // ═══════════════════════════════════════════════════════
            $table->id();
            
            // ═══════════════════════════════════════════════════════
            // Order Relationships
            // ═══════════════════════════════════════════════════════
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->comment('سفارش مرتبط');
            
            $table->foreignId('order_item_id')
                ->constrained('order_items')
                ->cascadeOnDelete()
                ->comment('آیتم سفارش');
            
            // ═══════════════════════════════════════════════════════
            // Polymorphic Entity (Ingredient or Box)
            // ═══════════════════════════════════════════════════════
            $table->enum('entity_type', ['ingredient', 'box'])->comment('نوع موجودی: ingredient یا box');
            $table->unsignedBigInteger('entity_id')->comment('شناسه ingredient یا box');
            
            // ═══════════════════════════════════════════════════════
            // Usage Details
            // ═══════════════════════════════════════════════════════
            $table->decimal('quantity_used', 12, 3)->comment('مقدار مصرف شده');
            $table->string('unit', 20)->comment('واحد: gram یا piece');
            
            // ═══════════════════════════════════════════════════════
            // Link to Inventory Transaction
            // ═══════════════════════════════════════════════════════
            $table->unsignedBigInteger('transaction_id')->nullable()->comment('شناسه transaction در Inventory module');
            
            // ═══════════════════════════════════════════════════════
            // Usage Type
            // ═══════════════════════════════════════════════════════
            $table->enum('usage_type', ['commit', 'rollback'])->default('commit')->comment('نوع عملیات: commit یا rollback');
            
            // ═══════════════════════════════════════════════════════
            // Timestamp
            // ═══════════════════════════════════════════════════════
            $table->timestamp('created_at')->useCurrent();
            
            // ═══════════════════════════════════════════════════════
            // Indexes for Performance
            // ═══════════════════════════════════════════════════════
            $table->index('order_id', 'idx_inventory_usages_order');
            $table->index('order_item_id', 'idx_inventory_usages_order_item');
            $table->index(['entity_type', 'entity_id'], 'idx_inventory_usages_entity');
            $table->index('transaction_id', 'idx_inventory_usages_transaction');
            $table->index(['order_id', 'usage_type'], 'idx_inventory_usages_order_type');
            $table->index('created_at', 'idx_inventory_usages_created_at');
        });
        
        // ═══════════════════════════════════════════════════════
        // Table Comment
        // ═══════════════════════════════════════════════════════
        DB::statement("ALTER TABLE inventory_usages COMMENT = 'ردیابی مصرف موجودی برای هر سفارش - لینک به Inventory transactions'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_usages');
    }
};