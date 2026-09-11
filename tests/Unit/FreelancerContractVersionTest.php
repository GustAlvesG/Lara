<?php

namespace Tests\Unit;

use App\Models\Freelancer;
use App\Models\FreelancerDirector;
use App\Models\FreelancerService;
use App\Models\FunctionFreelancer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Congelamento do documento: a redação das cláusulas e a qualificação das partes
 * que o texto cita.
 *
 * O contrato é montado ao vivo — o texto vem dos templates, os dados vêm do
 * cadastro. As duas coisas mudam depois da assinatura, e sem o congelamento um
 * contrato já firmado passaria a dizer outra coisa. Estes testes cobrem as duas
 * metades da garantia: a regra (quando congela, o que devolve) e o LACRE dos
 * arquivos de cada redação já em uso.
 *
 * Sem banco: são funções puras do model mais a leitura dos templates.
 */
class FreelancerContractVersionTest extends TestCase
{
    /**
     * Sha256 dos arquivos de cada redação CONGELADA, com as quebras de linha
     * normalizadas (o repositório é clonado em Windows e em Linux).
     *
     * É isto que impede que uma cláusula antiga seja "corrigida" no lugar de
     * gerar uma redação nova: contratos assinados apontam para estes arquivos, e
     * editá-los mudaria o texto de documentos já firmados.
     *
     * Ao criar a redação N+1, acrescente aqui as entradas da N — e nunca altere
     * uma linha já existente.
     */
    private const SEALS = [
        1 => [
            'original-clauses.blade.php' => 'd1fc2f6e93974c17a46472c763ad9ccaa3f6af38bec8c94b19624c80eb7b4be5',
            'amendment-clauses.blade.php' => '7be6e6e50add0ec1ffa025b5c25aaaed36ba7e217b533fc91c9d276e8ec2ae3a',
            'commission-clauses.blade.php' => 'a64477bc85df3dca9dca36576f209a4472752cb3ca666ef6f1be759ad766ebd6',
            'pix-clause.blade.php' => '268b8bd84518af12b0fbd018117b1d63c3e223bf5eef5a9f204c10b5a3114521',
        ],
        // Lacrada já na criação, e não só quando surgir a 3: ela passa a ser
        // assinada no dia do deploy, e daí em diante vale a mesma regra da 1.
        2 => [
            'original-clauses.blade.php' => 'd54db18848d6f3a09c6d3393aad6424651260816c1e493c141a6bb66441fd9a6',
            'amendment-clauses.blade.php' => '62e043afd4e53fa2200016338c5b05e8cf1aa5859e1b508c83809b78e0577e74',
            'commission-clauses.blade.php' => 'e3001fb450f976ee0261d4a96f135a0c629588e5f83ee1f4c86f4ec8e95aed12',
            'pix-clause.blade.php' => '79d33ee4f32e2a2d4e5e454a6ea4053e5f9b49936af732334a1094d6be9b70a3',
        ],
    ];

    private function versionPath(int $version): string
    {
        return resource_path('views/freelancer/services/partials/contract/v' . $version);
    }

    /** Hash do arquivo com as quebras de linha normalizadas. */
    private function seal(string $file): string
    {
        return hash('sha256', str_replace("\r\n", "\n", file_get_contents($file)));
    }

    private function service(array $attributes = [], ?Freelancer $freelancer = null, ?string $function = null): FreelancerService
    {
        $service = new FreelancerService($attributes);

        $service->setRelation('freelancer', $freelancer ?? new Freelancer([
            'name' => 'Maria da Silva',
            'cpf' => '12345678901',
            'rg' => '12.345.678',
            'nacionality' => 'brasileira',
            'civil_status' => 'solteira',
            'address' => 'Rua A, 100 — Volta Redonda/RJ',
        ]));

        $service->setRelation('functionFreelancer', new FunctionFreelancer(['name' => $function ?? 'Garçom']));

        return $service;
    }

    /* ---------------------------------------------------------------------
     | Lacre das redações
     |---------------------------------------------------------------------*/

