<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('low_stock', 18, 3)->nullable()->after('stock');
        });

        Schema::create('item_stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('quantity', 18, 3)->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'item_id', 'warehouse_id', 'bin_location_id'], 'item_stock_balances_unique');
        });

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('adjustment_number');
            $table->date('adjustment_date');
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('adjustment_reason_id')->nullable()->constrained('adjustment_reasons')->nullOnDelete();
            $table->enum('adjustment_type', ['Increase', 'Decrease']);
            $table->enum('status', ['Draft', 'Approved', 'Cancelled'])->default('Draft');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'adjustment_number']);
        });

        Schema::create('stock_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained('stock_adjustments')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->decimal('unit_cost', 18, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('transfer_number');
            $table->date('transfer_date');
            $table->foreignId('source_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->foreignId('destination_warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->enum('kind', ['movement', 'request'])->default('movement');
            $table->enum('status', ['Draft', 'Requested', 'Initiated', 'Received', 'Cancelled'])->default('Draft');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'transfer_number']);
        });

        Schema::create('warehouse_transfer_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_transfer_id')->constrained('warehouse_transfers')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('source_bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->foreignId('destination_bin_location_id')->nullable()->constrained('bin_locations')->nullOnDelete();
            $table->decimal('quantity', 18, 3);
            $table->string('uom')->nullable();
            $table->timestamps();
        });

        Schema::create('warehouse_user_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'warehouse_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('warehouse_user_access');
        Schema::dropIfExists('warehouse_transfer_lines');
        Schema::dropIfExists('warehouse_transfers');
        Schema::dropIfExists('stock_adjustment_lines');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('item_stock_balances');

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('low_stock');
        });
    }
};
