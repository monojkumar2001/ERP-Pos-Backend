<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('balance', 15, 2)->default(0)->after('status');
        });

        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->date('entry_date');
            $table->time('entry_time')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('Draft'); // Draft, Approved
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'entry_date']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->date('entry_date');
            $table->time('entry_time')->nullable();
            $table->string('description')->nullable();
            $table->string('expense_from')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('Draft');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->date('entry_date');
            $table->time('entry_time')->nullable();
            $table->string('description')->nullable();
            $table->string('income_to')->nullable();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('Draft');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('money_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('reference_number')->nullable();
            $table->date('entry_date');
            $table->time('entry_time')->nullable();
            $table->string('description')->nullable();
            $table->string('transfer_from')->nullable();
            $table->string('transfer_to')->nullable();
            $table->foreignId('from_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('to_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('status')->default('Draft');
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('recurring_journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->date('start_date');
            $table->string('account_type')->nullable();
            $table->string('debit_account')->nullable();
            $table->string('credit_account')->nullable();
            $table->foreignId('debit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->foreignId('credit_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('recurring_interval')->nullable(); // e.g. 5 Days, 1 Months
            $table->date('last_transaction_date')->nullable();
            $table->string('approval_status')->default('Approve');
            $table->string('operational_status')->default('Active');
            $table->timestamps();

            $table->index(['company_id', 'operational_status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('recurring_journals');
        Schema::dropIfExists('money_transfers');
        Schema::dropIfExists('incomes');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('journals');

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('balance');
        });
    }
};