    /**
     * O teste que dá sentido a todo o resto: se ele quebra, alguém editou o
     * texto de uma redação em uso — e contratos assinados sob ela passariam a
     * ser impressos com outras palavras. O caminho certo é criar a redação
     * seguinte, não consertar esta.
     */
    public function test_as_redacoes_ja_em_uso_estao_lacradas(): void
    {
        foreach (self::SEALS as $version => $files) {
            foreach ($files as $name => $expected) {
                $path = $this->versionPath($version) . '/' . $name;

                $this->assertFileExists($path, "A redação {$version} perdeu o arquivo {$name}.");

                $this->assertSame(
                    $expected,
                    $this->seal($path),
                    "O texto de {$name} da redação {$version} mudou. Contratos assinados sob ela seriam "
                    . 'impressos com o texto novo. Para revisar uma cláusula, crie a redação seguinte '
                    . '(copie a pasta para vN+1, some a entrada em CONTRACT_VERSIONS e suba '
                    . 'CONTRACT_VERSION_CURRENT).'
                );
            }
        }
    }

    /** Toda redação declarada tem os quatro corpos de texto que o documento monta. */
    public function test_toda_redacao_declarada_tem_os_arquivos_do_documento(): void
    {
        foreach (array_keys(FreelancerService::CONTRACT_VERSIONS) as $version) {
            foreach (['original-clauses', 'amendment-clauses', 'commission-clauses', 'pix-clause'] as $partial) {
                $this->assertFileExists(
                    $this->versionPath($version) . '/' . $partial . '.blade.php',
                    "A redação {$version} não tem o parcial {$partial}."
                );
            }
        }
    }

    public function test_a_redacao_vigente_esta_declarada(): void
    {
        $this->assertArrayHasKey(
            FreelancerService::CONTRACT_VERSION_CURRENT,
            FreelancerService::CONTRACT_VERSIONS,
            'A redação vigente precisa estar em CONTRACT_VERSIONS, que é o histórico que a varredura lê.'
        );
    }

    /* ---------------------------------------------------------------------
     | Quando congela
     |---------------------------------------------------------------------*/

    public function test_contrato_sem_assinatura_acompanha_a_redacao_vigente(): void
    {
        $service = $this->service();

        $this->assertFalse($service->contractIsFrozen());
        $this->assertSame(FreelancerService::CONTRACT_VERSION_CURRENT, $service->contractVersion());
        $this->assertSame(
            'freelancer.services.partials.contract.v' . FreelancerService::CONTRACT_VERSION_CURRENT,
            $service->contractViewNamespace()
        );
    }

    public function test_contrato_assinado_mantem_a_redacao_que_firmou(): void
    {
        // Simula um contrato firmado sob a redação 1 num mundo em que a vigente
        // já é outra: é o caso que a coluna existe para atender.
        $service = $this->service(['contract_version' => 1]);

        $this->assertTrue($service->contractIsFrozen());
        $this->assertSame(1, $service->contractVersion());
        $this->assertSame('freelancer.services.partials.contract.v1', $service->contractViewNamespace());
    }

    public function test_congela_redacao_e_qualificacao_na_primeira_assinatura(): void
    {
        $attributes = $this->service()->contractFreezeAttributes();

        $this->assertSame(FreelancerService::CONTRACT_VERSION_CURRENT, $attributes['contract_version']);
        $this->assertSame('Maria da Silva', $attributes['signed_snapshot']['freelancer']['name']);
        $this->assertSame('12.345.678', $attributes['signed_snapshot']['freelancer']['rg']);
        $this->assertSame('Garçom', $attributes['signed_snapshot']['function']['name']);
    }

    /**
     * As duas assinaturas são independentes e a segunda vem depois — se ela
     * reescrevesse o congelamento, o documento assinado pelo freelancer sob uma
     * redação seria impresso com outra ao passar pelo coordenador.
     */
    public function test_a_segunda_assinatura_nao_reescreve_o_congelamento(): void
    {
        $service = $this->service([
            'contract_version' => 1,
            'signed_snapshot' => ['freelancer' => ['name' => 'Maria da Silva'], 'function' => ['name' => 'Garçom']],
        ]);

        $this->assertSame([], $service->contractFreezeAttributes());
    }

    /* ---------------------------------------------------------------------
     | O que o documento cita
     |---------------------------------------------------------------------*/

