<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * اضافه کردن indexهای composite برای بهینه‌سازی queryهای پرتکرار
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->index(['is_active', 'category', 'display_order'], 'idx_active_category_order');
        });
        
        Schema::table('item_ingredients', function (Blueprint $table) {
            $table->index(['ingredient_id', 'item_id'], 'idx_ingredient_item');
        });
        
        Schema::table('item_cost_history', function (Blueprint $table) {
            $table->index(['item_id', 'valid_from', 'valid_until'], 'idx_active_costs');
        });
        
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE items ADD FULLTEXT idx_items_search (name, code)');
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex('idx_active_category_order');
            
            if (DB::getDriverName() === 'mysql') {
                $table->dropIndex('idx_items_search');
            }
        });
        
        Schema::table('item_ingredients', function (Blueprint $table) {
            $table->dropIndex('idx_ingredient_item');
        });
        
        Schema::table('item_cost_history', function (Blueprint $table) {
            $table->dropIndex('idx_active_costs');
        });
    }
};
