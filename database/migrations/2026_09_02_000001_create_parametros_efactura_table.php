<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parametros_efactura', function (Blueprint $table) {
            $table->id();
            $table->integer('contribuyente_id');
            $table->string('pass');
            $table->string('timbrado');
            $table->dateTime('fec_inicio');
            $table->string('sucursal');
            $table->timestamps();
        });

        DB::table('parametros_efactura')->insert([
            'contribuyente_id' => 33,
            'pass' => 'c6e8a6f0d815e1fd9358d76b208799efbb40c6d8155be29903dcbe5b99125c07',
            'timbrado' => '12558948',
            'fec_inicio' => '2021-08-25 00:00:00',
            'sucursal' => 'Central',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('parametros_efactura');
    }
};
