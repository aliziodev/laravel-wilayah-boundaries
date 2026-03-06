<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('region_boundaries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('code', 13)->unique();      // Relasi via code ke tabel utama
            $table->unsignedTinyInteger('level');       // 1=prov, 2=kab, 3=kec, 4=desa
            $table->decimal('lat', 10, 7)->nullable();  // Centroid latitude
            $table->decimal('lng', 10, 7)->nullable();  // Centroid longitude
            $table->longText('path');                   // Polygon coordinates (JSON array)
            $table->tinyInteger('status')->default(1);  // 1=verified, 0=draft
            $table->index('code');
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('region_boundaries');
    }
};
