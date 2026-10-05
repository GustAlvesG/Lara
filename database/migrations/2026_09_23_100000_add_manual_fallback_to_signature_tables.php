<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O caminho degradado para ambiente SEM HTTPS.
 *
 * `getUserMedia` — que abre a câmera — só funciona em origem segura. Em HTTP
 * comum o tablet não lê o QR e não tira a foto. As duas colunas abaixo tornam
 * esse cenário utilizável sem fingir que ele é equivalente ao normal:
 *
 * `signature_requests.manual_code_hash` guarda o hash de um código CURTO, que
 * o atendente dita e a pessoa digita no tablet. É a mesma liberação, com as
 * mesmas travas (uso único, prazo, vínculo com um documento) — só muda como o
 * segredo chega ao aparelho. Só é gerado quando o modo manual está ligado na
 * configuração, e o token longo do QR continua existindo em paralelo.
 *
 * `signature_evidences.photo_skipped_reason` registra POR QUE não há foto. Um
 * campo nulo diria a mesma coisa para "o modelo não pedia foto" e para "a
 * câmera não abriu" — e a segunda é uma evidência a menos, que precisa
 * aparecer no manifesto em vez de sumir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            /*
             | Nullable e sem unique: a maioria das liberações não tem código
             | manual, e várias linhas com NULL conviveriam mal com um índice
             | único em alguns bancos. A unicidade é garantida na geração, que
             | sorteia de novo em caso de colisão entre códigos VIVOS.
             */
            $table->char('manual_code_hash', 64)->nullable()->index()->after('token_hash');
        });

        Schema::table('signature_evidences', function (Blueprint $table) {
            $table->string('photo_skipped_reason', 60)->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('signature_requests', function (Blueprint $table) {
            $table->dropColumn('manual_code_hash');
        });

        Schema::table('signature_evidences', function (Blueprint $table) {
            $table->dropColumn('photo_skipped_reason');
        });
    }
};
