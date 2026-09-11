<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foto de identificação do freelancer, para a portaria reconhecer quem está
     * entrando. Guarda só o nome do arquivo em `public/images`, como o
     * terceirizado (`company_workers.image`).
     *
     * As fotos que já existem estão no cadastro de terceirizado de cada
     * freelancer: `php artisan freelancers:migrar-fotos` as traz para cá.
     */
    public function up(): void
    {
        Schema::table('freelancers', function (Blueprint $table) {
            $table->string('image')->nullable()->after('telephone');
        });
    }

    public function down(): void
    {
        Schema::table('freelancers', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
