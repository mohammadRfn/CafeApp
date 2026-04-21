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
        Schema::create('orders', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════
            // Primary Key
            // ═══════════════════════════════════════════════════════
            $table->id();
            
            // ═══════════════════════════════════════════════════════
            // Order Identification
            // ═══════════════════════════════════════════════════════
            $table->string('order_number', 50)->unique()->comment('شماره یکتای سفارش: ORD-20240206-001');
            
            // ═══════════════════════════════════════════════════════
            // Status Management
            // ═══════════════════════════════════════════════════════
            $table->enum('status', [
                'draft',              // در حال ساخت
                'pending',            // منتظر بررسی موجودی
                'confirmed',          // موجودی رزرو شد
                'paid',               // پرداخت شد، موجودی کم شد
                'completed',          // تحویل داده شد
                'cancelled',          // لغو شد (قبل پرداخت)
                'refunded_consumed',  // برگشت با کسر موجودی
                'refunded_returned'   // برگشت بدون کسر موجودی
            ])->default('draft')->index()->comment('وضعیت سفارش');
            
            // ═══════════════════════════════════════════════════════
            // Pricing - Subtotal
            // ═══════════════════════════════════════════════════════
            $table->decimal('subtotal', 15, 2)->default(0)->comment('جمع اولیه (قبل تخفیف و مالیات)');
            
            // ═══════════════════════════════════════════════════════
            // Pricing - Discount (اختیاری)
            // ═══════════════════════════════════════════════════════
            $table->decimal('discount_percent', 5, 2)->nullable()->comment('درصد تخفیف (0-100)');
            $table->decimal('discount_amount', 15, 2)->default(0)->comment('مبلغ تخفیف محاسبه شده');
            
            // ═══════════════════════════════════════════════════════
            // Pricing - Tax (اختیاری)
            // ═══════════════════════════════════════════════════════
            $table->decimal('tax_percent', 5, 2)->nullable()->comment('درصد مالیات (0-100)');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('مبلغ مالیات محاسبه شده');
            
            // ═══════════════════════════════════════════════════════
            // Pricing - Delivery (اختیاری)
            // ═══════════════════════════════════════════════════════
            $table->decimal('delivery_fee', 15, 2)->default(0)->comment('هزینه ارسال');
            
            // ═══════════════════════════════════════════════════════
            // Pricing - Total
            // ═══════════════════════════════════════════════════════
            $table->decimal('total_amount', 15, 2)->default(0)->index()->comment('مبلغ نهایی');
            
            // ═══════════════════════════════════════════════════════
            // Notes & Refund Info
            // ═══════════════════════════════════════════════════════
            $table->text('notes')->nullable()->comment('یادداشت سفارش');
            $table->text('refund_reason')->nullable()->comment('دلیل برگشت');
            $table->enum('refund_type', ['consumed', 'returned'])->nullable()->comment('نوع برگشت: مصرف شده یا سالم');
            
            // ═══════════════════════════════════════════════════════
            // Audit & Tracking
            // ═══════════════════════════════════════════════════════
            $table->foreignId('created_by')->constrained('users')->comment('کاربر ثبت‌کننده');
            
            // ═══════════════════════════════════════════════════════
            // Status Timestamps
            // ═══════════════════════════════════════════════════════
            $table->timestamp('confirmed_at')->nullable()->index()->comment('زمان تایید سفارش');
            $table->timestamp('paid_at')->nullable()->index()->comment('زمان پرداخت');
            $table->timestamp('completed_at')->nullable()->comment('زمان تکمیل');
            $table->timestamp('cancelled_at')->nullable()->comment('زمان لغو');
            $table->timestamp('refunded_at')->nullable()->comment('زمان برگشت');
            
            // ═══════════════════════════════════════════════════════
            // Standard Timestamps
            // ═══════════════════════════════════════════════════════
            $table->timestamps();
            $table->softDeletes();
            
            // ═══════════════════════════════════════════════════════
            // Indexes for Performance
            // ═══════════════════════════════════════════════════════
            $table->index('created_at', 'idx_orders_created_at');
            $table->index(['status', 'created_at'], 'idx_orders_status_created');
            $table->index(['created_by', 'status'], 'idx_orders_user_status');
            $table->index(['paid_at', 'status'], 'idx_orders_paid_status');
        });
        
        // ═══════════════════════════════════════════════════════
        // Table Comment
        // ═══════════════════════════════════════════════════════
        DB::statement("ALTER TABLE orders COMMENT = 'جدول سفارشات - مدیریت کامل Order lifecycle'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};