<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('department_id');
        });

        Schema::table('requisitions', function (Blueprint $table) {
            $table->string('requester_signature_path')->nullable()->after('status');
            $table->timestamp('requester_signed_at')->nullable()->after('requester_signature_path');
            $table->string('manager_signature_path')->nullable()->after('requester_signed_at');
            $table->timestamp('manager_signed_at')->nullable()->after('manager_signature_path');
            $table->string('inventory_signature_path')->nullable()->after('manager_signed_at');
            $table->timestamp('inventory_signed_at')->nullable()->after('inventory_signature_path');
            $table->string('warehouse_signature_path')->nullable()->after('inventory_signed_at');
            $table->timestamp('warehouse_signed_at')->nullable()->after('warehouse_signature_path');
        });
    }

    public function down(): void
    {
        Schema::table('requisitions', function (Blueprint $table) {
            $table->dropColumn([
                'requester_signature_path',
                'requester_signed_at',
                'manager_signature_path',
                'manager_signed_at',
                'inventory_signature_path',
                'inventory_signed_at',
                'warehouse_signature_path',
                'warehouse_signed_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });
    }
};
