<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('item_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('manufacturers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('discount')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('colors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('color_code');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('size_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('sizes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('size_type_id')->constrained('size_types')->cascadeOnDelete();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('attribute_sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('attribute_set_id')->constrained('attribute_sets')->cascadeOnDelete();
            $table->string('name');
            $table->integer('position')->default(0);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('price_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('transaction_type');
            $table->string('pricebook_type');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('uom_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('from_uom');
            $table->string('to_uom');
            $table->decimal('factor', 18, 6);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('sku');
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('item_categories')->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            $table->foreignId('size_id')->nullable()->constrained('sizes')->nullOnDelete();
            $table->foreignId('attribute_set_id')->nullable()->constrained('attribute_sets')->nullOnDelete();
            $table->decimal('price', 18, 2)->default(0);
            $table->decimal('cost', 18, 2)->default(0);
            $table->decimal('stock', 18, 3)->default(0);
            $table->decimal('opening_stock', 18, 3)->default(0);
            $table->string('uom')->nullable();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'sku']);
        });

        Schema::create('customer_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('code')->nullable();
            $table->foreignId('customer_type_id')->nullable()->constrained('customer_types')->nullOnDelete();
            $table->string('website')->nullable();
            $table->boolean('as_supplier')->default(false);
            $table->foreignId('price_list_id')->nullable()->constrained('price_lists')->nullOnDelete();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->decimal('credit_limit', 18, 2)->default(0);
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('supplier_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('code')->nullable();
            $table->foreignId('supplier_type_id')->nullable()->constrained('supplier_types')->nullOnDelete();
            $table->decimal('opening_balance', 18, 2)->default(0);
            $table->string('contact_name')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('bin_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::table('pos_outlets', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('code');
            $table->string('address')->nullable()->after('phone');
            $table->string('cash_account')->nullable()->after('address');
            $table->json('mobile_banking')->nullable()->after('cash_account');
            $table->string('last_user')->nullable()->after('mobile_banking');
        });
    }

    public function down()
    {
        Schema::table('pos_outlets', function (Blueprint $table) {
            $table->dropColumn(['phone', 'address', 'cash_account', 'mobile_banking', 'last_user']);
        });
        Schema::dropIfExists('bin_locations');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('supplier_types');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('customer_types');
        Schema::dropIfExists('items');
        Schema::dropIfExists('uom_conversions');
        Schema::dropIfExists('price_lists');
        Schema::dropIfExists('attributes');
        Schema::dropIfExists('attribute_sets');
        Schema::dropIfExists('sizes');
        Schema::dropIfExists('size_types');
        Schema::dropIfExists('colors');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('manufacturers');
        Schema::dropIfExists('item_categories');
    }
};
