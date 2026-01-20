<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vm_bandwidth_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vm_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->bigInteger('bytes_in')->default(0);
            $table->bigInteger('bytes_out')->default(0);
            $table->bigInteger('total_bytes')->default(0);
            $table->timestamps();
            
            $table->unique(['vm_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vm_bandwidth_usage');
    }
};
