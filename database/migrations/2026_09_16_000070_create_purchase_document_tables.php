<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('purchase_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type');
            $table->string('document_number');
            $table->date('document_date');
            $table->date('valid_till')->nullable();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('purchase_documents')->nullOnDelete();
            $table->string('sales_person')->nullable();
            $table->string('price_list')->nullable();
            $table->string('receipt_number')->nullable();
            $table->string('status')->default('Draft');
            $table->string('order_status')->nullable();
            $table->string('delivery_status')->nullable();
            $table->string('invoice_status')->nullable();
            $table->string('ecommerce_order')->nullable()->default('N/A');
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('discount_amount', 18, 2)->default(0);
            $table->decimal('adjustment', 18, 2)->default(0);
            $table->decimal('grand_total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('amount_due', 18, 2)->default(0);
            $table->text('note')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'type', 'document_number'], 'purchase_documents_company_type_number_unique');
            $table->index(['company_id', 'type', 'status']);
        });

        Schema::create('purchase_document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained('purchase_documents')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('discount', 18, 2)->default(0);
            $table->decimal('tax', 18, 2)->default(0);
            $table->decimal('line_total', 18, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_document_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_document_id')->constrained('purchase_documents')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('method_name')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->date('payment_date')->nullable();
            $table->timestamps();
        });

        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('purchase_document_id')->nullable()->constrained('purchase_documents')->nullOnDelete();
            $table->date('entry_date');
            $table->decimal('debit', 18, 2)->default(0);
            $table->decimal('credit', 18, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'supplier_id', 'entry_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('supplier_ledger_entries');
        Schema::dropIfExists('purchase_document_payments');
        Schema::dropIfExists('purchase_document_lines');
        Schema::dropIfExists('purchase_documents');
    }
};
