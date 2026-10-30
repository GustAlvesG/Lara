<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editor visual do Disparo de E-mails: quem não sabe HTML escreve o texto
 * formatado (`body`) e O Lara monta o e-mail (`html`) no layout do clube
 * (App\Services\EmailCampaign\EmailComposer). `editor` diz qual dos dois a
 * campanha usa; o padrão do banco é `html` para as campanhas que já existiam
 * (o HTML delas foi escrito à mão) — as novas nascem `visual` pela tela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('email_campaigns', 'editor')) {
                $table->string('editor', 10)->default('html')->after('reply_to');
            }

            if (! Schema::hasColumn('email_campaigns', 'body')) {
                $table->longText('body')->nullable()->after('editor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_campaigns', function (Blueprint $table) {
            $table->dropColumn(['editor', 'body']);
        });
    }
};
