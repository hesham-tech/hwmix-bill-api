<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ResetTransactionalData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'db:reset-transactions {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all transactional data (invoices, profits, cash balances) but keep master data (users, products)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('This will DELETE all invoices, transactions, expenses, and reset stock/cash balances. Are you sure?')) {
            $this->info('Operation cancelled.');
            return;
        }

        $this->info('Starting database reset...');

        Schema::disableForeignKeyConstraints();

        // 1. Truncate Transactional Tables
        $tables = [
            'invoices',
            'invoice_items',
            'invoice_payments',
            'transactions',
            'expenses',
            'financial_ledger',
            'daily_sales_summary',
            'monthly_sales_summary',
            'revenues',
            'profits',
            'payments',
            'installments',
            'installment_plans',
            'installment_payments',
            'installment_payment_details',
            'payment_installment',
            'digital_product_deliveries',
            'activity_logs',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("- Truncated table: {$table}");
            }
        }

        // 2. Reset Aggregated/Cached Values

        // Reset Stocks
        if (Schema::hasTable('stocks')) {
            DB::table('stocks')->update(['quantity' => 0]);
            $this->info('- Reset stocks to 0');
        }

        // Reset Cash Boxes
        if (Schema::hasTable('cash_boxes')) {
            DB::table('cash_boxes')->update(['balance' => 0]);
            $this->info('- Reset cash box balances to 0');
        }

        // Reset User Stats
        if (Schema::hasColumn('users', 'sales_count')) {
            DB::table('users')->update(['sales_count' => 0]);
            $this->info('- Reset user sales counts');
        }
        // Note: Check if users have a balance column. Based on earlier views, usually balances are calculated. 
        // If there is a 'balance' column in users table, uncomment below:
        // if (Schema::hasColumn('users', 'balance')) {
        //    DB::table('users')->update(['balance' => 0]);
        // }

        // Reset Product Stats
        if (Schema::hasColumn('products', 'sales_count')) {
            DB::table('products')->update(['sales_count' => 0]);
            $this->info('- Reset product sales counts');
        }

        Schema::enableForeignKeyConstraints();

        $this->info('Transactional data reset complete! 🚀');
        $this->info('Master data (Users, Products, etc.) preserved.');
    }
}
