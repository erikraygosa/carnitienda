<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->foreignId('warehouse_id')->nullable()->after('id')->constrained('warehouses');
    });
  }
  public function down(): void {
    Schema::table('users', function (Blueprint $t) {
      $t->dropConstrainedForeignId('warehouse_id');
    });
  }
};
