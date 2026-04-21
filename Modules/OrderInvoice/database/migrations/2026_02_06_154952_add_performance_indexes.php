<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
    {
    // Orders - Generated Column + Indexes
    Schema::table('orders', function (Blueprint $table) {
        $table->date('paid_date')->generatedAs('DATE(paid_at)')->stored();
        $table->index(['total_amount', 'status'], 'idx_orders_amount_status');
        $table->index(['refund_type', 'refunded_at'], 'idx_orders_refund');
    });
    
    // Order Items
    Schema::table('order_items', function (Blueprint $table) {
        $table->index(['item_id', 'created_at'], 'idx_order_items_item_created');
        $table->index(['order_id', 'total_price'], 'idx_order_items_order_total');
    });
    
    // Invoices
    Schema::table('invoices', function (Blueprint $table) {
        $table->date('invoice_paid_date')->generatedAs('DATE(paid_at)')->stored();
        $table->index(['payment_method', 'payment_status'], 'idx_invoices_payment_method');
    });
    
    // Inventory Usages
    DB::statement('CREATE INDEX idx_inventory_usages_covering ON inventory_usages (entity_type, entity_id, created_at, quantity_used)');
    
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('paid_date');
            $table->dropIndex('idx_orders_amount_status');
            $table->dropIndex('idx_orders_refund');
        });
        
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('idx_order_items_item_created');
            $table->dropIndex('idx_order_items_order_total');
        });
        
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('invoice_paid_date');
            $table->dropIndex('idx_invoices_payment_method');
        });
        
        DB::statement('DROP INDEX idx_inventory_usages_covering ON inventory_usages');
    }
};