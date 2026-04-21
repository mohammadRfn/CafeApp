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
        Schema::create('invoices', function (Blueprint $table) {
            // ═══════════════════════════════════════════════════════
            // Primary Key
            // ═══════════════════════════════════════════════════════
            $table->id();
            
            // ═══════════════════════════════════════════════════════
            // Relationships (1-to-1 با Order)
            // ═══════════════════════════════════════════════════════
            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete()
                ->comment('سفارش مرتبط (1-to-1)');
            
            // ═══════════════════════════════════════════════════════
            // Invoice Identification
            // ═══════════════════════════════════════════════════════
            $table->string('invoice_number', 50)->unique()->comment('شماره فاکتور: INV-20240206-001');
            
            // ═══════════════════════════════════════════════════════
            // Pricing (کپی از Order)
            // ═══════════════════════════════════════════════════════
            $table->decimal('subtotal', 15, 2)->comment('جمع اولیه');
            $table->decimal('discount_amount', 15, 2)->default(0)->comment('مبلغ تخفیف');
            $table->decimal('tax_amount', 15, 2)->default(0)->comment('مبلغ مالیات');
            $table->decimal('delivery_fee', 15, 2)->default(0)->comment('هزینه ارسال');
            $table->decimal('total_amount', 15, 2)->comment('مبلغ نهایی');
            
            // ═══════════════════════════════════════════════════════
            // Payment Information
            // ═══════════════════════════════════════════════════════
            $table->enum('payment_method', ['cash', 'card', 'online'])->nullable()->comment('روش پرداخت');
            $table->enum('payment_status', ['unpaid', 'paid', 'refunded'])->default('unpaid')->index()->comment('وضعیت پرداخت');
            
            // ═══════════════════════════════════════════════════════
            // Payment Timestamps
            // ═══════════════════════════════════════════════════════
            $table->timestamp('paid_at')->nullable()->index()->comment('زمان پرداخت');
            $table->timestamp('refunded_at')->nullable()->comment('زمان برگشت');
            
            // ═══════════════════════════════════════════════════════
            // Notes
            // ═══════════════════════════════════════════════════════
            $table->text('notes')->nullable()->comment('یادداشت فاکتور');
            
            // ═══════════════════════════════════════════════════════
            // Audit
            // ═══════════════════════════════════════════════════════
            $table->foreignId('created_by')->constrained('users')->comment('کاربر صادرکننده فاکتور');
            
            // ═══════════════════════════════════════════════════════
            // Timestamps
            // ═══════════════════════════════════════════════════════
            $table->timestamps();
            
            // ═══════════════════════════════════════════════════════
            // Indexes for Performance
            // ═══════════════════════════════════════════════════════
            $table->index('created_at', 'idx_invoices_created_at');
            $table->index(['payment_status', 'paid_at'], 'idx_invoices_payment_status');
            $table->index(['created_by', 'payment_status'], 'idx_invoices_user_status');
        });
        
        // ═══════════════════════════════════════════════════════
        // Table Comment
        // ═══════════════════════════════════════════════════════
        DB::statement("ALTER TABLE invoices COMMENT = 'فاکتورهای مالی - مرتبط 1-to-1 با سفارشات'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};