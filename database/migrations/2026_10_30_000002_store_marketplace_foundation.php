<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('store_settings',function(Blueprint $t) {
            $t->id(); $t->string('key')->unique(); $t->json('value'); $t->timestamps();
        });
        Schema::create('store_shipping_accounts',function(Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies');
            $t->string('name'); $t->string('driver',40)->default('manual');
            $t->text('credentials')->nullable(); $t->json('configuration')->nullable();
            $t->string('cod_beneficiary',20)->default('seller');
            $t->boolean('is_active')->default(false); $t->boolean('is_test_mode')->default(true);
            $t->timestamps(); $t->softDeletes();
        });
        Schema::create('store_shipping_rates',function(Blueprint $t) {
            $t->id(); $t->foreignId('shipping_account_id')->constrained('store_shipping_accounts');
            $t->string('governorate_code',30); $t->unsignedBigInteger('fee_minor');
            $t->unsignedBigInteger('free_above_minor')->nullable();
            $t->boolean('cod_allowed')->default(true); $t->boolean('is_active')->default(true);
            $t->timestamps(); $t->unique(['shipping_account_id','governorate_code']);
        });
        Schema::create('store_seller_profiles',function(Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->unique()->constrained('companies');
            $t->foreignId('warehouse_id')->nullable()->constrained('warehouses');
            $t->foreignId('shipping_account_id')->nullable()->constrained('store_shipping_accounts');
            $t->json('pickup_address')->nullable(); $t->json('policy_overrides')->nullable();
            $t->text('payout_destination')->nullable(); $t->boolean('is_enabled')->default(false);
            $t->unsignedInteger('policy_version')->default(1); $t->timestamps();
        });
        Schema::create('store_seller_accounts',function(Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained('companies'); $t->string('currency',3)->default('EGP');
            $t->bigInteger('balance_minor')->default(0); $t->unsignedBigInteger('prepaid_minor')->default(0);
            $t->unsignedBigInteger('pending_minor')->default(0); $t->unsignedBigInteger('payout_held_minor')->default(0);
            $t->timestamps(); $t->unique(['company_id','currency']);
        });
        Schema::create('store_seller_ledger',function(Blueprint $t) {
            $t->id(); $t->foreignId('account_id')->constrained('store_seller_accounts');
            $t->string('operation_key',120)->unique(); $t->string('kind',50); $t->string('bucket',20);
            $t->bigInteger('amount_minor'); $t->bigInteger('balance_after_minor');
            $t->string('reference_type',60)->nullable(); $t->unsignedBigInteger('reference_id')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users');
            $t->json('metadata')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('store_financial_journals',function(Blueprint $t) {
            $t->id(); $t->string('operation_key',120)->unique(); $t->string('kind',50);
            $t->string('fingerprint',64);
            $t->string('currency',3); $t->json('metadata')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('store_financial_lines',function(Blueprint $t) {
            $t->id(); $t->foreignId('journal_id')->constrained('store_financial_journals');
            $t->string('account_code',100); $t->foreignId('company_id')->nullable()->constrained('companies');
            $t->bigInteger('amount_minor'); $t->index(['company_id','account_code']);
        });
        Schema::create('store_account_holds',function(Blueprint $t) {
            $t->id(); $t->foreignId('account_id')->constrained('store_seller_accounts');
            $t->foreignId('store_sub_order_id')->unique()->constrained('store_sub_orders');
            $t->unsignedBigInteger('balance_minor')->default(0); $t->unsignedBigInteger('prepaid_minor')->default(0);
            $t->unsignedBigInteger('credit_minor')->default(0); $t->string('status',20)->default('active');
            $t->timestamps();
        });
        Schema::create('store_stock_allocations',function(Blueprint $t) {
            $t->id(); $t->foreignId('store_sub_order_id')->constrained('store_sub_orders');
            $t->foreignId('store_order_item_id')->constrained('store_order_items');
            $t->foreignId('stock_id')->constrained('stocks'); $t->decimal('quantity',18,6);
            $t->string('status',20)->default('reserved'); $t->timestamps();
            $t->index(['store_sub_order_id','status']);
        });
        Schema::create('store_checkout_quotes',function(Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('checkout_key',64)->index();
            $t->foreignId('customer_user_id')->nullable()->constrained('users');
            $t->string('request_fingerprint',64); $t->text('snapshot');
            $t->timestamp('expires_at')->index(); $t->timestamps();
        });
        Schema::table('store_orders',function(Blueprint $t) {
            $t->foreignId('customer_user_id')->nullable()->change();
            $t->foreignId('shipping_address_id')->nullable()->change();
            $t->string('checkout_key',64)->nullable()->unique();
            $t->string('checkout_fingerprint',64)->nullable();
            $t->string('tracking_token_hash',64)->nullable();
            $t->json('shipping_address_snapshot')->nullable();
            $t->string('currency',3)->default('EGP');
            $t->timestamp('hold_expires_at')->nullable()->index();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('cancellation_requested_at')->nullable();
            $t->json('policy_snapshot')->nullable();
            $t->json('legal_snapshot')->nullable();
            $t->decimal('tax_total',18,2)->default(0);
        });
        Schema::table('store_sub_orders',function(Blueprint $t) {
            $t->unsignedBigInteger('commission_minor')->default(0);
            $t->json('financial_policy_snapshot')->nullable();
            $t->timestamp('seller_ready_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamp('collected_at')->nullable();
            $t->timestamp('remitted_at')->nullable();
            $t->timestamp('settlement_available_at')->nullable()->index();
            $t->timestamp('commission_recognized_at')->nullable();
            $t->timestamp('settled_at')->nullable();
            $t->unsignedBigInteger('refunded_minor')->default(0);
            $t->unsignedBigInteger('seller_reversed_minor')->default(0);
            $t->unsignedBigInteger('commission_reversed_minor')->default(0);
            $t->timestamp('refund_requested_at')->nullable();
            $t->decimal('tax_total',18,2)->default(0);
            $t->decimal('discount_total',18,2)->default(0);
        });
        Schema::table('store_order_items',function(Blueprint $t) {
            $t->unsignedBigInteger('product_id')->nullable();
            $t->unsignedBigInteger('selling_unit_id')->nullable();
            $t->string('unit_name_snapshot')->nullable();
            $t->decimal('conversion_factor_snapshot',18,6)->default(1);
            $t->decimal('base_quantity',18,6)->nullable();
            $t->json('return_policy_snapshot')->nullable();
            $t->decimal('tax_total',18,2)->default(0);
            $t->decimal('discount_total',18,2)->default(0);
        });
        Schema::create('store_shipments',function(Blueprint $t) {
            $t->id(); $t->foreignId('store_sub_order_id')->constrained('store_sub_orders');
            $t->unsignedInteger('sequence')->default(1); $t->foreignId('company_id')->constrained('companies');
            $t->foreignId('shipping_account_id')->constrained('store_shipping_accounts');
            $t->string('idempotency_key',64)->unique(); $t->string('status',30)->default('waiting_ready');
            $t->string('provider_reference')->nullable(); $t->string('tracking_number')->nullable();
            $t->text('integration_snapshot'); $t->json('pickup_address'); $t->json('delivery_address');
            $t->string('cod_beneficiary',20); $t->unsignedBigInteger('cod_minor')->default(0);
            $t->string('currency',3)->default('EGP'); $t->json('quote_snapshot');
            $t->text('label_url')->nullable(); $t->timestamps();
            $t->unique(['store_sub_order_id','sequence']); $t->unique(['shipping_account_id','provider_reference']);
        });
        Schema::create('store_outbox',function(Blueprint $t) {
            $t->id(); $t->string('kind',60); $t->string('deduplication_key',150)->unique();
            $t->text('payload'); $t->string('status',30)->default('pending');
            $t->unsignedInteger('attempts')->default(0); $t->timestamp('available_at')->index();
            $t->timestamp('locked_until')->nullable(); $t->string('last_error',255)->nullable(); $t->timestamps();
            $t->index(['status','available_at']);
        });
        Schema::create('store_payouts',function(Blueprint $t) {
            $t->id(); $t->foreignId('account_id')->constrained('store_seller_accounts');
            $t->string('operation_key',64)->unique(); $t->unsignedBigInteger('amount_minor');
            $t->string('status',30)->default('requested'); $t->text('destination_snapshot');
            $t->string('provider_reference')->nullable(); $t->string('method',30)->default('manual');
            $t->foreignId('requested_by')->nullable()->constrained('users');
            $t->foreignId('approved_by')->nullable()->constrained('users'); $t->text('notes')->nullable();
            $t->timestamps(); $t->timestamp('paid_at')->nullable();
        });
        Schema::create('store_refunds',function(Blueprint $t) {
            $t->id(); $t->foreignId('store_order_id')->constrained('store_orders');
            $t->foreignId('store_sub_order_id')->nullable()->constrained('store_sub_orders');
            $t->foreignId('payment_transaction_id')->nullable()->constrained('payment_transactions');
            $t->string('operation_key',100)->unique(); $t->unsignedBigInteger('amount_minor');
            $t->string('status',30)->default('requested'); $t->string('reason',100);
            $t->string('provider_reference')->nullable(); $t->timestamps(); $t->timestamp('completed_at')->nullable();
            $t->json('allocation_snapshot')->nullable();
        });
        Schema::create('store_returns',function(Blueprint $t) {
            $t->id(); $t->foreignId('store_order_item_id')->constrained('store_order_items');
            $t->unsignedInteger('quantity'); $t->string('reason',40);
            $t->string('status',30)->default('requested'); $t->string('shipping_payer',20);
            $t->unsignedBigInteger('refund_minor'); $t->text('customer_notes')->nullable();
            $t->text('admin_notes')->nullable(); $t->timestamps();
        });
        Schema::create('store_order_events',function(Blueprint $t) {
            $t->id(); $t->foreignId('store_order_id')->nullable()->constrained('store_orders');
            $t->foreignId('company_id')->nullable()->constrained('companies');
            $t->foreignId('actor_id')->nullable()->constrained('users'); $t->string('type',70);
            $t->json('metadata')->nullable(); $t->timestamp('created_at'); $t->index(['store_order_id','id']);
        });
        Schema::table('company_subscriptions',function(Blueprint $t) {
            $t->json('feature_overrides')->nullable();
            $t->timestamp('paid_at')->nullable();
        });
        Schema::table('products',function(Blueprint $t) { $t->json('store_return_policy')->nullable(); });
    }
    public function down(): void {
        Schema::table('products',fn(Blueprint $t)=>$t->dropColumn('store_return_policy'));
        Schema::table('company_subscriptions',fn(Blueprint $t)=>$t->dropColumn(['feature_overrides','paid_at']));
        foreach(['store_order_events','store_returns','store_refunds','store_payouts','store_outbox','store_shipments'] as $table) Schema::dropIfExists($table);
        Schema::table('store_order_items',fn(Blueprint $t)=>$t->dropColumn(['product_id','selling_unit_id','unit_name_snapshot','conversion_factor_snapshot','base_quantity','return_policy_snapshot','tax_total','discount_total']));
        Schema::table('store_sub_orders',fn(Blueprint $t)=>$t->dropColumn(['commission_minor','financial_policy_snapshot','seller_ready_at','delivered_at','collected_at','remitted_at','settlement_available_at','commission_recognized_at','settled_at','refunded_minor','seller_reversed_minor','commission_reversed_minor','refund_requested_at','tax_total','discount_total']));
        // Keep customer/address columns nullable: populated guest orders must never be destroyed by a rollback.
        Schema::table('store_orders',fn(Blueprint $t)=>$t->dropColumn(['checkout_key','checkout_fingerprint','tracking_token_hash','shipping_address_snapshot','currency','hold_expires_at','confirmed_at','cancellation_requested_at','policy_snapshot','legal_snapshot','tax_total']));
        foreach(['store_checkout_quotes','store_stock_allocations','store_account_holds','store_financial_lines','store_financial_journals','store_seller_ledger','store_seller_accounts','store_seller_profiles','store_shipping_rates','store_shipping_accounts','store_settings'] as $table) Schema::dropIfExists($table);
    }
};
