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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            
            $table->string('name', 100)->comment('نام محصول - مثال: Caramel Macchiato');
            $table->string('code', 50)->unique()->comment('کد یکتا محصول - مثال: ITM-001');
            $table->text('description')->nullable()->comment('توضیحات محصول');
            
            $table->string('category', 50)->nullable()->index()->comment('دسته‌بندی - مثال: coffee, tea, dessert');
            $table->string('subcategory', 50)->nullable()->comment('زیردسته');
            
            $table->decimal('target_cost', 12, 2)->default(0)->comment('هزینه تمام شده هدف (محاسبه شده از recipe)');
            $table->decimal('target_sell_price', 12, 2)->default(0)->comment('قیمت فروش پیشنهادی');
            $table->decimal('actual_sell_price', 12, 2)->nullable()->comment('قیمت فروش واقعی');
            
            $table->unsignedSmallInteger('preparation_time')->default(0)->comment('زمان آماده‌سازی به دقیقه');
            $table->decimal('serving_size', 10, 2)->nullable()->comment('سایز سرو (ml, g, etc)');
            $table->string('serving_unit', 20)->nullable()->comment('واحد سرو - ml, g, piece');
            
            $table->boolean('is_active')->default(true)->index()->comment('آیا قابل سفارش است؟');
            $table->boolean('is_featured')->default(false)->comment('محصول ویژه');
            $table->boolean('requires_preparation')->default(true)->comment('نیاز به آماده‌سازی دارد؟');
            
            $table->unsignedInteger('daily_stock_limit')->nullable()->comment('محدودیت تعداد روزانه');
            $table->unsignedInteger('daily_sold_count')->default(0)->comment('تعداد فروش امروز');
            
            $table->unsignedSmallInteger('calories')->nullable()->comment('کالری');
            $table->json('allergens')->nullable()->comment('آلرژن‌ها - JSON array');
            
            $table->string('image_url')->nullable()->comment('تصویر محصول');
            $table->unsignedTinyInteger('display_order')->default(0)->comment('ترتیب نمایش');
            
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamp('last_cost_calculated_at')->nullable()->comment('آخرین زمان محاسبه هزینه');
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['is_active', 'is_featured']);
            $table->index('created_at');
            
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
        
        DB::statement("ALTER TABLE items COMMENT='جدول تعریف محصولات و آیتم‌های قابل سفارش'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
