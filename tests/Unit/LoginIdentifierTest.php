<?php

namespace Tests\Unit;

use App\Http\Requests\Auth\LoginRequest;
use Tests\TestCase;

/**
 * O login aceita duas identificações: e-mail (padrão do Breeze) e matrícula.
 * Quem decide qual delas vale é a aba escolhida no formulário — e essa escolha
 * atravessa validação, tentativa de autenticação e chave de rate limit, então
 * é testada aqui.
 *
 * Sem banco: o model User está preso à conexão `mysql` e a suíte roda em
 * SQLite, então tocar no banco escreveria no ambiente de homologação.
 */
class LoginIdentifierTest extends TestCase
{
    private function request(array $input): LoginRequest
    {
        $request = LoginRequest::create('/login', 'POST', $input);
        $request->setContainer($this->app);

        return $request;
    }

    public function test_usa_email_por_padrao(): void
    {
        $request = $this->request(['email' => 'a@b.com', 'password' => 'x']);

        $this->assertSame('email', $request->identifierField());
    }

    public function test_a_aba_escolhida_decide_o_identificador(): void
    {
        $request = $this->request([
            'login_type' => 'matricula',
            'matricula' => '12345',
            'password' => 'x',
        ]);

        $this->assertSame('matricula', $request->identifierField());
    }

    /** Sem `login_type` (JS desligado), vale o campo que veio preenchido. */
    public function test_cai_na_matricula_quando_so_ela_veio(): void
    {
        $request = $this->request(['matricula' => '12345', 'password' => 'x']);

        $this->assertSame('matricula', $request->identifierField());
    }

    /**
     * `login_type` vem do formulário, então não pode escolher a coluna do
     * banco: qualquer valor fora da lista volta para e-mail.
     */
    public function test_login_type_invalido_nao_vira_coluna(): void
    {
        $request = $this->request([
            'login_type' => 'pin',
            'email' => 'a@b.com',
            'password' => 'x',
        ]);

        $this->assertSame('email', $request->identifierField());
    }

    public function test_exige_o_campo_da_aba_ativa(): void
    {
        $porMatricula = $this->request(['login_type' => 'matricula'])->rules();
        $this->assertContains('required', $porMatricula['matricula']);
        $this->assertContains('nullable', $porMatricula['email']);

        $porEmail = $this->request(['login_type' => 'email'])->rules();
        $this->assertContains('required', $porEmail['email']);
        $this->assertContains('nullable', $porEmail['matricula']);
    }

    /** Matrícula tem 5 caracteres no banco; a validação acompanha. */
    public function test_matricula_limitada_a_cinco_caracteres(): void
    {
        $rules = $this->request(['login_type' => 'matricula'])->rules();

        $this->assertContains('max:5', $rules['matricula']);
    }

    /**
     * O rate limit conta por identificador. Se a chave ignorasse a matrícula,
     * todas as tentativas por matrícula cairiam no mesmo balde (`|ip`) e cinco
     * erros de um usuário travariam o login de todos naquele IP.
     */
    public function test_rate_limit_separa_as_matriculas(): void
    {
        $um = $this->request(['login_type' => 'matricula', 'matricula' => '11111'])->throttleKey();
        $outro = $this->request(['login_type' => 'matricula', 'matricula' => '22222'])->throttleKey();

        $this->assertNotSame($um, $outro);
        $this->assertStringStartsWith('11111|', $um);
    }
}
