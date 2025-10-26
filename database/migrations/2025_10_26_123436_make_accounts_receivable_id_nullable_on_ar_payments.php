<?php

// database/migrations/xxxx_xx_xx_xxxxxx_make_accounts_receivable_id_nullable_on_ar_payments.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ar_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('accounts_receivable_id')->nullable()->change();
        });
    }
    public function down(): void {
        Schema::table('ar_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('accounts_receivable_id')->nullable(false)->change();
        });
    }
};
