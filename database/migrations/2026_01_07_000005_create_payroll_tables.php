<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thành phần lương, kỳ lương, phiếu lương + dòng chi tiết phiếu lương. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name');
            $table->enum('type', ['allowance', 'deduction'])->default('allowance');
            $table->enum('calculation', ['fixed', 'percent'])->default('fixed');
            $table->decimal('amount', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
        });

        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('code', 40);
            $table->char('period', 7); // 'YYYY-MM'
            $table->enum('status', ['draft', 'calculated', 'locked', 'approved', 'paid'])->default('draft');
            $table->decimal('total_gross', 18, 2)->default(0);
            $table->decimal('total_deductions', 18, 2)->default(0);
            $table->decimal('total_tax', 18, 2)->default(0);
            $table->decimal('total_insurance', 18, 2)->default(0);
            $table->decimal('total_net', 18, 2)->default(0);
            $table->dateTime('locked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->unique(['company_id', 'period']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->decimal('base_salary', 18, 2)->default(0);
            $table->decimal('allowances', 18, 2)->default(0);
            $table->decimal('deductions', 18, 2)->default(0);
            $table->decimal('insurance_employee', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('net_salary', 18, 2)->default(0);
            $table->enum('status', ['draft', 'locked'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);
            $table->index(['company_id', 'payroll_run_id']);
        });

        Schema::create('payslip_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payslip_id')->constrained('payslips')->cascadeOnDelete();
            $table->string('component_code', 40);
            $table->string('label');
            $table->decimal('amount', 18, 2)->default(0);
            $table->enum('type', ['earning', 'deduction'])->default('earning');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['payslip_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_lines');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
        Schema::dropIfExists('salary_components');
    }
};
