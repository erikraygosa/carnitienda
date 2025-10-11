<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->morphs('document'); // document_type, document_id
            $table->string('action', 40);
            $table->string('old_status', 30)->nullable();
            $table->string('new_status', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('nota')->nullable();
            $table->timestamps();
            $table->index(['document_type','document_id','action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_activity_logs');
    }
};
