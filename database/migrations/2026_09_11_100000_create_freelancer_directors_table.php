<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cadastro da diretoria que aprova os lotes de freelancer.
     *
     * Até aqui o diretor morava no `.env` (FREELANCER_DIRECTOR_*): trocar o
     * destinatário exigia deploy. Com a redação 2 do contrato ele passa também
     * a ASSINAR — a imagem da assinatura dele é aplicada ao documento quando o
     * lote é aprovado —, e isso precisa de uma tela que a gerência opere.
     *
     * Os registros NÃO são editados: cada alteração cria uma linha nova, e vale
     * a mais recente. É a mesma razão do versionamento da redação — contratos
     * aprovados apontam para a linha que os assinou (`freelancer_director_id`),
     * e editar o nome ou a imagem no lugar reescreveria a assinatura de todo
     * documento já aprovado.
     *
     * - `signature_path` — PNG no disco PRIVADO (`local`), nunca no público: é
     *   a assinatura de uma pessoa. Nulo só no registro importado do `.env`,
     *   que ainda não tem imagem; a tela pede o envio.
     * - `created_by` — sem foreign key para `users` de propósito: o model User
     *   fixa a conexão `mysql`, e a FK amarraria esta tabela a ela.
     */
    public function up(): void
    {
        Schema::create('freelancer_directors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('signature_path')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // O diretor que já recebe os e-mails continua recebendo no dia do
        // deploy: nome e e-mail vêm do `.env` de hoje. A imagem não existe lá —
        // sem ela os lotes com contrato da redação 2 não seguem para a
        // diretoria, e a tela avisa o que falta.
        $email = env('FREELANCER_DIRECTOR_EMAIL');

        if (filled($email)) {
            DB::table('freelancer_directors')->insert([
                'name' => env('FREELANCER_DIRECTOR_NAME') ?: 'Diretoria',
                'email' => trim($email),
                'signature_path' => null,
                'created_by' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_directors');
    }
};
