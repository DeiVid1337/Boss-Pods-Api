<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'manager', 'seller'])->default('seller')->after('password');
            $table->foreignId('store_id')->nullable()->after('role')->constrained('stores')->onDelete('set null');
            $table->boolean('is_active')->default(true)->after('store_id');
            $table->softDeletes();

            $table->index('store_id');
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['store_id']);
            $table->dropIndex(['store_id']);
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'store_id', 'is_active', 'deleted_at']);
        });
    }
};
