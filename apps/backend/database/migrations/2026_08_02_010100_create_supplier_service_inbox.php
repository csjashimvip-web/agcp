<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_service_inbox', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('external_service_id', 128);
            $table->string('external_name')->nullable();
            $table->unsignedBigInteger('cost_minor')->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('status', 24)->default('unmapped')->index();
            $table->foreignId('mapped_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['supplier_id', 'external_service_id'],
                'uq_supplier_service_inbox'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_service_inbox');
    }
};