    public function test_documento_assinado_cita_a_qualificacao_congelada(): void
    {
        // O cadastro mudou depois: nome de casada, endereço novo, função
        // renomeada. O documento continua citando o que foi assinado.
        $service = $this->service([
            'signed_snapshot' => [
                'freelancer' => [
                    'name' => 'Maria da Silva',
                    'cpf' => '12345678901',
                    'rg' => '12.345.678',
                    'nacionality' => 'brasileira',
                    'civil_status' => 'solteira',
                    'address' => 'Rua A, 100 — Volta Redonda/RJ',
                ],
                'function' => ['name' => 'Garçom'],
            ],
        ], new Freelancer([
            'name' => 'Maria da Silva Souza',
            'cpf' => '12345678901',
            'rg' => '12.345.678',
            'nacionality' => 'brasileira',
            'civil_status' => 'casada',
            'address' => 'Rua B, 200 — Barra Mansa/RJ',
        ]), 'Garçom (Salão)');

        $party = $service->contractParty();

        $this->assertSame('Maria da Silva', $party['name']);
        $this->assertSame('solteira', $party['civil_status']);
        $this->assertSame('Rua A, 100 — Volta Redonda/RJ', $party['address']);
        $this->assertSame('Garçom', $service->contractFunctionName());

        $this->assertTrue($service->contractPartyIsFrozen());
        $this->assertTrue($service->contractPartyDivergesFromFreelancer());
    }

    /**
     * Contrato anterior a esta cópia: sem snapshot, o documento cai no cadastro
     * do freelancer — que é exatamente o que ele citava antes de a coluna
     * existir. Mesma decisão tomada para a `pix_key` legada.
     */
    public function test_contrato_legado_sem_snapshot_cai_no_cadastro(): void
    {
        $service = $this->service(['contract_version' => 1]);

        $this->assertFalse($service->contractPartyIsFrozen());
        $this->assertFalse($service->contractPartyDivergesFromFreelancer());
        $this->assertSame('Maria da Silva', $service->contractParty()['name']);
        $this->assertSame('Garçom', $service->contractFunctionName());
    }

    public function test_qualificacao_igual_ao_cadastro_nao_e_divergencia(): void
    {
        $service = $this->service();
        $service->signed_snapshot = $service->buildContractSnapshot();

        $this->assertTrue($service->contractPartyIsFrozen());
        $this->assertFalse($service->contractPartyDivergesFromFreelancer());
    }

    /* ---------------------------------------------------------------------
     | O documento montado
     |---------------------------------------------------------------------*/

    /**
     * A garantia inteira, ponta a ponta: o documento renderizado de um contrato
     * assinado traz a qualificação do dia da assinatura, e não a do cadastro de
     * hoje. É o mesmo Blade que o painel imprime e que o tablet exibe.
     */
    public function test_o_documento_renderizado_usa_a_qualificacao_congelada(): void
    {
        $service = $this->service([
            'price' => 120.00,
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-22',
            'freelancer_signed_at' => '2026-07-22 18:00:00',
            'contract_version' => 1,
            'signed_snapshot' => [
                'freelancer' => [
                    'name' => 'Maria da Silva',
                    'cpf' => '12345678901',
                    'rg' => '12.345.678',
                    'nacionality' => 'brasileira',
                    'civil_status' => 'solteira',
                    'address' => 'Rua A, 100 — Volta Redonda/RJ',
                ],
                'function' => ['name' => 'Garçom'],
            ],
        ], new Freelancer([
            'name' => 'Maria da Silva Souza',
            'cpf' => '12345678901',
            'rg' => '99.999.999',
            'nacionality' => 'brasileira',
            'civil_status' => 'casada',
            'address' => 'Rua B, 200 — Barra Mansa/RJ',
        ]), 'Garçom (Salão)');

        $html = view('freelancer.services.partials.contract-document', ['service' => $service])->render();

        $this->assertStringContainsString('Maria da Silva</b>', $html);
        $this->assertStringContainsString('solteira', $html);
        $this->assertStringContainsString('12.345.678', $html);
        $this->assertStringContainsString('Garçom</b>', $html);

        // O cadastro de hoje não aparece em lugar nenhum do instrumento.
        $this->assertStringNotContainsString('Maria da Silva Souza', $html);
        $this->assertStringNotContainsString('casada', $html);
        $this->assertStringNotContainsString('99.999.999', $html);
        $this->assertStringNotContainsString('Barra Mansa', $html);
    }

