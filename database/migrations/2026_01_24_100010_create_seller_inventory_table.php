<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('store_product_id')->constrained('store_products')->onDelete('restrict');
            $table->integer('quantity')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'store_product_id']);
            $table->index('user_id');
            $table->index('store_product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_inventory');
    }
};
