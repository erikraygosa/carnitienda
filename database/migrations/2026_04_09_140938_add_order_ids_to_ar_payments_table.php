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
    Schema::table('ar_payments', function (Blueprint $table) {
        $table->json('order_ids')->nullable()->after('nota');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ar_payments', function (Blueprint $table) {
            //
        });
    }
};
