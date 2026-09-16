<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('account_type')->nullable()->after('account_code');
            $table->string('expense_account')->nullable()->after('account_type');
            $table->decimal('charge', 18, 2)->default(0)->after('expense_account');
        });
    }

    public function down()
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn(['account_type', 'expense_account', 'charge']);
        });
    }
};
