<?php

namespace Database\Seeders;

use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureDocumentRenderer;
use App\Support\HtmlSanitizer;
use Illuminate\Database\Seeder;

/**
 * Três modelos de documento para começar a usar o módulo de assinatura.
 *
 * NÃO é demo: são os três documentos que motivaram o módulo — o termo de
 * responsabilidade, a ficha cadastral e o contrato de locação de espaço. O
 * texto é um ponto de partida para o jurídico revisar pela tela, e revisar
 * cria a versão seguinte sem tocar nestes.
 *
 * Idempotente: roda de novo sem duplicar. Um modelo que já existe é deixado
 * como está — inclusive se já foi revisado, porque sobrescrever apagaria o
 * trabalho de quem revisou.
 */
class SignatureTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->modelos() as $modelo) {
            if (SignatureTemplate::where('name', $modelo['name'])->exists()) {
                $this->command?->line('  Já existe, mantido: ' . $modelo['name']);

                continue;
            }

            SignatureTemplate::create([
                'name' => $modelo['name'],
                'description' => $modelo['description'],
                // Saneado na gravação, como faz o controller — o corpo é
                // renderizado sem escapar no PDF e no tablet.
                'body_html' => HtmlSanitizer::clean(
                    $modelo['body_html'],
                    SignatureDocumentRenderer::EXTRA_HTML_TAGS,
                ),
                'variables' => $modelo['variables'],
                'requires_photo' => $modelo['requires_photo'],
                'identity_check' => $modelo['identity_check'],
                'retention_months' => $modelo['retention_months'],
            ]);

            $this->command?->info('  Modelo criado: ' . $modelo['name']);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function modelos(): array
    {
        return [
            [
                'name' => 'Termo de Responsabilidade — Uso de Espaço Esportivo',
                'description' => 'Assinado por quem usa piscina, academia ou quadra. Exige foto.',
                'requires_photo' => true,
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'retention_months' => 60,
                'variables' => [
                    ['key' => 'espaco', 'label' => 'Espaço utilizado', 'required' => true],
                    ['key' => 'validade', 'label' => 'Validade do termo', 'required' => false],
                ],
                'body_html' => <<<'HTML'
<p>Declaro, para os devidos fins, que estou ciente e de acordo com as condições de uso do espaço
<strong>[[espaco]]</strong> do Clube dos Funcionários da CSN, comprometendo-me a observar as
regras abaixo.</p>

<ol>
<li>Utilizarei o espaço nos horários de funcionamento divulgados pelo clube, respeitando a
lotação máxima e a orientação dos funcionários responsáveis.</li>
<li>Declaro estar em condições de saúde compatíveis com a atividade que vou praticar, e assumo a
responsabilidade por eventual agravamento decorrente de condição preexistente não informada.</li>
<li>Responsabilizo-me por danos que eu causar às instalações e aos equipamentos, bem como pelos
meus pertences, que não ficam sob a guarda do clube.</li>
<li>Estou ciente de que o descumprimento das regras pode acarretar a suspensão do uso do espaço,
na forma do estatuto.</li>
</ol>

<p>Este termo tem validade [[validade]] e é assinado eletronicamente, nos termos da Lei
14.063/2020.</p>

[[assinatura]]
HTML,
            ],

            [
                'name' => 'Ficha Cadastral — Atualização de Dados',
                'description' => 'Confirmação de dados cadastrais pelo associado. Sem foto.',
                'requires_photo' => false,
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'retention_months' => 60,
                'variables' => [
                    ['key' => 'endereco', 'label' => 'Endereço', 'required' => true],
                    ['key' => 'telefone', 'label' => 'Telefone', 'required' => true],
                    ['key' => 'email', 'label' => 'E-mail', 'required' => false],
                ],
                'body_html' => <<<'HTML'
<p>Confirmo que os dados abaixo estão corretos e autorizo o Clube dos Funcionários da CSN a
utilizá-los para as comunicações relativas ao meu vínculo associativo.</p>

<table>
<tr><th>Endereço</th><td>[[endereco]]</td></tr>
<tr><th>Telefone</th><td>[[telefone]]</td></tr>
<tr><th>E-mail</th><td>[[email]]</td></tr>
</table>

<p>Comprometo-me a informar ao clube qualquer alteração nesses dados. Estou ciente de que eles
são tratados conforme a Lei 13.709/2018 (LGPD), exclusivamente para as finalidades do vínculo
associativo.</p>

[[assinatura]]
HTML,
            ],

            [
                'name' => 'Contrato de Locação de Espaço para Evento',
                'description' => 'Locação de salão ou área para evento particular. Exige foto e CPF completo.',
                'requires_photo' => true,
                'identity_check' => SignatureTemplate::IDENTITY_FULL,
                'retention_months' => 120,
                'variables' => [
                    ['key' => 'espaco', 'label' => 'Espaço locado', 'required' => true],
                    ['key' => 'data_evento', 'label' => 'Data do evento', 'required' => true],
                    ['key' => 'horario', 'label' => 'Horário', 'required' => true],
                    ['key' => 'valor', 'label' => 'Valor total (R$)', 'required' => true],
                    ['key' => 'convidados', 'label' => 'Número de convidados', 'required' => true],
                ],
                'body_html' => <<<'HTML'
<p>O <strong>CLUBE DOS FUNCIONÁRIOS DA CSN</strong>, doravante LOCADOR, e o associado
identificado ao final deste instrumento, doravante LOCATÁRIO, celebram o presente contrato de
locação de espaço para evento, nas condições seguintes.</p>

<h3>1. Objeto</h3>
<table>
<tr><th>Espaço</th><td>[[espaco]]</td></tr>
<tr><th>Data</th><td>[[data_evento]]</td></tr>
<tr><th>Horário</th><td>[[horario]]</td></tr>
<tr><th>Convidados</th><td>[[convidados]]</td></tr>
<tr><th>Valor total</th><td>R$ [[valor]]</td></tr>
</table>

<h3>2. Obrigações do locatário</h3>
<ol>
<li>Devolver o espaço nas condições em que o recebeu, respondendo por danos causados por si ou
por seus convidados.</li>
<li>Respeitar o número de convidados contratado e os limites de horário e de som estabelecidos
pelo clube.</li>
<li>Responsabilizar-se pela conduta dos convidados dentro das dependências do clube.</li>
</ol>

<h3>3. Cancelamento</h3>
<p>O cancelamento pelo locatário observará as condições vigentes de reembolso divulgadas pelo
clube na data da contratação.</p>

<h3>4. Foro</h3>
<p>Fica eleito o foro da comarca de Volta Redonda/RJ para dirimir questões oriundas deste
contrato.</p>

[[assinatura]]
HTML,
            ],
        ];
    }
}
