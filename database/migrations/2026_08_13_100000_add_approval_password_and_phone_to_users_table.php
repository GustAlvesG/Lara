<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Senha de aprovação e telefone.
 *
 * **`approval_password` é campo próprio, e não a senha do painel, de propósito.**
 * O login da Lara já aceita matrícula + senha; se o aprovador externo usasse a
 * coluna `password`, a credencial digitada no site em DMZ seria a mesma que abre
 * o painel interno — rotacionada, vazada e recuperada junto com ela. O Kiosk de
 * assinatura resolveu o mesmo problema criando `pin`; este é o equivalente
 * alfanumérico, para a superfície de aprovação de ordem de compra.
 *
 * `phone` é opcional: é para onde vai o aviso de WhatsApp ("há ordens
 * aguardando você"). Sem telefone o usuário simplesmente não é avisado — o que
 * não impede ninguém de entrar no site e ver a fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('approval_password')->nullable()->after('pin');
            $table->string('phone', 20)->nullable()->after('matricula');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approval_password', 'phone']);
        });
    }
};
