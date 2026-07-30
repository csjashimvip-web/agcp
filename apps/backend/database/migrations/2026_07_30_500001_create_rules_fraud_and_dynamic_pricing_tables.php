<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('surcharge_minor')->default(0)->after('discount_minor');
        });

        Schema::create('rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('slug', 180);
            $table->string('scope', 40)->index();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('stop_on_match')->default(false);
            $table->unsignedInteger('published_version')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'scope', 'status', 'priority']);
        });

        Schema::create('rule_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('condition_mode', 12)->default('all');
            $table->json('conditions');
            $table->json('actions');
            $table->string('checksum', 64);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['rule_id', 'version']);
        });

        Schema::create('rule_executions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('rule_id')->constrained('rules')->cascadeOnDelete();
            $table->foreignUuid('rule_version_id')->constrained('rule_versions')->cascadeOnDelete();
            $table->string('scope', 40)->index();
            $table->string('context_type', 120)->nullable();
            $table->uuid('context_id')->nullable()->index();
            $table->boolean('matched')->index();
            $table->json('input_snapshot')->nullable();
            $table->json('result_snapshot')->nullable();
            $table->string('correlation_id', 64)->nullable()->index();
            $table->timestamp('executed_at')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'scope', 'executed_at']);
        });

        Schema::create('fraud_risk_assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('subject_type', 80);
            $table->uuid('subject_id')->nullable()->index();
            $table->unsignedInteger('score')->default(0)->index();
            $table->string('level', 24)->index();
            $table->string('decision', 24)->index();
            $table->string('status', 24)->default('open')->index();
            $table->json('context')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->index();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status', 'score']);
        });

        Schema::create('fraud_signals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('assessment_id')->constrained('fraud_risk_assessments')->cascadeOnDelete();
            $table->string('code', 120)->index();
            $table->unsignedInteger('score');
            $table->string('severity', 24)->index();
            $table->string('message', 500);
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->index(['assessment_id', 'score']);
        });

        Schema::create('price_quotes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('catalog_variant_id')->constrained('catalog_variants')->cascadeOnDelete();
            $table->char('currency', 3)->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('base_amount_minor');
            $table->bigInteger('adjustment_minor')->default(0);
            $table->unsignedBigInteger('final_amount_minor');
            $table->json('matched_rule_ids')->nullable();
            $table->json('breakdown')->nullable();
            $table->string('context_hash', 64)->index();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->index(['tenant_id', 'catalog_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quotes');
        Schema::dropIfExists('fraud_signals');
        Schema::dropIfExists('fraud_risk_assessments');
        Schema::dropIfExists('rule_executions');
        Schema::dropIfExists('rule_versions');
        Schema::dropIfExists('rules');
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('surcharge_minor');
        });
    }
};
