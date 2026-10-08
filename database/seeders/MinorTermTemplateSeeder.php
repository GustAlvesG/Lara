<?php

namespace Database\Seeders;

use App\Models\SignatureTemplate;
use App\Services\Signature\MinorTerms\MinorTermFields as F;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Services\Signature\SignatureFieldTypes;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;

/**
 * O modelo do Termo de Menores do OKTOBERPET 2026 — o termo em papel que o
 * clube usava, com as variáveis que o autoatendimento preenche (ver
 * MinorTermFields). Ponto de partida: para o próximo evento, revise o texto em
 * Assinaturas → Modelos (cria a versão seguinte) ou crie outro modelo, e
 * cadastre o termo em Assinaturas → Termo de Menores.
 *
 * Idempotente e FORA do deploy, como SignatureTemplateSeeder:
 *   php artisan db:seed --class=MinorTermTemplateSeeder
 * Um modelo com o mesmo nome é mantido como está.
 */
class MinorTermTemplateSeeder extends Seeder
{
    public const NAME = 'Termo de Menores — OKTOBERPET 2026';

    public function run(): void
    {
        if (SignatureTemplate::where('name', self::NAME)->exists()) {
            $this->command?->line('  Já existe, mantido: ' . self::NAME);

            return;
        }

        SignatureTemplate::create([
            'name' => self::NAME,
            'description' => 'Autorização de entrada de menor no evento, assinada pelo responsável no tablet de autoatendimento.',
            'body_html' => HtmlSanitizer::clean($this->body(), SignatureDocumentRenderer::EXTRA_HTML_TAGS),
            'variables' => $this->variables(),
            'requires_photo' => true,
            'requires_initials' => false,
            'identity_check' => SignatureTemplate::IDENTITY_FULL,
            'retention_months' => 60,
        ]);

        $this->command?->info('  Modelo criado: ' . self::NAME);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function variables(): array
    {
        $texto = fn(string $key, string $label) => [
            'key' => $key, 'label' => $label, 'required' => false, 'type' => SignatureFieldTypes::TEXT,
        ];

        return [
            $texto(F::RESPONSIBLE_NAME, 'Nome do responsável'),
            $texto(F::RESPONSIBLE_EMAIL, 'E-mail do responsável'),
            $texto(F::RESPONSIBLE_CPF, 'CPF do responsável'),
            $texto(F::RESPONSIBLE_RG, 'RG do responsável'),
            $texto(F::RESPONSIBLE_ADDRESS, 'Endereço do responsável'),
            $texto(F::MINOR_NAME, 'Nome do menor'),
            $texto(F::MINOR_AGE, 'Idade do menor'),
            $texto(F::MINOR_CPF, 'CPF do menor'),
            $texto(F::MINOR_RG, 'RG do menor'),
            ['key' => 'data', 'label' => 'Data', 'required' => false, 'type' => SignatureFieldTypes::DATE_SIGNING_LONG],
        ];
    }

    private function body(): string
    {
        return <<<'HTML'
<h1>Autorização para entrada de menores de idade no evento OKTOBERPET 2026</h1>

<p>Eu, <strong>[[nome_responsavel]]</strong> (nome do pai, da mãe, ou responsável legal),
e-mail [[e_mail_responsavel]], portador (a) do CPF nº [[cpf_responsavel]], e RG nº [[rg_responsavel]], residente na
[[endereco_responsavel]] me responsabilizo por entrar e permanecer em companhia do menor <strong>[[nome_menor]]</strong>
(nome da criança ou adolescente), de [[idade_menor]] anos de idade, portador (a) do CPF nº [[cpf_menor]], e RG nº
[[rg_menor]] no evento “OKTOBERPET”, realizado nas dependências da Praça de Esportes Tabajaras – PET, em Volta
Redonda/RJ, que ocorrerá nos dias 10, 11 e 12 de julho de 2026.</p>

<p>Declaro estar ciente das disposições da Portaria nº 01/2026 da Vara da Infância,
Juventude e Idoso da Comarca de Volta Redonda/RJ, especialmente quanto às regras de entrada e permanência de crianças
e adolescentes em eventos realizados em ambientes fechados, me comprometendo, sob as penas da lei em permanecer com o
menor durante todo o evento.</p>

<p>Declaro, ainda, estar ciente de que o adolescente deverá portar documento oficial com
foto durante toda a permanência no evento, apresentando-o sempre que solicitado pela organização ou equipe de
segurança.</p>

<p>Estou ciente de que é expressamente proibido o fornecimento, consumo ou acesso de
menores de idade a bebidas alcoólicas, produtos fumígenos ou quaisquer substâncias ilícitas, nos termos da legislação
vigente.</p>

<p>Assumo integral responsabilidade pela conduta, segurança e bem-estar do adolescente
durante sua permanência no evento, bem como por eventuais danos materiais ou morais causados a terceiros ou ao
patrimônio do clube.</p>

<p>Autorizo, de forma gratuita, a captação e utilização da imagem do adolescente em
fotografias e vídeos institucionais do evento “OKTOBERPET”, para fins exclusivamente informativos e de divulgação
institucional do evento e do Clube dos Funcionários da CSN, sem finalidade comercial. Da mesma forma, autorizo que o
CFCSN a armazenar por tempo indeterminado e sem fins comerciais, cópias físicas e digitais dos documentos pessoais
apresentados das partes.</p>

<p>Volta Redonda, [[data]].</p>

[[assinatura]]
HTML;
    }
}
