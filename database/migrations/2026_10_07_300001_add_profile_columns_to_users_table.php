<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Badan usaha
            $table->string('company_type', 20)->nullable()->after('avatar');  // PT, CV, UD, Perorangan, dll.
            $table->string('company_name', 255)->nullable()->after('company_type');

            // Telepon / WhatsApp
            $table->string('phone', 30)->nullable()->after('company_name');

            // Wilayah Indonesia (chained: provinsi → kab/kota → kecamatan → kelurahan)
            // Simpan sebagai code (string) sesuai skema laravolt/indonesia v4
            $table->string('province_id', 10)->nullable()->after('phone');
            $table->string('city_id', 10)->nullable()->after('province_id');
            $table->string('district_id', 10)->nullable()->after('city_id');
            $table->string('village_id', 10)->nullable()->after('district_id');

            // Nama provinsi/kota/kecamatan/kelurahan disimpan sebagai denormalized cache
            // agar tidak perlu join saat export atau menampilkan profil
            $table->string('province_name', 100)->nullable()->after('village_id');
            $table->string('city_name', 100)->nullable()->after('province_name');
            $table->string('district_name', 100)->nullable()->after('city_name');
            $table->string('village_name', 100)->nullable()->after('district_name');

            // Alamat detail bebas (nomor jalan, RT/RW, dll.)
            $table->text('address')->nullable()->after('village_name');

            // Kode pos
            $table->string('postal_code', 10)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'company_type', 'company_name', 'phone',
                'province_id', 'city_id', 'district_id', 'village_id',
                'province_name', 'city_name', 'district_name', 'village_name',
                'address', 'postal_code',
            ]);
        });
    }
};
