<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Luồng duyệt: quy tắc duyệt + lịch sử từng bước duyệt. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 50);
            $table->decimal('min_amount', 18, 2)->default(0);
            $table->decimal('max_amount', 18, 2)->nullable();
            $table->string('approver_role', 50);
            $table->unsignedTinyInteger('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'document_type']);
        });

        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('approver_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->default(1);
            $table->string('decision', 20); // approved | rejected
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_logs');
        Schema::dropIfExists('approval_rules');
    }
};
