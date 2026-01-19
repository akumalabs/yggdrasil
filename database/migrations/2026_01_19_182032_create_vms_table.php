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
        Schema::create('vms', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('vmid')->unique();
            $table->string('name');
            $table->string('node');
            $table->string('status')->default('creating'); // creating, running, stopped
            $table->json('config')->nullable();
            $table->text('upid')->nullable(); // Track the creation task
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vms');
    }
};