    /**
     * O tablet recebe este mesmo HTML, com o campo da assinatura no lugar de
     * quem vai assinar — e é por isso que o texto não precisa mais existir em
     * JavaScript.
     */
    public function test_o_layout_do_tablet_traz_o_campo_da_assinatura(): void
    {
        // Sem assinatura: segue a redação vigente, em que o CONTRATANTE é
        // assinado pela diretoria na aprovação do lote.
        $service = $this->service([
            'price' => 120.00,
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-22',
        ]);

        $freelancer = view('freelancer.services.partials.contract-document', [
            'service' => $service,
            'layout' => 'tablet',
            'signing' => 'freelancer',
        ])->render();

        // O campo do canvas existe uma vez só, e no bloco do FREELANCER: o do
        // CONTRATANTE fica esperando a diretoria.
        $this->assertSame(1, substr_count($freelancer, 'id="sigCanvas"'));
        $this->assertStringContainsString('assinatura da diretoria (pendente)', $freelancer);

        // Redação 1: o CONTRATANTE continua sendo o coordenador, no tablet.
        $redacao1 = $this->service([
            'price' => 120.00,
            'start_date' => '2026-07-22',
            'end_date' => '2026-07-22',
            'contract_version' => 1,
        ]);

        $this->assertStringContainsString('assinatura do coordenador (pendente)', view('freelancer.services.partials.contract-document', [
            'service' => $redacao1,
            'layout' => 'tablet',
            'signing' => 'freelancer',
        ])->render());

        $service = $redacao1;

        $coordenador = view('freelancer.services.partials.contract-document', [
            'service' => $service,
            'layout' => 'tablet',
            'signing' => 'coordinator',
            'operatorName' => 'João Coordenador',
        ])->render();

        $this->assertSame(1, substr_count($coordenador, 'id="sigCanvas"'));
        $this->assertStringContainsString('João Coordenador', $coordenador);

        // Na impressão não há campo de assinatura nenhum: o painel não assina.
        $impressao = view('freelancer.services.partials.contract-document', ['service' => $service])->render();

        $this->assertStringNotContainsString('sigCanvas', $impressao);
    }

    /* ---------------------------------------------------------------------
     | Quem assina pelo CONTRATANTE
     |---------------------------------------------------------------------*/

    /**
     * O fluxo vem da redação congelada, e não da data de hoje: o que o
     * freelancer assinou sob a redação 1 termina com o traço do coordenador,
     * mesmo depois de a 2 entrar em vigor.
     */
    public function test_quem_assina_pelo_contratante_vem_da_redacao(): void
    {
        $this->assertFalse($this->service(['contract_version' => 1])->usesDirectorSignature());
        $this->assertTrue($this->service(['contract_version' => 2])->usesDirectorSignature());

        // Sem assinatura, segue a vigente — é nela que vai ser firmado.
        $this->assertSame(
            FreelancerService::CONTRACT_VERSIONS[FreelancerService::CONTRACT_VERSION_CURRENT]['contractor_signature']
                === FreelancerService::CONTRACTOR_SIGNS_DIRECTOR,
            $this->service()->usesDirectorSignature()
        );

        $this->assertSame([1], FreelancerService::versionsSignedBy(FreelancerService::CONTRACTOR_SIGNS_COORDINATOR));
        $this->assertContains(2, FreelancerService::versionsSignedBy(FreelancerService::CONTRACTOR_SIGNS_DIRECTOR));
    }

    /** Toda redação declarada diz quem assina pelo CONTRATANTE. */
    public function test_toda_redacao_declara_quem_assina_pelo_contratante(): void
    {
        foreach (FreelancerService::CONTRACT_VERSIONS as $version => $info) {
            $this->assertContains(
                $info['contractor_signature'] ?? null,
                [FreelancerService::CONTRACTOR_SIGNS_COORDINATOR, FreelancerService::CONTRACTOR_SIGNS_DIRECTOR],
                "A redação {$version} não diz quem assina pelo CONTRATANTE."
            );
        }
    }

