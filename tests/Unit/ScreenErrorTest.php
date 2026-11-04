<?php

namespace Tests\Unit;

use App\Services\Lara\ScreenError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScreenErrorTest extends TestCase
{
    #[DataProvider('statusETipo')]
    public function test_tipo_sai_do_status(?int $status, string $tipo): void
    {
        $this->assertSame($tipo, ScreenError::from($status, null)->tipo);
    }

    public static function statusETipo(): array
    {
        return [
            'sem status' => [null, ScreenError::TIPO_REGRA],
            '401' => [401, ScreenError::TIPO_SESSAO],
            '403' => [403, ScreenError::TIPO_PERMISSAO],
            '404' => [404, ScreenError::TIPO_NAO_ENCONTRADO],
            '409' => [409, ScreenError::TIPO_REGRA],
            '419' => [419, ScreenError::TIPO_SESSAO],
            '422' => [422, ScreenError::TIPO_VALIDACAO],
            '429' => [429, ScreenError::TIPO_LIMITE],
            '500' => [500, ScreenError::TIPO_SISTEMA],
            '503' => [503, ScreenError::TIPO_SISTEMA],
        ];
    }

    #[DataProvider('textosTecnicos')]
    public function test_mensagem_tecnica_vira_erro_do_sistema_sem_texto(string $texto): void
    {
        $erro = ScreenError::from(null, $texto);

        $this->assertSame(ScreenError::TIPO_SISTEMA, $erro->tipo);
        $this->assertNull($erro->texto);
    }

    public static function textosTecnicos(): array
    {
        return [
            ['SQLSTATE[42S22]: Column not found'],
            ['Call to a member function name() on null'],
            ['Illuminate\Database\QueryException: falhou'],
            ['Erro em /var/www/app/Http/Controllers/AccessController.php linha 40'],
            ['cURL error 28: Operation timed out'],
        ];
    }

    public function test_erro_500_nunca_leva_texto(): void
    {
        $this->assertNull(ScreenError::from(500, 'Mensagem qualquer')->texto);
    }

    public function test_mensagem_padrao_do_framework_nao_conta_como_texto(): void
    {
        $erro = ScreenError::from(403, 'This action is unauthorized.');

        $this->assertNull($erro->texto);
        $this->assertStringContainsString('acesso negado', $erro->pergunta());
    }

    public function test_mensagem_escrita_para_a_pessoa_entra_na_pergunta(): void
    {
        $erro = ScreenError::from(403, 'Só o autor pode editar este aviso.');

        $this->assertSame(
            'Apareceu este erro na tela: "Só o autor pode editar este aviso.". O que aconteceu e como resolvo?',
            $erro->pergunta()
        );
    }

    public function test_cpf_e_mascarado_com_e_sem_pontuacao(): void
    {
        $this->assertSame('CPF [CPF] e [CPF]', ScreenError::from(422, 'CPF 123.456.789-09 e 12345678909')->texto);
    }

    public function test_texto_longo_e_cortado_e_html_sai(): void
    {
        $erro = ScreenError::from(422, '<b>' . str_repeat('a', 2000) . '</b>');

        $this->assertStringNotContainsString('<b>', $erro->texto);
        $this->assertLessThan(500, mb_strlen($erro->pergunta()));
    }

    public function test_status_fora_da_faixa_e_ignorado(): void
    {
        $this->assertNull(ScreenError::from(200, null)->status);
        $this->assertNull(ScreenError::from('abc', null)->status);
    }
}
