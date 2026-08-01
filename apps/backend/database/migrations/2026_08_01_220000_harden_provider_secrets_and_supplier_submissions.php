<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->longText('secret_payload')->nullable();
        });

        Schema::table('payment_providers', function (Blueprint $table): void {
            $table->longText('secret_payload')->nullable();
        });

        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->string('submission_key', 160)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('supplier_orders', function (Blueprint $table): void {
            $table->dropUnique(['submission_key']);
            $table->dropColumn('submission_key');
        });

        Schema::table('payment_providers', function (Blueprint $table): void {
            $table->dropColumn('secret_payload');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn('secret_payload');
        });
    }
};