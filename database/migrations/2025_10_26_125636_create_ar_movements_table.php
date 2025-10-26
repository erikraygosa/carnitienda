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
        Schema::create('ar_movements', function (Blueprint $t) {
    $t->id();
    $t->foreignId('client_id')->constrained('clients');
    $t->date('fecha');
    $t->enum('tipo', ['CARGO','ABONO']);
    $t->decimal('monto', 12, 2);
    $t->string('descripcion')->nullable();
    $t->nullableMorphs('source'); // source_type, source_id
    $t->foreignId('created_by')->nullable()->constrained('users');
    $t->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ar_movements');
    }
};
