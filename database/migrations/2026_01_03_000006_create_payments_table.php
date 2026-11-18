<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phiếu thu (receipt) / phiếu chi (payment) — thanh toán hóa đơn
 * hoặc thu/chi trực tiếp với đối tác (khách hàng / NCC / nhân viên).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->enum('type', ['receipt', 'payment']);
            $table->string('partner_type', 50)->nullable(); // customer | supplier | employee
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('payment_date');
            $table->enum('status', ['draft', 'posted', 'cancelled'])->default('draft');
            $table->string('description', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'type', 'status']);
            $table->index(['invoice_id']);
            $table->index(['partner_type', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
