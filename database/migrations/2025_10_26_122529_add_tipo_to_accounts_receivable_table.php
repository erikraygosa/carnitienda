<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->enum('tipo', ['CARGO','ABONO'])->default('CARGO')->after('fecha');
        });
    }

    public function down(): void
    {
        Schema::table('accounts_receivable', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
