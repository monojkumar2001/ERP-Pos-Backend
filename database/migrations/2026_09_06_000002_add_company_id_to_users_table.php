<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('role_id');
                $table->boolean('is_owner')->default(false)->after('company_id');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasTable('companies') && Schema::hasColumn('users', 'company_id')) {
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn(['company_id', 'is_owner']);
            }
        });
    }
};
