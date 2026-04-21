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
        Schema::create('item_ingredients', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('item_id')->comment('شناسه محصول');
            $table->unsignedBigInteger('ingredient_id')->comment('شناسه ماده اولیه از Inventory');
            
            $table->decimal('required_grams', 10, 3)->comment('مقدار مورد نیاز به گرم');
            $table->decimal('waste_factor', 5, 4)->default(0)->comment('ضریب ضایعات - مثال: 0.05 = 5%');
            $table->decimal('actual_grams', 10, 3)->storedAs('required_grams * (1 + waste_factor)')
                ->comment('مقدار واقعی با احتساب ضایعات (computed)');
            
            $table->decimal('unit_cost', 10, 2)->default(0)->comment('قیمت واحد در زمان تعریف');
            $table->decimal('total_cost', 10, 2)->storedAs('actual_grams * unit_cost')
                ->comment('هزینه کل این ingredient (computed)');
            
            $table->boolean('is_optional')->default(false)->comment('آیا اختیاری است؟ (مثل شیرینی اضافه)');
            $table->boolean('is_customizable')->default(false)->comment('قابل تغییر توسط مشتری؟');
            $table->string('preparation_note')->nullable()->comment('نکته آماده‌سازی');
            $table->unsignedTinyInteger('order')->default(0)->comment('ترتیب استفاده در recipe');
            
            $table->timestamps();
            
            $table->unique(['item_id', 'ingredient_id'], 'item_ingredient_unique');
            $table->index('item_id');
            $table->index('ingredient_id');
            $table->index(['item_id', 'is_optional']);
            
            $table->foreign('item_id')
                ->references('id')
                ->on('items')
                ->onDelete('cascade')
                ->comment('حذف cascade - اگر item حذف شد، recipe هم حذف شود');
                
            $table->foreign('ingredient_id')
                ->references('id')
                ->on('ingredients')
                ->onDelete('restrict')
                ->comment('حذف restrict - نمی‌توان ingredient مورد استفاده را حذف کرد');
        });
        
        DB::statement("ALTER TABLE item_ingredients COMMENT='جدول رابطه محصولات و مواد اولیه (Recipe Definition)'");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('item_ingredients');
    }
};
