<?php

namespace Database\Seeders;

use App\Models\SignatureTemplate;
use App\Services\Signature\SignatureAttachmentService;
use App\Services\Signature\SignatureFieldTypes as T;
use Illuminate\Database\Seeder;

/**
 * Modelos de EXEMPLO do módulo de assinatura, para teste manual: cada um
 * exercita uma combinação de recursos — campos do atendente, perguntas
 * obrigatórias e opcionais a quem assina, data automática, anexos, partes,
 * visto, conferência por CPF ou por código no e-mail, e um próprio para o
 * gov.br.
 *
 *   php artisan db:seed --class=SignatureExampleTemplatesSeeder
 *
 * - Todos começam com "[Exemplo]" no nome, para não se confundirem com os
 *   modelos de verdade — e para serem desativados de uma vez depois.
 * - Idempotente: modelo que já existe com o mesmo nome é deixado como está
 *   (rodar de novo não cria versões novas).
 * - NÃO roda em produção, e não é chamado pelo DatabaseSeeder nem pelos
 *   scripts de deploy.
 */
class SignatureExampleTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Modelos de exemplo não são criados em produção.');

            return;
        }

        foreach ($this->templates() as $modelo) {
            $existe = SignatureTemplate::where('name', $modelo['name'])->where('single_use', false)->exists();

            if ($existe) {
                $this->command?->line('  já existe: ' . $modelo['name']);

                continue;
            }

            SignatureTemplate::create($modelo + ['created_by' => null]);
            $this->command?->info('  criado: ' . $modelo['name']);
        }
    }

    /**
     * Um campo na forma gravada (ver TemplateController::variable).
     *
     * `$paraOTablet` só diz que o campo foi pensado para perguntar a quem
     * assina e grava a pergunta dele: QUEM responde é marcado pelo atendente
     * em cada documento ("Perguntar ao signatário"), não no modelo.
     *
     * @param  array<int, string>  $options
     * @return array<string, mixed>
     */
    private function campo(
        string $key,
        string $label,
        string $type = T::TEXT,
        bool $required = false,
        bool $paraOTablet = false,
        ?string $question = null,
        array $options = [],
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'required' => $required && !T::isAutomatic($type),
            'question' => $paraOTablet ? $question : null,
            'options' => $options,
        ];
    }

    /**
     * @param  array<int, array{0: string, 1: bool}>  $itens  [rótulo, obrigatório]
     * @return array<int, array{key: string, label: string, required: bool}>
     */
    private function anexos(array $itens): array
    {
        return SignatureAttachmentService::normalize(
            array_map(fn(array $i) => ['label' => $i[0], 'required' => $i[1]], $itens),
            'mod',
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function templates(): array
    {
        return [
            // 1. O mais simples: texto fixo, nenhum campo. Testa o fluxo básico do tablet.
            [
                'name' => '[Exemplo] 1. Termo simples (texto fixo)',
                'description' => 'Sem campos, sem perguntas, sem anexos. Quatro dígitos do CPF e foto.',
                'body_html' => '<p>Declaro que li e estou ciente das regras de uso das dependências do Clube dos '
                    . 'Funcionários da CSN, e que me responsabilizo pelo cumprimento delas.</p>'
                    . '<p>Este é um modelo de exemplo, para teste.</p>[[assinatura]]',
                'variables' => [],
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'requires_photo' => true,
            ],

            // 2. Campos obrigatórios e opcionais, alguns com pergunta pronta para o tablet + data automática.
            [
                'name' => '[Exemplo] 2. Reserva de espaço (campos e perguntas)',
                'description' => 'Campos obrigatórios e opcionais. No documento, marque "Perguntar ao signatário" em Espaço, Telefone e Bebida (obrigatórias) e em E-mail, Itens adicionais e Comentários (opcionais) para testá-las no tablet. Data da assinatura automática.',
                'body_html' => '<h2>Reserva de espaço</h2>'
                    . '<p>Eu, [[nome_completo]], CPF [[cpf]], reservo o espaço <b>[[espaco]]</b> para o dia [[data_evento]], '
                    . 'das [[hora_inicio]], pelo valor de [[valor]].</p>'
                    . '<p>Número de convidados: [[convidados]]. Observações do atendimento: [[observacoes]].</p>'
                    . '<p>Telefone para contato: [[telefone]]. E-mail (opcional): [[email_contato]].</p>'
                    . '<p>Itens adicionais: [[adicionais]]. Vai servir bebida alcoólica? [[bebida]]</p>'
                    . '<p>Comentários de quem assina: [[comentarios]]</p>'
                    . '<p>Volta Redonda, [[data_assinatura]].</p>[[assinatura]]',
                'variables' => [
                    // Do atendente
                    $this->campo('nome_completo', 'Nome completo', T::TEXT, true),
                    $this->campo('cpf', 'CPF', T::CPF, true),
                    $this->campo('data_evento', 'Data do evento', T::DATE, true),
                    $this->campo('hora_inicio', 'Hora de início', T::TIME, true),
                    $this->campo('valor', 'Valor', T::MONEY, true),
                    $this->campo('convidados', 'Número de convidados', T::NUMBER),
                    $this->campo('observacoes', 'Observações', T::TEXTAREA),
                    // Perguntas no tablet — obrigatórias
                    $this->campo('espaco', 'Espaço', T::RADIO, true, true, 'Qual espaço você vai usar?', ['Salão de festas', 'Churrasqueira', 'Quiosque da piscina']),
                    $this->campo('telefone', 'Telefone', T::PHONE, true, true, 'Qual é o seu telefone para contato?'),
                    $this->campo('bebida', 'Bebida alcoólica', T::YES_NO, true, true, 'Vai servir bebida alcoólica?'),
                    // Perguntas no tablet — opcionais
                    $this->campo('email_contato', 'E-mail de contato', T::EMAIL, false, true, 'Quer informar um e-mail? (opcional)'),
                    $this->campo('adicionais', 'Itens adicionais', T::CHECKBOX, false, true, 'Vai precisar de algum item adicional?', ['Mesas extras', 'Cadeiras extras', 'Som ambiente']),
                    $this->campo('comentarios', 'Comentários', T::TEXTAREA, false, true, 'Algum comentário? (opcional)'),
                    // Automático
                    $this->campo('data_assinatura', 'Data da assinatura', T::DATE_SIGNING_LONG),
                ],
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'requires_photo' => true,
            ],

            // 3. Anexos obrigatórios e opcionais.
            [
                'name' => '[Exemplo] 3. Cadastro de dependente (anexos)',
                'description' => 'Pede identidade e certidão (obrigatórias) e comprovante de residência (opcional). CPF completo.',
                'body_html' => '<h2>Inclusão de dependente</h2>'
                    . '<p>Solicito a inclusão de [[nome_dependente]], nascido(a) em [[nascimento]], como meu dependente, '
                    . 'com grau de parentesco [[parentesco]].</p>'
                    . '<p>Declaro que os documentos anexados são verdadeiros.</p>[[assinatura]]',
                'variables' => [
                    $this->campo('nome_dependente', 'Nome do dependente', T::TEXT, true),
                    $this->campo('nascimento', 'Data de nascimento', T::DATE, true),
                    $this->campo('parentesco', 'Parentesco', T::RADIO, true, false, null, ['Cônjuge', 'Filho(a)', 'Enteado(a)']),
                ],
                'attachments' => $this->anexos([
                    ['Documento de identidade do dependente', true],
                    ['Certidão de nascimento ou casamento', true],
                    ['Comprovante de residência', false],
                ]),
                'identity_check' => SignatureTemplate::IDENTITY_FULL,
                'requires_photo' => true,
            ],

            // 4. Contrato com partes lado a lado e visto em todas as páginas.
            [
                'name' => '[Exemplo] 4. Contrato com partes e visto',
                'description' => 'Duas partes (Contratante e Contratado), visto em todas as páginas, CPF completo.',
                'body_html' => '<h2>Contrato de prestação de serviço</h2>'
                    . '<p><b>CONTRATANTE:</b> Clube dos Funcionários da CSN. <b>CONTRATADO:</b> [[nome_contratado]], CPF [[cpf_contratado]].</p>'
                    . '<ol><li>O contratado prestará o serviço de [[servico]] no dia [[data_servico]].</li>'
                    . '<li>O valor total é de [[valor_total]], pago em até 10 dias após o serviço.</li>'
                    . '<li>Este é um modelo de exemplo, para teste do visto em todas as páginas e da assinatura lado a lado.</li></ol>'
                    . str_repeat('<p>Cláusula de texto longo para o documento passar de uma página e o visto aparecer em mais de uma folha. '
                        . 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>', 12)
                    . '[[assinatura:contratante]][[assinatura:contratado]]',
                'variables' => [
                    $this->campo('nome_contratado', 'Nome do contratado', T::TEXT, true),
                    $this->campo('cpf_contratado', 'CPF do contratado', T::CPF, true),
                    $this->campo('servico', 'Serviço', T::TEXT, true),
                    $this->campo('data_servico', 'Data do serviço', T::DATE_LONG, true),
                    $this->campo('valor_total', 'Valor total', T::MONEY, true),
                ],
                'parties' => [
                    ['key' => 'contratante', 'label' => 'CONTRATANTE'],
                    ['key' => 'contratado', 'label' => 'CONTRATADO'],
                ],
                'identity_check' => SignatureTemplate::IDENTITY_FULL,
                'requires_photo' => false,
                'requires_initials' => true,
            ],

            // 5. Conferência de identidade por código no e-mail.
            [
                'name' => '[Exemplo] 5. Autorização com código por e-mail',
                'description' => 'Identidade conferida por código de 6 números enviado ao e-mail do signatário (o signatário precisa ter e-mail). Sem foto.',
                'body_html' => '<p>Autorizo o Clube dos Funcionários da CSN a [[autorizacao]] até [[validade]].</p>'
                    . '<p>Este é um modelo de exemplo: no tablet, a identidade é confirmada por um código enviado ao e-mail.</p>[[assinatura]]',
                'variables' => [
                    $this->campo('autorizacao', 'O que é autorizado', T::TEXTAREA, true),
                    $this->campo('validade', 'Válida até', T::DATE_LONG, false),
                ],
                'attachments' => $this->anexos([['Procuração (se houver)', false]]),
                'identity_check' => SignatureTemplate::IDENTITY_EMAIL,
                'requires_photo' => false,
            ],

            // 6. Próprio para o gov.br: sem visto (o gov.br não tem visto), com data automática e anexo.
            [
                'name' => '[Exemplo] 6. Termo para o gov.br',
                'description' => 'Sem visto, para poder ser assinado pelo gov.br: preencha todos os campos no documento, sem "Perguntar ao signatário". Data automática e um anexo obrigatório.',
                'body_html' => '<h2>Termo de adesão</h2>'
                    . '<p>Eu, [[nome]], CPF [[cpf]], declaro aderir ao [[programa]] do Clube dos Funcionários da CSN.</p>'
                    . '<p>Assinado em [[data_assinatura]].</p>[[assinatura]]',
                'variables' => [
                    $this->campo('nome', 'Nome', T::TEXT, true),
                    $this->campo('cpf', 'CPF', T::CPF, true),
                    $this->campo('programa', 'Programa', T::TEXT, true),
                    $this->campo('data_assinatura', 'Data da assinatura', T::DATE_SIGNING),
                ],
                'attachments' => $this->anexos([['Documento de identidade', true]]),
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'requires_photo' => true,
            ],

            // 7. Só campos opcionais, sem foto: marcados para o tablet, testa o formulário sem nada obrigatório.
            [
                'name' => '[Exemplo] 7. Pesquisa de satisfação (só opcionais)',
                'description' => 'Todos os campos são opcionais; marque "Perguntar ao signatário" em todos para testar o formulário do tablet sem nada obrigatório. Sem foto. Quatro dígitos do CPF.',
                'body_html' => '<p>Respostas da pesquisa de satisfação:</p>'
                    . '<ul><li>Nota do atendimento: [[nota]]</li><li>Indicaria o clube? [[indicaria]]</li>'
                    . '<li>Sugestões: [[sugestoes]]</li></ul>[[assinatura]]',
                'variables' => [
                    $this->campo('nota', 'Nota', T::RADIO, false, true, 'Que nota você dá ao atendimento?', ['Ótimo', 'Bom', 'Regular', 'Ruim']),
                    $this->campo('indicaria', 'Indicaria', T::YES_NO, false, true, 'Você indicaria o clube a um amigo?'),
                    $this->campo('sugestoes', 'Sugestões', T::TEXTAREA, false, true, 'Tem alguma sugestão?'),
                ],
                'identity_check' => SignatureTemplate::IDENTITY_PARTIAL,
                'requires_photo' => false,
            ],
        ];
    }
}
