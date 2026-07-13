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
        Schema::create('inventories', function (Blueprint $table) {
            $table->id();
            // hasOne on the Product side. If you add variants later, drop the
            // unique() and it naturally becomes hasMany.
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('incoming')->default(0);
            $table->unsignedInteger('min_threshold')->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventories');
    }
};
