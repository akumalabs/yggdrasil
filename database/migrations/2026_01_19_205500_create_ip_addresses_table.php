<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ip_addresses', function (Blueprint $table) {
            $table->id();
            $table->ipAddress('ip')->unique();
            $table->ipAddress('gateway')->default('192.168.1.1');
            $table->string('netmask')->default('24'); // CIDR
            $table->boolean('is_reserved')->default(false);
            $table->foreignId('vm_id')->nullable()->constrained('vms')->onDelete('set null');
            $table->string('label')->nullable(); // e.g. "Public", "Private"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ip_addresses');
    }
};
