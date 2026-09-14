<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('roles', function (Blueprint $table) {
            if (!Schema::hasColumn('roles', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
            }
            if (!Schema::hasColumn('roles', 'redirect_url')) {
                $table->string('redirect_url')->nullable()->after('slug');
            }
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('redirect_url');
            }
            if (!Schema::hasColumn('roles', 'is_system')) {
                $table->boolean('is_system')->default(false)->after('description');
            }
            if (!Schema::hasColumn('roles', 'status')) {
                $table->boolean('status')->default(true)->after('is_system');
            }
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('group_key'); // contacts, items, sales...
            $table->string('group_title');
            $table->string('resource'); // Suppliers, Brand...
            $table->string('action'); // view, create, update, delete
            $table->string('key')->unique(); // contacts.suppliers.view
            $table->timestamps();

            $table->index(['group_key', 'resource']);
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('status')->default('Active'); // Active / Inactive
            $table->date('effective_from')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'role_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');

        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
            foreach (['redirect_url', 'description', 'is_system', 'status'] as $col) {
                if (Schema::hasColumn('roles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
