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
        Schema::create('order_items', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════
            // Primary Key
            // ═══════════════════════════════════════════════════════
            $table->id();
            
            // ═══════════════════════════════════════════════════════
            // Relationships
            // ═══════════════════════════════════════════════════════
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete()
                ->comment('سفارش مرتبط');
            
            $table->foreignId('item_id')
                ->constrained('items')
                ->comment('محصول از ItemMaker');
            
            // ═══════════════════════════════════════════════════════
            // Item Snapshot (قیمت و recipe لحظه سفارش)
            // ═══════════════════════════════════════════════════════
            $table->json('item_snapshot')->comment('اطلاعات freeze شده محصول: {name, code, final_sell_price, recipe}');
            
            // ═══════════════════════════════════════════════════════
            // Quantity & Pricing
            // ═══════════════════════════════════════════════════════
            $table->unsignedInteger('quantity')->default(1)->comment('تعداد');
            $table->decimal('unit_price', 15, 2)->comment('قیمت واحد (لحظه سفارش)');
            $table->decimal('total_price', 15, 2)->comment('قیمت کل (unit_price × quantity)');
            
            // ═══════════════════════════════════════════════════════
            // Item-specific Notes
            // ═══════════════════════════════════════════════════════
            $table->text('notes')->nullable()->comment('یادداشت آیتم (مثلاً: بدون شکر)');
            
            // ═══════════════════════════════════════════════════════
            // Timestamps
            // ═══════════════════════════════════════════════════════
            $table->timestamps();
            
            // ═══════════════════════════════════════════════════════
            // Indexes for Performance
            // ═══════════════════════════════════════════════════════
            $table->index('order_id', 'idx_order_items_order');
            $table->index('item_id', 'idx_order_items_item');
            $table->index(['order_id', 'item_id'], 'idx_order_items_order_item');
        });
        
        // ═══════════════════════════════════════════════════════
        // Table Comment
        // ═══════════════════════════════════════════════════════
        DB::statement("ALTER TABLE order_items COMMENT = 'آیتم‌های سفارش - هر آیتم در سفارش با snapshot قیمت'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};