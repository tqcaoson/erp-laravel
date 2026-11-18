<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bút toán tổng hợp + dòng bút toán — ĐÚNG DDL mục 5.3 của thiết kế,
 * thêm created_by/updated_by theo quy ước chung.
 * Ràng buộc CHECK: debit/credit không âm và một dòng không vừa Nợ vừa Có.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fiscal_period_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->date('entry_date');
            $table->string('description', 500)->nullable();
            $table->string('source_type', 100)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->enum('status', ['draft', 'posted', 'reversed'])->default('draft');
            $table->foreignId('reversed_by_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'fiscal_period_id', 'entry_date']);
            $table->index(['source_type', 'source_id']);
            $table->index(['company_id', 'status']);
        });

        /*
         * CHECK debit/credit (mục 5.3): Laravel 11 chưa có $table->check()
         * nên thêm bằng SQL thô. SQLite chỉ cho phép CHECK lúc CREATE TABLE
         * (không ALTER được) nên tạo bảng bằng SQL thô; MySQL thì tạo bảng
         * bằng Blueprint rồi ALTER TABLE thêm CONSTRAINT.
         */
        if (DB::getDriverName() === 'sqlite') {
            DB::statement(<<<'SQL'
                CREATE TABLE journal_entry_lines (
                    id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
                    journal_entry_id INTEGER NOT NULL,
                    account_id INTEGER NOT NULL,
                    partner_type VARCHAR(50) NULL,
                    partner_id INTEGER NULL,
                    debit NUMERIC(18, 2) NOT NULL DEFAULT 0,
                    credit NUMERIC(18, 2) NOT NULL DEFAULT 0,
                    description VARCHAR(255) NULL,
                    created_by INTEGER NULL,
                    updated_by INTEGER NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    CONSTRAINT chk_debit_credit CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0)),
                    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries (id) ON DELETE CASCADE,
                    FOREIGN KEY (account_id) REFERENCES chart_of_accounts (id) ON DELETE RESTRICT,
                    FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
                    FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
                )
                SQL);

            DB::statement('CREATE INDEX journal_entry_lines_journal_entry_id_index ON journal_entry_lines (journal_entry_id)');
            DB::statement('CREATE INDEX idx_jel_account ON journal_entry_lines (account_id, partner_type, partner_id)');
        } else {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
                $table->string('partner_type', 50)->nullable(); // customer | supplier | employee
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->decimal('debit', 18, 2)->default(0);
                $table->decimal('credit', 18, 2)->default(0);
                $table->string('description')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['account_id', 'partner_type', 'partner_id'], 'idx_jel_account');
            });

            DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_debit_credit CHECK (debit >= 0 AND credit >= 0 AND NOT (debit > 0 AND credit > 0))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
    }
};
