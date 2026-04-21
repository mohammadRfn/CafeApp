<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('item_cost_history', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('item_id')->comment('شناسه محصول');
            
            $table->decimal('ingredients_cost', 12, 2)->default(0)->comment('هزینه مواد اولیه');
            $table->decimal('boxes_cost', 12, 2)->default(0)->comment('هزینه بسته‌بندی');
            $table->decimal('overhead_cost', 12, 2)->default(0)->comment('هزینه‌های سربار (اختیاری)');
            $table->decimal('total_cost', 12, 2)->storedAs('ingredients_cost + boxes_cost + overhead_cost')
                ->comment('هزینه کل (computed)');
            
            $table->decimal('suggested_sell_price', 12, 2)->nullable()->comment('قیمت فروش پیشنهادی');
            $table->decimal('profit_margin', 8, 4)->nullable()->comment('حاشیه سود - درصد');
            
            $table->string('calculation_method', 50)->default('auto')->comment('روش محاسبه: auto, manual');
            $table->json('breakdown_details')->nullable()->comment('جزئیات محاسبه - JSON');
            $table->text('notes')->nullable()->comment('یادداشت‌ها');
            
            $table->timestamp('valid_from')->useCurrent()->comment('معتبر از تاریخ');
            $table->timestamp('valid_until')->nullable()->comment('معتبر تا تاریخ - null = بدون محدودیت');
            
            $table->unsignedBigInteger('calculated_by')->nullable()->comment('محاسبه شده توسط');
            $table->timestamps();
            
            $table->index('item_id');
            $table->index(['item_id', 'valid_from']);
            $table->index(['item_id', 'valid_until']);
            $table->index(['valid_from', 'valid_until']);
            $table->index('created_at');
            
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->onDelete('cascade');
                
            $table->foreign('calculated_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
        
        DB::statement("ALTER TABLE item_cost_history COMMENT='تاریخچه محاسبات هزینه محصولات'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('item_cost_history');
    }
};
