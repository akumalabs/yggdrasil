<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->string('hostname')->nullable()->after('name');
            $table->integer('bandwidth_limit')->nullable()->after('config')->comment('TB per month');
            $table->bigInteger('bandwidth_usage_bytes')->default(0)->after('bandwidth_limit');
            $table->date('bandwidth_reset_date')->nullable()->after('bandwidth_usage_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('vms', function (Blueprint $table) {
            $table->dropColumn(['hostname', 'bandwidth_limit', 'bandwidth_usage_bytes', 'bandwidth_reset_date']);
        });
    }
};
