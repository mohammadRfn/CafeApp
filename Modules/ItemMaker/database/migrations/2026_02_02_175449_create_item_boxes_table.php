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
        Schema::create('item_boxes', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('item_id')->comment('شناسه محصول');
            $table->unsignedBigInteger('box_id')->comment('شناسه باکس/بسته‌بندی از Inventory');
            
            $table->unsignedSmallInteger('required_quantity')->default(1)->comment('تعداد باکس مورد نیاز - معمولاً 1');
            
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('قیمت واحد باکس در زمان تعریف');
            $table->decimal('total_cost', 10, 2)->storedAs('required_quantity * unit_cost')
                ->comment('هزینه کل باکس‌ها (computed)');
            
            $table->boolean('is_default_packaging')->default(true)->comment('بسته‌بندی پیش‌فرض؟');
            $table->boolean('is_optional')->default(false)->comment('آیا اختیاری است؟ (upgrade به باکس بهتر)');
            $table->string('note')->nullable()->comment('توضیحات');
            
            $table->timestamps();
            
            $table->unique(['item_id', 'box_id'], 'item_box_unique');
            $table->index('item_id');
            $table->index('box_id');
            $table->index(['item_id', 'is_default_packaging']);
            
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->onDelete('cascade');
                
            $table->foreign('box_id')
                ->references('id')
                ->on('boxes')
                ->onDelete('restrict')
                ->comment('نمی‌توان باکس مورد استفاده را حذف کرد');
        });
        
        DB::statement("ALTER TABLE item_boxes COMMENT='جدول رابطه محصولات و باکس‌های بسته‌بندی'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('item_boxes');
    }
};