    /**
     * Redação 2 validada pela coordenação e ainda sem a aprovação da diretoria:
     * a validação NÃO vai ao documento — nem traço, nem a marca "assinado
     * eletronicamente" dos contratos antigos. O CONTRATANTE espera o diretor.
     */
    public function test_redacao_2_validada_nao_leva_a_coordenacao_ao_documento(): void
    {
        $service = $this->service([
            'price' => 120.00,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'freelancer_signed_at' => '2026-09-01 17:50:00',
            'contract_version' => 2,
            'coordinator_signed_at' => '2026-09-02 09:00:00',
            // Se o documento tentasse citar o coordenador, iria ao banco atrás
            // dele — e o teste quebraria, que é o que se quer.
            'coordinator_signed_by' => 7,
        ]);

        $html = view('freelancer.services.partials.contract-document', ['service' => $service])->render();

        $this->assertStringContainsString('assinatura da diretoria (pendente)', $html);
        $this->assertStringNotContainsString('Assinado eletronicamente', $html);
        $this->assertStringNotContainsString('Assinado digitalmente', $html);
        $this->assertStringNotContainsString('sigCanvas', $html);
    }

    /** Aprovado o lote, o CONTRATANTE sai com a imagem e o nome do diretor. */
    public function test_redacao_2_aprovada_sai_assinada_pelo_diretor(): void
    {
        Storage::fake(FreelancerDirector::DISK);
        Storage::disk(FreelancerDirector::DISK)->put('freelancer/director-signatures/ass.png', 'PNG-FALSO');

        $diretor = new FreelancerDirector([
            'name' => 'Carlos Diretor',
            'email' => 'diretor@exemplo.test',
            'signature_path' => 'freelancer/director-signatures/ass.png',
        ]);

        $service = $this->service([
            'price' => 120.00,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'freelancer_signed_at' => '2026-09-01 17:50:00',
            'contract_version' => 2,
            'coordinator_signed_at' => '2026-09-02 09:00:00',
        ]);
        // Fora do $fillable de propósito: só a aprovação da diretoria grava.
        $service->forceFill([
            'freelancer_director_id' => 3,
            'director_signed_at' => '2026-09-05 10:30:00',
        ]);
        $service->setRelation('director', $diretor);

        $html = view('freelancer.services.partials.contract-document', ['service' => $service])->render();

        $this->assertStringContainsString('Assinado digitalmente por Carlos Diretor em 05/09/2026 às 10:30', $html);
        $this->assertStringContainsString('data:image/png;base64,' . base64_encode('PNG-FALSO'), $html);
        $this->assertStringNotContainsString('(pendente)', $html);
        $this->assertStringNotContainsString('Assinado eletronicamente', $html);
    }

    /** No tablet, o campo do CONTRATANTE da redação 2 nunca recebe canvas. */
    public function test_redacao_2_nao_oferece_canvas_ao_contratante(): void
    {
        $service = $this->service([
            'price' => 120.00,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-01',
            'freelancer_signed_at' => '2026-09-01 17:50:00',
            'contract_version' => 2,
        ]);

        $html = view('freelancer.services.partials.contract-document', [
            'service' => $service,
            'layout' => 'tablet',
            'signing' => 'coordinator',
            'operatorName' => 'João Coordenador',
        ])->render();

        $this->assertStringNotContainsString('sigCanvas', $html);
        $this->assertStringNotContainsString('João Coordenador', $html);
    }

    /* ---------------------------------------------------------------------
     | Varredura
     |---------------------------------------------------------------------*/

    public function test_o_filtro_da_varredura_oferece_todas_as_redacoes_e_os_nao_congelados(): void
    {
        $filters = FreelancerService::contractVersionFilters();

        foreach (array_keys(FreelancerService::CONTRACT_VERSIONS) as $version) {
            $this->assertArrayHasKey((string) $version, $filters);
        }

        $this->assertArrayHasKey('unfrozen', $filters);
    }
}
