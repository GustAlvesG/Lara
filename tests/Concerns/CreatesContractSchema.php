<?php

namespace Tests\Concerns;

use App\Models\SignatureDirectorArea;
use App\Models\SignatureDirectorAreaHolder;
use App\Services\Signature\Contracts\ContractContent;

/**
 * Tabelas dos Contratos (Assinaturas → Contratos) no SQLite da suíte: as do
 * módulo de assinatura, a dos contratos e a de setores — todas pelas
 * migrations de verdade, como em CreatesSignatureSchema.
 */
trait CreatesContractSchema
{
    use CreatesSignatureSchema;

    /**
     * @param  bool  $withSectors  false quando o teste já migrou o esquema de
     *                             acesso (MigratesAccessSchema cria `sectors`)
     */
    protected function createContractSchema(bool $withSectors = true): void
    {
        $this->createSignatureSchema();

        $arquivos = $withSectors ? ['2026_06_05_102714_create_sectors_table.php'] : [];
        $arquivos[] = '2026_10_29_100000_create_signature_contracts.php';
        $arquivos[] = '2026_10_29_110000_create_signature_contract_ai_reviews.php';

        foreach ($arquivos as $arquivo) {
            (require base_path('database/migrations/' . $arquivo))->up();
        }
    }

    /** O presidente: titular da área configurada em `signature.contracts.president_area`. */
    protected function criaPresidente(string $nome = 'Alex Ribeiro da Silva', int $userId = 50): SignatureDirectorArea
    {
        $area = SignatureDirectorArea::create(['name' => config('signature.contracts.president_area'), 'active' => true]);

        SignatureDirectorAreaHolder::create([
            'signature_director_area_id' => $area->id,
            'user_id' => $userId,
            'user_name' => $nome,
            'role' => SignatureDirectorAreaHolder::ROLE_PRIMARY,
            'starts_on' => now()->toDateString(),
        ]);

        return $area;
    }

    /**
     * Um contrato completo, sem nenhum problema que trave o envio — o
     * CT 175/26 de verdade, encurtado.
     *
     * @return array<string, mixed>
     */
    protected function conteudoCompleto(array $troca = []): array
    {
        return ContractContent::normalize(array_merge([
            'title' => 'Contrato de prestação de serviços',
            'establishment' => 'pet',
            'contractor_term' => 'CONTRATANTE',
            'parties' => [[
                'uid' => 'parteacs01',
                'role' => 'contratada',
                'term' => 'CONTRATADA',
                'kind' => 'pj',
                'name' => 'A.C.S TRADING LTDA',
                'document' => '04.758.575/0001-29',
                'address' => 'Rua Vereador Francisco Evangelista Delgado, nº 672, São Lucas, Volta Redonda/RJ',
                'rep_name' => 'Maria Márcia Prazeres do Prado',
                'rep_cpf' => '529.982.247-25',
                'email' => 'contato@acs.example.com',
            ]],
            'variables' => [
                ['label' => 'Valor global', 'type' => 'money', 'value' => '8.900,00'],
                ['label' => 'Multa', 'type' => 'percent', 'value' => '10'],
            ],
            'considerations' => ['<p>A [[contratada]] é pessoa jurídica cujo objeto social abrange serviços de engenharia;</p>'],
            'clauses' => [
                ['uid' => 'clobjeto01', 'title' => 'Objeto', 'items' => [
                    ['uid' => 'itobjeto11', 'level' => 1, 'html' => '<p>Manutenção preventiva na subestação da PET.</p>'],
                    ['uid' => 'itobjeto12', 'level' => 2, 'html' => '<p>Pára-raios 13,8 KV.</p>'],
                ]],
                ['uid' => 'clvalor001', 'title' => 'Valor', 'items' => [
                    ['uid' => 'itvalor011', 'level' => 1, 'html' => '<p>O valor global é de [[valor_global]].</p>'],
                ]],
                ['uid' => 'clmulta001', 'title' => 'Multa', 'items' => [
                    ['uid' => 'itmulta011', 'level' => 1, 'html' => '<p>Multa de [[multa]] do valor previsto no item [[ref:itvalor011]].</p>'],
                ]],
            ],
            'signers' => [
                ['uid' => 'sigacs0001', 'party' => 'contratada', 'source' => 'person', 'name' => 'Maria Márcia Prazeres do Prado', 'cpf' => '529.982.247-25', 'email' => 'contato@acs.example.com'],
                ['uid' => 'sigtest001', 'party' => 'testemunha', 'source' => 'person', 'name' => 'Edalmo de Mendonça', 'cpf' => '111.444.777-35'],
            ],
        ], $troca));
    }
}
