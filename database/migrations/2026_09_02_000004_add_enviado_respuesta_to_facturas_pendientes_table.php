<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_pendientes', function (Blueprint $table) {
            $table->boolean('enviado')->default(false)->after('payload');
            $table->text('respuesta')->nullable()->after('enviado');
        });
    }

    public function down(): void
    {
        Schema::table('facturas_pendientes', function (Blueprint $table) {
            $table->dropColumn(['enviado', 'respuesta']);
        });
    }
};
