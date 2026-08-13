<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Define a senha de aprovação de ordem de compra de um usuário.
 *
 * É a senha do site externo (DMZ), **não** a do painel — campo próprio, para
 * que um vazamento lá fora não vire acesso ao painel interno. Ver a migration
 * `add_approval_password_and_phone_to_users_table`.
 *
 * Existe como comando, e não como uma linha de Tinker, por três motivos: a
 * operação se repete a cada diretor novo e a cada redefinição; digitada no
 * terminal, a senha não fica no histórico do shell (`secret()` não ecoa); e
 * aqui dá para conferir antes de gravar que a pessoa é mesmo da Diretoria e
 * está ativa — sem isso, a senha é gravada e o login recusa depois, sem dizer
 * por quê.
 *
 * Não há autoatendimento de recuperação de senha no site externo, de propósito:
 * um e-mail de reset vale a conta inteira. Redefinir é por aqui.
 */
class SetApprovalPassword extends Command
{
    protected $signature = 'questor:senha-aprovacao
                            {matricula? : Matrícula do aprovador}
                            {--senha= : Senha (evite: fica no histórico do shell)}
                            {--listar : Lista os diretores e quem ainda não tem senha}';

    protected $description = 'Define a senha de aprovação de compras de um usuário do setor Diretoria.';

    /** Tamanho mínimo. Não é PIN: é senha, e o site está exposto na DMZ. */
    private const MIN = 8;

    public function handle(): int
    {
        if ($this->option('listar')) {
            return $this->listar();
        }

        $matricula = $this->argument('matricula') ?: $this->ask('Matrícula do aprovador');

        $user = User::where('matricula', $matricula)->first();

        if (!$user) {
            $this->error("Nenhum usuário com a matrícula {$matricula}.");

            return self::FAILURE;
        }

        $this->line("Usuário: <options=bold>{$user->name}</> (id {$user->id})");

        if ((int) $user->status_id !== 1) {
            $this->error('Usuário inativo — o login de aprovação vai recusar mesmo com senha definida.');

            return self::FAILURE;
        }

        // Aviso, não bloqueio: pode-se preparar a senha antes de vincular a
        // pessoa ao setor. Mas ela precisa saber que ainda não vai entrar.
        if (!$user->isDirector()) {
            $this->warn('Este usuário NÃO está no setor Diretoria — o login vai recusar com 403 até ele ser vinculado.');

            if (!$this->confirm('Definir a senha mesmo assim?', false)) {
                return self::FAILURE;
            }
        }

        $senha = $this->option('senha') ?: $this->secret('Senha de aprovação (não aparece na tela)');

        if (mb_strlen((string) $senha) < self::MIN) {
            $this->error('A senha precisa de pelo menos ' . self::MIN . ' caracteres.');

            return self::FAILURE;
        }

        if (!$this->option('senha')) {
            if ($senha !== $this->secret('Repita a senha')) {
                $this->error('As senhas não conferem.');

                return self::FAILURE;
            }
        }

        // O cast `hashed` do model faz o bcrypt: nunca gravar já hasheado aqui,
        // ou o hash seria hasheado de novo e nenhuma senha bateria.
        $user->approval_password = $senha;
        $user->save();

        $this->info('Senha de aprovação definida.');
        $this->line("Login: matrícula <options=bold>{$user->matricula}</> em POST /api/aprovacao/login");

        if (blank($user->phone)) {
            $this->warn('Este usuário está sem telefone — não vai receber o aviso de WhatsApp.');
        }

        return self::SUCCESS;
    }

    /** Quem já pode entrar no site de aprovação, e quem falta. */
    private function listar(): int
    {
        $diretores = User::directors()->get();

        if ($diretores->isEmpty()) {
            $this->warn('Nenhum usuário no setor Diretoria.');

            return self::SUCCESS;
        }

        $this->table(
            ['Matrícula', 'Nome', 'Ativo', 'Senha de aprovação', 'Telefone'],
            $diretores->map(fn(User $u) => [
                $u->matricula ?: '—',
                $u->name,
                (int) $u->status_id === 1 ? 'sim' : 'NÃO',
                $u->hasApprovalPassword() ? 'definida' : 'FALTA',
                $u->phone ?: '—',
            ])->all()
        );

        return self::SUCCESS;
    }
}
