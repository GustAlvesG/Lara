<?php

namespace App\Services\Signature\Contracts;

use LogicException;

/**
 * Modelos-base de contrato e cláusulas avulsas, tirados dos contratos reais
 * do clube (PDFs enviados pelo usuário em 04/11/2026): obras (CT 106/26 e
 * CT 145/26), serviço pontual (CT 188/26), diárias (CT 189/26), uniformes
 * (CT 194/26), locação de loja (CT 088/26 e CT 09-22 A1), telões de LED
 * (CT 131, 139 e 184/26), permuta (CT 148/26), escolinha (CT 109/26) e
 * aditivo (CT 145/26 A1 e CT 106/26 A2). Em 06/11/2026, atração musical
 * (contrato 283/26, DJ em evento do clube).
 *
 * O que muda de um contrato para outro virou variável; o que O Lara já monta
 * sozinho (cabeçalho, qualificação das partes, cidade e data, assinaturas)
 * saiu do texto. As remissões são `{ref:chave.N}` (item N da cláusula de
 * chave "chave", contando todos os itens) e viram `[[ref:uid]]`.
 *
 * Ajustes de redação em relação aos PDFs: numeração e remissões erradas,
 * artigos do Código Civil de 1916 na fiança (trocados pelos do Código de 2002),
 * IVA (não existe no Brasil), papéis invertidos nos telões (o clube vende o
 * espaço: CLUBE e ANUNCIANTE), teto das multas (art. 412 do Código Civil) e a
 * assinatura eletrônica no lugar das "duas vias".
 *
 * Gravados pelo comando `contratos:modelos-base` (ContractBaseModelsImport).
 */
class ContractBaseModels
{
    /** Quem aparece como autor do que o comando grava. */
    public const AUTHOR = 'O Lara (modelos-base)';

    /**
     * @return array<int, array{name: string, content: array<string, mixed>}>
     */
    public static function models(): array
    {
        return [
            self::obra(),
            self::reforma(),
            self::pontual(),
            self::diarias(),
            self::uniformes(),
            self::locacao(),
            self::teloes(),
            self::permuta(),
            self::escolinha(),
            self::aditivo(),
            self::atracaoMusical(),
        ];
    }

    /**
     * Cláusulas para a biblioteca: o que entra em alguns contratos e não em
     * todos. Variável usada aqui precisa ser criada no contrato que a recebe.
     *
     * @return array<int, array{title: string, category: string, items: array<int, array<string, mixed>>}>
     */
    public static function clauses(): array
    {
        $lista = [
            ['Carência para reforma', 'Locação', [
                'O [[contratante]] concede ao [[contratada]] carência de [[dias_de_carencia]] dias no pagamento do aluguel, para reforma e adequação do imóvel ao negócio. Em razão da carência, as benfeitorias realizadas, sejam necessárias, úteis ou voluptuárias, passam a integrar o imóvel, sem direito a retenção ou indenização.',
            ]],
            ['Aluguel escalonado', 'Locação', [
                'O aluguel mensal será de [[aluguel_ano_1]] nos 12 (doze) primeiros meses, de [[aluguel_ano_2]] do 13º (décimo terceiro) ao 24º (vigésimo quarto) mês e de [[aluguel_ano_3]] a partir do 25º (vigésimo quinto) mês, sem prejuízo do reajuste anual.',
            ]],
            ['Comissão de agência', 'Publicidade', [
                'Sendo o [[contratada]] agência de publicidade, a comissão de agência será de [[comissao_da_agencia]] sobre cada parcela, retida no ato do pagamento, mediante emissão de nota fiscal.',
            ]],
            ['Amortização de investimento', 'Locação de espaço', [
                'O investimento de [[valor_do_investimento]], suportado inicialmente pelo [[contratante]], será amortizado pelo [[contratada]] conforme planilha anexa, com saldo de [[saldo_a_amortizar]] apurado em [[data_do_saldo]], a ser pago em [[parcelas_de_amortizacao]] parcelas mensais.',
                'Em caso de atraso das parcelas, incidirão correção monetária, juros de mora de 1% (um por cento) ao mês, pro rata die, e multa de 2% (dois por cento) sobre a parcela em atraso.',
            ]],
            ['Ratificação do contrato-mãe', 'Aditivo', [
                'Permanecem em vigor, sem qualquer alteração ou substituição, todas as demais cláusulas do contrato-mãe, que as partes ratificam e se obrigam a cumprir, observados os princípios da boa-fé.',
            ]],
            ['Proteção de dados pessoais (LGPD)', 'Geral', [
                'As partes tratarão os dados pessoais a que tiverem acesso apenas para a execução deste contrato, conforme a Lei nº 13.709/2018 (LGPD), adotando medidas de segurança adequadas e dando especial cuidado a dados de crianças e adolescentes (art. 14 da LGPD).',
                'A parte que sofrer incidente de segurança envolvendo esses dados comunicará a outra em até 2 (dois) dias úteis e responderá pelos danos a que der causa.',
            ]],
            ['Inexistência de vínculo trabalhista', 'Geral', [
                'Não há vínculo empregatício nem subordinação entre o [[contratante]] e o [[contratada]] ou seus empregados e prepostos; os encargos trabalhistas, previdenciários e sociais são exclusivamente do [[contratada]].',
            ]],
            ['Responsabilidade tributária', 'Geral', [
                'Todos os tributos incidentes sobre este contrato (como ISS, ICMS e quaisquer outros federais, estaduais ou municipais) são de responsabilidade exclusiva do [[contratada]], sem ônus adicional para o [[contratante]], com comprovação do recolhimento em até 5 (cinco) dias, por exibição da nota fiscal.',
            ]],
            ['Assinatura eletrônica', 'Geral', [
                'As partes reconhecem como válida, para comprovação de autoria e integridade, a assinatura eletrônica deste instrumento, inclusive pelo sistema de assinaturas do clube ou pela plataforma gov.br, nos termos do art. 10, § 2º, da Medida Provisória nº 2.200-2/2001.',
            ]],
        ];

        return array_map(fn(array $c) => [
            'title' => $c[0],
            'category' => $c[1],
            'items' => ContractContent::items(array_map(fn(string $html) => ['level' => 1, 'html' => $html], $c[2])),
        ], $lista);
    }

    // ------------------------------------------------------------------
    // Modelos
    // ------------------------------------------------------------------

    private static function obra(): array
    {
        return self::model('Prestação de serviços — obra de engenharia (material e mão de obra)', 'obra', [
            'title' => 'Contrato de prestação de serviços',
            'establishment' => 'pet',
            'considerations' => [
                'A [[contratada]] é pessoa jurídica cujo objeto social abrange, dentre outras atividades, serviços de engenharia;',
                'As PARTES reconhecem e concordam estar obrigadas a cumprir integralmente os termos e condições deste contrato e de seus anexos, em tudo o que for aplicável às suas atividades.',
            ],
            'variables' => [
                ['descricao_do_objeto', 'Descrição do objeto (o que será executado)', 'text'],
                ['anexos', 'Anexos (lista: caderno executivo, projeto, proposta, cronograma…)', 'text'],
                ['data_de_inicio', 'Data de início da obra', 'date'],
                ['data_de_conclusao', 'Data de conclusão da obra', 'date'],
                ['valor_global', 'Valor global', 'money'],
                ['percentual_do_sinal', 'Percentual pago na assinatura', 'percent'],
                ['valor_do_sinal', 'Valor pago na assinatura', 'money'],
                ['percentual_de_retencao', 'Percentual retido como garantia', 'percent'],
                ['dados_bancarios', 'Dados bancários da contratada (banco, agência, conta, PIX)', 'text'],
                ['responsavel_tecnico', 'Responsável técnico (engenheiro) da contratada', 'text'],
                ['prazo_para_apresentar_seguros', 'Prazo para apresentar as apólices (dias)', 'number'],
                ['multa_diaria_por_atraso', 'Multa por dia de atraso', 'percent'],
                ['multa_por_descumprimento', 'Multa por descumprimento de obrigação', 'percent'],
                ['multa_por_desistencia', 'Multa por desistência da contratada', 'percent'],
                ['contato_do_clube', 'Responsável pelo clube (notificações)', 'text'],
                ['email_do_clube', 'E-mail do clube (notificações)', 'text'],
                ['contato_da_contratada', 'Responsável pela contratada (notificações)', 'text'],
                ['email_da_contratada', 'E-mail da contratada (notificações)', 'text'],
                ['prazo_de_garantia_em_anos', 'Prazo de garantia (anos)', 'number'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto', [
                    'O objeto deste contrato é a prestação, pela [[contratada]], à [[contratante]], dos serviços de mão de obra especializada e fornecimento de material, em regime de preço global, consistindo em [[descricao_do_objeto]], nas dependências da [[contratante]], conforme projeto técnico de conhecimento da [[contratada]].',
                    'A execução do objeto dar-se-á em estrita observância dos termos deste contrato e dos documentos a seguir, que o integram por referência, independentemente de terem sido ou não rubricados ou assinados pelas PARTES: [[anexos]].',
                    'Em caso de discrepância entre os termos deste contrato e os de seus anexos, prevalecerá o contrato. Se a discrepância se der entre os anexos, prevalecerá o listado primeiro no item {ref:objeto.2}.',
                ]),
                self::c('prazo', 'Prazo', [
                    'A [[contratada]] entregará a obra pronta e acabada, com início da execução em [[data_de_inicio]] e conclusão até [[data_de_conclusao]].',
                    'O prazo do item {ref:prazo.1} é improrrogável, salvo caso fortuito ou força maior devidamente comprovados e aceitos pela [[contratante]]; nesses casos, o prazo será acrescido dos dias em que a [[contratada]] não puder trabalhar.',
                    'Os serviços seguirão o cronograma de atividades, sob pena das multas e penalidades previstas neste contrato.',
                    'Havendo alteração ou revisão dos projetos durante a execução, as PARTES farão análise do impacto em prazo e custo, formalizada por aditivo.',
                ]),
                self::c('valor', 'Valor e forma de pagamento', [
                    'O valor global deste contrato, compreendendo mão de obra e fornecimento de materiais, é de [[valor_global]].',
                    'Na assinatura deste contrato, mediante apresentação de nota fiscal, a [[contratante]] pagará [[percentual_do_sinal]] do valor global, ou seja, [[valor_do_sinal]]. O saldo será pago após medições quinzenais, conforme o andamento e a execução da obra, sempre precedidas da emissão da nota fiscal competente.',
                    '>Em caso de rescisão, o adiantamento será descontado do valor devido e, não havendo saldo, a [[contratada]] devolverá o valor em 10 (dez) dias corridos, corrigido monetariamente, sob pena de multa de 10% (dez por cento) e juros de 1% (um por cento) ao mês.',
                    'A [[contratante]] reterá [[percentual_de_retencao]] do valor global, a título de garantia para fechamento financeiro, conferência final dos serviços executados, regularização de pendências e conclusão integral do objeto.',
                    'O valor retido será liberado à [[contratada]] após a conclusão total dos serviços, mediante aprovação da [[contratante]] e inexistência de pendências técnicas, financeiras, fiscais, trabalhistas ou documentais relacionadas ao contrato.',
                    'Todo material, equipamento e serviço terceirizado necessário à obra deverá ter nota fiscal emitida obrigatoriamente em nome da [[contratante]].',
                    'Os valores de materiais, equipamentos e serviços terceirizados pagos diretamente pela [[contratante]] serão integralmente deduzidos do valor global do item {ref:valor.1} e não poderão, em hipótese alguma, ser cobrados adicionalmente pela [[contratada]], que reconhece a redução proporcional e automática do valor global.',
                    'A [[contratada]] declara que a remuneração prevista contempla todos os custos diretos e indiretos da perfeita execução do objeto — mão de obra, materiais, equipamentos, ferramentas, transporte, tributos, encargos trabalhistas, previdenciários, fiscais e securitários, despesas operacionais, taxas, seguros e lucro —, renunciando a pleitos futuros de revisão de preços, compensações ou reembolsos, salvo nas hipóteses previstas em lei.',
                    'O pagamento será feito em até 5 (cinco) dias úteis após a apresentação da nota fiscal dos serviços executados, aprovada pelo Gerente do clube.',
                    'A [[contratante]] poderá suspender o pagamento em caso de atraso no cronograma, de execução em desconformidade com as especificações ou de falta da documentação mensal de regularidade trabalhista, fiscal e previdenciária dos funcionários da obra, sem prejuízo da rescisão e das penalidades deste contrato.',
                    'Os pagamentos serão feitos por depósito ou transferência para a conta de titularidade da [[contratada]]: [[dados_bancarios]].',
                    'A [[contratada]] fará pesquisa de mercado e apresentará mapa de cotação com o melhor preço e condição de pagamento de fornecedores, prestadores e demais aquisições da obra. Se a [[contratante]] obtiver condição melhor com terceiros, a diferença ficará a seu crédito e será deduzida do valor global, tendo por base o valor do mapa de cotação.',
                    'Se a [[contratante]] solicitar alteração de materiais da planilha de preços, pagará a diferença de valores.',
                ]),
                self::c('medicao', 'Medição, faturamento e pagamento', [
                    ...self::faturamento(),
                    'O atraso de pagamento pela [[contratante]] implicará juros de mora de 1% (um por cento) ao mês, pro rata die, e multa moratória de 2% (dois por cento) sobre a parcela em atraso.',
                    '<p>Para a contratação, a [[contratada]] fornecerá os documentos abaixo, sob pena de infração contratual e rescisão motivada:</p><ul><li>comprovante do CNPJ;</li><li>contrato social e suas alterações;</li><li>inscrições estadual e municipal;</li><li>alvará de localização e funcionamento;</li><li>certidões negativas das receitas estadual e municipal;</li><li>certificado de regularidade do FGTS (CRF);</li><li>certidão conjunta da Receita Federal e da Previdência Social;</li><li>certidão negativa de débitos trabalhistas.</li></ul>',
                ]),
                self::c('reajuste', 'Reajuste', [
                    'Os preços deste contrato e de seus anexos são fixos e irreajustáveis.',
                ]),
                self::c('obrigacoes', 'Obrigações da contratada', [
                    'Constituem obrigações da [[contratada]], além das demais previstas neste instrumento e em seus anexos:',
                    ...self::obrigacoesDeObra(),
                    ...self::normasDoClube(),
                    'A [[contratada]] manterá o Diário de Obra atualizado, em meio físico, com as atividades, os recursos e as ocorrências de cada dia, assinado pelo responsável técnico e pelas PARTES; ele ficará à disposição da [[contratante]] e terá valor de prova em eventuais divergências.',
                    'A responsabilidade técnica pela execução será de [[responsavel_tecnico]], que apresentará à [[contratante]] a respectiva ART expedida pelo CREA-RJ.',
                    'A [[contratada]] permitirá, a qualquer tempo, o livre acesso de representantes e diretores da [[contratante]] à obra, para acompanhá-la e fiscalizá-la, e responderá pela boa qualidade dos serviços e pela solidez da obra, nos termos da lei e deste contrato.',
                    'Aprovada a proposta, a [[contratada]] encaminhará com antecedência a cotação completa dos materiais, da locação de equipamentos especiais e do transporte de materiais; a [[contratante]] decidirá pela compra ou contratação, ou pedirá novas cotações, e a [[contratada]] acompanhará as entregas.',
                    'A [[contratada]] apresentará à [[contratante]], com antecedência mínima de 2 (dois) dias, a lista de colaboradores, gestores e fiscais, com cópia dos documentos de identidade e comprovação das contratações, para o credenciamento de entrada no clube.',
                    'A [[contratada]] administrará e orientará tecnicamente seus prestadores, eximindo a [[contratante]] de qualquer vínculo com eles, e fará o registro e a conferência diária dos serviços, gerando o Relatório de Medição.',
                    'A [[contratada]] participará, com a [[contratante]], do planejamento da movimentação de materiais e pessoas, do isolamento e da sinalização da obra, de forma segura para sócios, funcionários e visitantes.',
                ]),
                self::c('obrigacoesclube', 'Obrigações da contratante', [
                    'Constituem obrigações da [[contratante]], além das demais previstas neste instrumento:',
                    '>conferir as medições apresentadas e efetuar os pagamentos na forma pactuada, em até 5 (cinco) dias úteis da aprovação de cada medição;',
                    '>instruir a [[contratada]] sobre as diretrizes para a perfeita execução do objeto;',
                    '>permitir o acesso à área dos serviços e credenciar os funcionários da [[contratada]], orientando sobre locais, horários e tráfego de veículos e pessoas;',
                    '>fornecer água e energia elétrica, água potável, banheiro e local para refeição das equipes;',
                    '>fornecer caçambas para entulho, avisos de desvio e interdição e materiais para isolamento da área;',
                    '>designar representante técnico para dirimir dúvidas e revisões de projeto, conferir as etapas executadas e aceitá-las ou pedir sua correção;',
                    '>formalizar com a [[contratada]] o plano de trabalho: data de início, isolamento, sinalização e trajeto de veículos e entregas.',
                ]),
                self::c('seguros', 'Seguros', [
                    'A [[contratada]] contratará e apresentará à [[contratante]], em até [[prazo_para_apresentar_seguros]] dias da assinatura deste contrato, as apólices de seguro de acidentes de trabalho, de responsabilidade civil e de danos a terceiros, observado o valor global do contrato.',
                ]),
                self::c('subcontratacao', 'Subcontratação', [
                    'É vedada a subcontratação do objeto deste contrato sem prévia aprovação, por escrito, da [[contratante]].',
                    'Mesmo subcontratando, a [[contratada]] continua responsável perante a [[contratante]] pela qualidade dos serviços e dos materiais, pela segurança e solidez e por perdas e danos, e fará as subcontratadas cumprirem os mesmos termos deste contrato.',
                    'Notas fiscais de terceiros aprovadas previamente pela [[contratante]] serão aceitas, e o valor será abatido do valor global a pagar à [[contratada]].',
                ]),
                self::c('tributos', 'Incidências tributárias', [
                    'A [[contratante]], quando fonte retentora, descontará e recolherá dos pagamentos os tributos exigidos pela legislação, nos prazos legais.',
                    'A [[contratada]] fornecerá previamente os documentos necessários à redução ou dispensa da retenção, quando cabível, e os que evitem a responsabilidade solidária da [[contratante]], sob pena de compensação do tributo no primeiro pagamento seguinte.',
                ]),
                self::c('cessao', 'Cessão e transferência de direitos e obrigações', [
                    'A [[contratada]] não poderá ceder, no todo ou em parte, os direitos e obrigações deste contrato, nem ceder ou dar em garantia os créditos dele decorrentes, sem autorização prévia e por escrito da [[contratante]]; a cessão autorizada não a exime de suas obrigações.',
                ]),
                self::c('fortuito', 'Caso fortuito e força maior', [
                    'Nenhuma das PARTES responderá perante a outra por atraso ou descumprimento de suas obrigações decorrente de caso fortuito ou força maior (art. 393 do Código Civil).',
                ]),
                self::c('penalidades', 'Penalidades', [
                    'Mediante notificação escrita, e sem prejuízo da rescisão, a [[contratante]] poderá aplicar à [[contratada]], comprovada sua culpa, as seguintes multas sobre o valor global atualizado:',
                    '>atraso no cumprimento de prazos parciais ou finais: [[multa_diaria_por_atraso]] por dia de atraso;',
                    '>atraso em informar acidente de trabalho com seus empregados ou em emitir a CAT nas condições e prazos legais: [[multa_diaria_por_atraso]] por dia de atraso;',
                    '>descumprimento de obrigação sem penalidade específica: [[multa_por_descumprimento]], se não sanada em 5 (cinco) dias do recebimento da notificação.',
                    'As penalidades não excluem outras previstas em lei ou neste contrato, nem a responsabilidade da [[contratada]] por perdas e danos.',
                    'Notificada de situação que permita a aplicação de multa, a [[contratada]] terá 5 (cinco) dias para apresentar defesa.',
                    'A soma das multas aplicadas não excederá o valor global do contrato (art. 412 do Código Civil).',
                ]),
                self::c('rescisao', 'Rescisão contratual', [
                    'A [[contratante]] poderá rescindir este contrato, sem direito da [[contratada]] a indenização ou retenção, nos casos de:',
                    '>descumprimento ou cumprimento irregular de cláusulas contratuais;',
                    '>cessão ou subcontratação do objeto, ou cessão ou dação em garantia de créditos, sem anuência prévia e expressa da [[contratante]];',
                    '>falência, ou recuperação judicial ou extrajudicial sem caução suficiente para garantir as obrigações, a critério da [[contratante]];',
                    '>dissolução, alteração social ou modificação da finalidade que, a juízo da [[contratante]], prejudique a execução;',
                    '>falta de comprovação, quando solicitada, do pagamento das obrigações trabalhistas, previdenciárias e do FGTS, após 5 (cinco) dias da notificação, sem prejuízo da multa.',
                    'A rescisão permite reter os créditos da [[contratada]] para compensação, até o limite dos prejuízos causados.',
                    'Se a [[contratada]] desistir do contrato antes do seu cumprimento integral, pagará multa de [[multa_por_desistencia]] do valor global atualizado, além de outras multas específicas aplicáveis.',
                    'A [[contratada]] poderá rescindir este contrato se a execução for suspensa por ordem escrita da [[contratante]] por mais de 120 (cento e vinte) dias, ou se os pagamentos atrasarem mais de 30 (trinta) dias, salvo calamidade pública, grave perturbação da ordem ou atraso causado pela própria [[contratada]] na emissão e apresentação de seus documentos de cobrança.',
                    'Em qualquer rescisão, a [[contratada]] receberá os valores devidos pelos serviços executados até a data da rescisão, ressalvados os descontos e retenções previstos neste contrato.',
                ]),
                self::c('sigilo', 'Sigilo', [
                    'A [[contratada]] manterá sob sigilo, por 5 (cinco) anos, as informações e os dados a que tiver acesso em razão deste contrato, transmitidos por qualquer meio, com ou sem menção expressa de confidencialidade. Segredos de negócio, estratégia comercial e o que represente diferencial competitivo da [[contratante]] serão mantidos em sigilo por prazo indeterminado.',
                    'A obrigação de sigilo alcança administradores, empregados, prepostos, sucessores e comissários da [[contratada]].',
                    'O descumprimento do sigilo importará rescisão, se vigente o contrato, responsabilidade por perdas e danos e as medidas judiciais cabíveis (Lei nº 9.279/1996).',
                    'Não estão sujeitas ao sigilo a informação comprovadamente conhecida antes das tratativas ou obtida legitimamente de outra fonte, a liberada por escrito pelo titular e a exigida por ordem judicial ou governamental, avisado o titular previamente.',
                    'Divulgação sobre este contrato depende de autorização prévia da [[contratante]], ressalvada a mera notícia de sua existência; a [[contratada]] não fará publicidade, entrevista ou material que o mencione sem consentimento escrito da [[contratante]].',
                ]),
                self::c('notificacoes', 'Notificações', [
                    'Serão válidas as notificações feitas por carta com aviso de recebimento (AR) ou por e-mail com confirmação de leitura.',
                    'As notificações serão encaminhadas, pela [[contratante]], a [[contato_do_clube]] (e-mail [[email_do_clube]]) e, pela [[contratada]], a [[contato_da_contratada]] (e-mail [[email_da_contratada]]).',
                ]),
                self::c('compliance', 'Compliance', [
                    'As PARTES declaram que não praticam atos lesivos à concorrência, aos direitos humanos e às normas ambientais, que não utilizam trabalho infantil, escravo, forçado ou degradante e que se abstêm de qualquer ato previsto na Lei Anticorrupção (Lei nº 12.846/2013) e nas demais normas nacionais e internacionais aplicáveis, exigindo o mesmo de terceiros com quem se relacionem.',
                    'A PARTE que descumprir esta cláusula responderá com exclusividade pelas penalidades, condenações e prejuízos decorrentes e reembolsará a PARTE inocente de todos os custos e despesas.',
                ]),
                self::c('gerais', 'Disposições gerais', self::disposicoesGerais()),
                self::c('garantia', 'Garantia', [
                    'A [[contratada]] garante a qualidade dos serviços e a solidez da obra pelo prazo de [[prazo_de_garantia_em_anos]] anos a partir do termo de entrega, seguindo os protocolos dos fabricantes, sem prejuízo da garantia legal (art. 618 do Código Civil).',
                    'Havendo necessidade de reparo ou conserto, a [[contratada]] arcará com a mão de obra e o material necessários.',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function reforma(): array
    {
        return self::model('Prestação de serviços — reforma ou obra com medições', 'reforma', [
            'title' => 'Contrato de prestação de serviço',
            'establishment' => 'pet',
            'variables' => [
                ['descricao_dos_servicos', 'Descrição dos serviços', 'text'],
                ['materiais_do_clube', 'Materiais comprados pelo clube', 'text'],
                ['valor_global', 'Valor global estimado', 'money'],
                ['percentual_do_sinal', 'Percentual pago na assinatura', 'percent'],
                ['valor_do_sinal', 'Valor pago na assinatura', 'money'],
                ['dados_bancarios', 'Dados bancários da contratada (banco, agência, conta, PIX)', 'text'],
                ['etapas_de_medicao', 'Etapas de medição e valores', 'text'],
                ['prazo_de_execucao_em_dias', 'Prazo de execução (dias corridos)', 'number'],
                ['data_de_inicio', 'Data de início', 'date'],
                ['multa_por_descumprimento', 'Multa por descumprimento', 'percent'],
                ['multa_diaria_por_atraso', 'Multa por dia de atraso', 'percent'],
                ['prazo_de_garantia_em_anos', 'Prazo de garantia (anos)', 'number'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto', [
                    'Constitui objeto deste contrato o fornecimento de mão de obra e equipamentos para [[descricao_dos_servicos]].',
                    'A compra dos seguintes materiais é de responsabilidade da [[contratante]], antes do início dos serviços: [[materiais_do_clube]].',
                    'Todas as ferramentas manuais, os equipamentos (como retroescavadeira, caminhão basculante, placa vibratória e martelete) e o transporte dos funcionários ficam sob responsabilidade da [[contratada]].',
                ]),
                self::c('valor', 'Valor do contrato', [
                    'O valor estimado deste contrato é de [[valor_global]], sendo [[percentual_do_sinal]], no valor de [[valor_do_sinal]], pagos na assinatura e o restante após medições quinzenais, conforme o andamento e a execução da obra, observado o item {ref:valor.2}.',
                    'O pagamento será feito em até 5 (cinco) dias após a apresentação da nota fiscal do serviço executado e a aprovação do Gerente do clube, que poderá suspendê-lo em caso de atraso no cronograma ou de execução em desconformidade com o contrato.',
                    'Em caso de rescisão, o adiantamento será descontado do valor devido e, não havendo saldo, a [[contratada]] devolverá o valor em 10 (dez) dias corridos, corrigido monetariamente.',
                    'A [[contratada]] declara que a remuneração considera todos os custos diretos e indiretos, insumos, despesas, encargos sociais, seguros, tributos, comunicação, lucro e demais obrigações legais necessárias ao cumprimento do objeto, renunciando a reivindicações de revisão de preço, compensações ou reembolsos.',
                    'Dados bancários da [[contratada]]: [[dados_bancarios]].',
                ]),
                self::c('medicao', 'Medição, faturamento e pagamento', [
                    'As medições seguirão as etapas e os valores a seguir: [[etapas_de_medicao]].',
                    ...self::faturamento(),
                ]),
                self::c('execucao', 'Execução do serviço e multa', [
                    'O prazo previsto de execução dos serviços é de [[prazo_de_execucao_em_dias]] dias corridos a partir de [[data_de_inicio]].',
                    'Se a [[contratada]] descumprir qualquer cláusula deste contrato, pagará multa de [[multa_por_descumprimento]] do valor global, sem prejuízo da rescisão e da cobrança de perdas e danos.',
                ]),
                self::c('obrigacoes', 'Obrigações da contratada', [
                    'Constituem obrigações da [[contratada]], além das demais previstas neste instrumento e em seus anexos:',
                    '>apresentar o cronograma de execução dos serviços;',
                    '>arcar integralmente com a locação de todos os equipamentos necessários à execução dos serviços, incluindo, mas não se limitando a, andaimes, retroescavadeira e caminhão caçamba;',
                    ...self::obrigacoesDeObra(),
                    ...self::normasDoClube(),
                ]),
                self::c('obrigacoesclube', 'Obrigações da contratante', [
                    'Constituem obrigações da [[contratante]], além das demais previstas neste instrumento:',
                    '>efetuar os pagamentos devidos à [[contratada]], na forma pactuada;',
                    '>instruir a [[contratada]] sobre as diretrizes para a perfeita execução do objeto;',
                    '>liberar o acesso dos funcionários à área de trabalho;',
                    '>disponibilizar local para armazenamento dos materiais, equipamentos e ferramentas;',
                    '>disponibilizar vestiário e local para refeição dos colaboradores da [[contratada]];',
                    '>fornecer água e energia elétrica.',
                ]),
                self::c('penalidades', 'Penalidades', [
                    'Mediante notificação escrita, e sem prejuízo da rescisão, a [[contratante]] poderá aplicar à [[contratada]], comprovada sua culpa, as seguintes multas sobre o valor global atualizado:',
                    '>atraso no cumprimento de prazos parciais ou finais: [[multa_diaria_por_atraso]] por dia de atraso;',
                    '>atraso em informar acidente de trabalho com seus empregados ou em emitir a CAT nas condições e prazos legais: [[multa_diaria_por_atraso]] por dia de atraso;',
                    '>descumprimento de obrigação sem penalidade específica: [[multa_por_descumprimento]], se não sanada em 5 (cinco) dias do recebimento da notificação.',
                    'As penalidades não excluem outras previstas em lei ou neste contrato, nem a responsabilidade da [[contratada]] por perdas e danos diretos que comprovadamente causar.',
                    'Notificada de situação que permita a aplicação de multa, a [[contratada]] terá 5 (cinco) dias para apresentar defesa.',
                    'A soma das multas aplicadas não excederá o valor global do contrato (art. 412 do Código Civil).',
                ]),
                self::c('garantia', 'Garantia', [
                    'A [[contratada]] garante a qualidade dos serviços pelo prazo de [[prazo_de_garantia_em_anos]] anos a partir do termo de entrega, seguindo os protocolos dos fabricantes, sem prejuízo da garantia legal (art. 618 do Código Civil).',
                    'Havendo necessidade de reparo ou conserto, a [[contratada]] arcará com a mão de obra e o material necessários.',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function pontual(): array
    {
        return self::model('Prestação de serviços — serviço pontual (ex.: poda, pequenos reparos)', 'pontual', [
            'title' => 'Contrato de prestação de serviço',
            'establishment' => 'pet',
            'variables' => [
                ['descricao_dos_servicos', 'Descrição dos serviços (com quantidades e valores unitários)', 'text'],
                ['valor_global', 'Valor global', 'money'],
                ['dados_bancarios', 'Dados bancários da contratada (banco, agência, conta, PIX)', 'text'],
                ['prazo_de_execucao_em_dias', 'Prazo de execução (dias corridos)', 'number'],
                ['data_de_inicio', 'Início previsto', 'date'],
                ['multa_por_descumprimento', 'Multa por descumprimento', 'percent'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto do contrato', [
                    'Constitui objeto deste contrato: [[descricao_dos_servicos]].',
                    'Todas as ferramentas manuais, os equipamentos mecânicos e o transporte dos funcionários ficam sob responsabilidade da [[contratada]].',
                ]),
                self::c('obrigacoes', 'Obrigações da contratada', [
                    'Cabe à [[contratada]] obter previamente as licenças e autorizações exigidas para o serviço junto aos órgãos competentes (por exemplo, a autorização da Secretaria Municipal do Meio Ambiente para poda ou supressão de árvores), cumprindo todas as exigências legais e administrativas.',
                    'Correm por conta da [[contratada]] todas as obrigações e encargos trabalhistas e previdenciários de seus empregados e quaisquer outras decorrentes da relação de emprego no período do serviço.',
                    ...self::normasDoClube(),
                    'A [[contratada]] fornecerá e fiscalizará o uso de todos os equipamentos de proteção individual e seguirá as normas de segurança do trabalho vigentes, com execução e acompanhamento técnico dedicados.',
                    'Todos os tributos incidentes sobre este contrato (como ISS, ICMS e quaisquer outros federais, estaduais ou municipais) são de responsabilidade exclusiva da [[contratada]], sem ônus adicional para a [[contratante]], com comprovação do recolhimento em até 5 (cinco) dias, por exibição da nota fiscal.',
                    'O descumprimento das obrigações tributárias pela [[contratada]] configura violação material do contrato e sujeita à multa do item {ref:execucao.2}, à rescisão e à indenização por perdas e danos.',
                ]),
                self::c('valor', 'Valor do contrato', [
                    'O valor global deste contrato é de [[valor_global]], pago na conclusão dos serviços e com o aceite da [[contratante]].',
                    'O pagamento será feito em até 5 (cinco) dias após a apresentação da nota fiscal do serviço executado e a aprovação do Gerente do clube, que poderá suspendê-lo em caso de atraso ou de execução em desconformidade com o solicitado.',
                    'Dados bancários da [[contratada]]: [[dados_bancarios]].',
                ]),
                self::c('execucao', 'Execução do serviço e multa', [
                    'O prazo de entrega do serviço é estimado em [[prazo_de_execucao_em_dias]] dias corridos, com início previsto para [[data_de_inicio]], condicionado à obtenção das autorizações do item {ref:obrigacoes.1}.',
                    'Se a [[contratada]] descumprir qualquer cláusula deste contrato, pagará multa de [[multa_por_descumprimento]] do valor global, sem prejuízo da rescisão e da cobrança de perdas e danos.',
                ]),
                self::c('vinculo', 'Inexistência de vínculo trabalhista', [
                    'Não há vínculo empregatício nem subordinação entre a [[contratante]] e a [[contratada]] ou seus empregados; as obrigações previdenciárias e os encargos sociais são exclusivamente da [[contratada]].',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function diarias(): array
    {
        return self::model('Prestação de serviços — mão de obra por diárias', 'diarias', [
            'title' => 'Contrato de prestação de serviço',
            'establishment' => 'sede',
            'variables' => [
                ['descricao_dos_servicos', 'Descrição dos serviços', 'text'],
                ['quantidade_de_diarias', 'Quantidade de diárias', 'number'],
                ['valor_da_diaria', 'Valor da diária', 'money'],
                ['valor_estimado', 'Valor estimado (diárias × valor)', 'money'],
                ['data_de_inicio', 'Data de início', 'date'],
                ['horario', 'Horário (ex.: das 7h30 às 16h30)', 'text'],
                ['dados_bancarios', 'Dados bancários da contratada (banco, agência, conta, PIX)', 'text'],
                ['multa_por_descumprimento', 'Multa por descumprimento', 'percent'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto do contrato', [
                    'Constitui objeto deste contrato a prestação de serviços de mão de obra qualificada para [[descricao_dos_servicos]].',
                    'A compra de material é de responsabilidade da [[contratante]]; as ferramentas manuais, os equipamentos e o transporte ficam sob responsabilidade da [[contratada]].',
                ]),
                self::c('obrigacoes', 'Obrigações da contratada', [
                    'Correm por conta da [[contratada]] todas as obrigações e encargos trabalhistas e previdenciários de seus empregados e quaisquer outras decorrentes da relação de emprego no período do serviço.',
                    'Todo equipamento necessário será transportado por conta e risco da [[contratada]] e guardado em local apropriado; a [[contratante]] não responde por sua perda ou desaparecimento.',
                    'As despesas de estadia são da [[contratada]]; a alimentação no período do serviço será fornecida pela [[contratante]], no local por ela indicado.',
                    'Ficam a cargo da [[contratada]] o fornecimento e a fiscalização do uso de todo equipamento de proteção individual.',
                    'Os colaboradores da [[contratada]] só circularão pelo clube por necessidade do trabalho, uniformizados e identificados com crachá, e se cadastrarão no Setor de Atendimento, onde receberão as orientações das normas de segurança do clube.',
                    'É de responsabilidade da [[contratada]] a limpeza diária da área de trabalho ao fim do expediente, sob pena de multa de 5% (cinco por cento) do valor global por infração.',
                    'Todos os tributos incidentes sobre este contrato são de responsabilidade exclusiva da [[contratada]], sem ônus adicional para a [[contratante]], com comprovação do recolhimento em até 5 (cinco) dias, por exibição da nota fiscal; o descumprimento sujeita à multa do item {ref:execucao.2}, à rescisão e a perdas e danos.',
                ]),
                self::c('valor', 'Valor do contrato', [
                    'O valor estimado deste contrato é de [[valor_estimado]], correspondente a [[quantidade_de_diarias]] diárias de [[valor_da_diaria]]. Prevalece a quantidade de diárias efetivamente trabalhadas, conforme as medições semanais da coordenação de manutenção.',
                    'Dados bancários da [[contratada]]: [[dados_bancarios]].',
                    'O preço é firme e irreajustável durante a vigência deste contrato.',
                    'O pagamento será feito em até 5 (cinco) dias após a apresentação da nota fiscal do serviço executado e a aprovação do Gerente do clube, que poderá suspendê-lo em caso de atraso ou de execução em desconformidade com o solicitado.',
                ]),
                self::c('execucao', 'Execução do serviço e multa', [
                    'Os serviços serão prestados em [[quantidade_de_diarias]] dias úteis, a partir de [[data_de_inicio]].',
                    'Se a [[contratada]] descumprir qualquer cláusula deste contrato, pagará multa de [[multa_por_descumprimento]] do valor global, sem prejuízo da rescisão e da cobrança de perdas e danos.',
                ]),
                self::c('vinculo', 'Inexistência de vínculo trabalhista', [
                    'Não há vínculo empregatício nem subordinação entre a [[contratante]] e a [[contratada]] ou seus empregados; as obrigações previdenciárias e os encargos sociais são exclusivamente da [[contratada]].',
                    'A [[contratada]] executa os serviços com autonomia técnica, com seus próprios meios e por sua conta e risco; a [[contratante]] apenas define o resultado esperado e confere a execução.',
                ]),
                self::c('horario', 'Horário da prestação do serviço e impacto no pagamento', [
                    'Os serviços serão executados de segunda a sexta-feira, [[horario]], com intervalo de 1 (uma) hora para almoço.',
                    'O não cumprimento do horário combinado poderá resultar em desconto proporcional nas medições.',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function uniformes(): array
    {
        return self::model('Fornecimento — confecção de uniformes', 'uniforme', [
            'title' => 'Contrato de prestação de serviço',
            'establishment' => 'pet',
            'variables' => [
                ['itens_quantidades_e_valores', 'Itens, quantidades e valores unitários', 'text'],
                ['valor_global', 'Valor global', 'money'],
                ['prazo_de_entrega_em_dias', 'Prazo de entrega (dias corridos)', 'number'],
                ['multa_por_descumprimento', 'Multa por descumprimento', 'percent'],
                ['prazo_de_garantia_em_dias', 'Prazo de garantia (dias)', 'number'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto do contrato', [
                    'Constitui objeto deste contrato a confecção de uniformes pela [[contratada]], para os funcionários da [[contratante]], nos moldes e formas aqui ajustados.',
                ]),
                self::c('itens', 'Discriminação dos uniformes, quantidades e valores', [
                    'Itens, quantidades e valores unitários: [[itens_quantidades_e_valores]].',
                    'O setor de Recursos Humanos da [[contratante]] informará os tamanhos por meio de planilha.',
                ]),
                self::c('valor', 'Valor e forma de pagamento', [
                    'O valor global deste contrato é de [[valor_global]].',
                    'O pagamento será feito em até 5 (cinco) dias após a apresentação da nota fiscal e a aprovação do Gerente do clube, que poderá suspendê-lo em caso de atraso ou de desconformidade com o solicitado, sob pena até de rescisão e da multa do item {ref:penal.2}.',
                ]),
                self::c('impostos', 'Impostos', [
                    'Os tributos incidentes sobre este contrato são de responsabilidade da [[contratada]].',
                ]),
                self::c('entrega', 'Prazo de entrega', [
                    'Os uniformes serão entregues pela [[contratada]] em até [[prazo_de_entrega_em_dias]] dias corridos a contar da assinatura deste contrato.',
                    'A [[contratada]] entregará antecipadamente os uniformes que ficarem prontos.',
                ]),
                self::c('obrigacoes', 'Obrigações da contratada', [
                    'Responsabilizar-se pelo cumprimento dos prazos e pela qualidade dos trabalhos.',
                    'Prestar os esclarecimentos solicitados pela [[contratante]], atender prontamente suas reclamações e comunicar-lhe imediatamente, por escrito, qualquer anormalidade.',
                    'Responder pelos prejuízos decorrentes do descumprimento das obrigações contratuais.',
                    'Arcar com todas as despesas necessárias ao cumprimento integral do objeto.',
                    'Reparar ou trocar imediatamente as peças com defeito apresentadas pela [[contratante]], sob pena de rescisão.',
                ]),
                self::c('penal', 'Cláusula penal', [
                    'Se os uniformes não atenderem ao padrão de qualidade dos moldes apresentados à [[contratante]] na pré-venda, ela poderá suspender imediatamente o pagamento e cancelar a compra por simples notificação, devendo a [[contratada]] devolver os valores recebidos e arcar com as perdas e danos.',
                    'Além disso, o descumprimento de qualquer obrigação da [[contratada]], seja quanto ao prazo de entrega, seja quanto à qualidade dos uniformes, acarretará multa de [[multa_por_descumprimento]] sobre o valor global do contrato, com direito à sua imediata rescisão pela [[contratante]].',
                ]),
                self::c('garantia', 'Garantia', [
                    'A [[contratada]] garante a qualidade do produto pelo prazo de [[prazo_de_garantia_em_dias]] dias a partir da entrega, desde que manuseado e lavado de acordo com as instruções de lavagem.',
                    'Havendo necessidade de reparo ou conserto, a [[contratada]] arcará com a mão de obra e o material necessários.',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function locacao(): array
    {
        return self::model('Locação de imóvel comercial (loja ou sala) com fiador', 'locacao', [
            'title' => 'Instrumento particular de contrato de locação de imóvel',
            'establishment' => 'sede',
            'contractor_term' => 'LOCADOR',
            'parties' => [
                ['role' => ContractContent::ROLE_CONTRATADA, 'term' => 'LOCATÁRIO', 'kind' => 'pj'],
                ['role' => ContractContent::ROLE_FIADOR, 'term' => 'FIADOR', 'kind' => 'pf'],
            ],
            'signers' => [
                ['party' => ContractContent::SIGNER_CONTRATADA, 'source' => 'person'],
                ['party' => ContractContent::SIGNER_FIADOR, 'source' => 'person', 'caption' => 'Fiador'],
                ['party' => ContractContent::SIGNER_FIADOR, 'source' => 'person', 'caption' => 'Cônjuge do fiador (outorga)'],
                ['party' => ContractContent::SIGNER_TESTEMUNHA, 'source' => 'person'],
                ['party' => ContractContent::SIGNER_TESTEMUNHA, 'source' => 'person'],
            ],
            'variables' => [
                ['endereco_do_imovel', 'Endereço do imóvel (com loja/sala)', 'text'],
                ['area_do_imovel', 'Área do imóvel (ex.: 78,64 m²)', 'text'],
                ['destinacao', 'Destinação (ex.: comércio de roupas e acessórios)', 'text'],
                ['prazo_em_meses', 'Prazo de vigência (meses)', 'number'],
                ['data_de_inicio', 'Início da locação', 'date'],
                ['data_de_termino', 'Término da locação', 'date'],
                ['valor_do_aluguel', 'Aluguel mensal', 'money'],
                ['dia_de_vencimento', 'Dia do vencimento', 'number'],
                ['email_para_boletos', 'E-mail para os boletos', 'text'],
                ['indice_de_reajuste', 'Índice de reajuste (ex.: IGP-M/FGV)', 'text'],
                ['nome_do_edificio', 'Edifício cujo regimento interno se aplica', 'text'],
                ['email_do_locador', 'E-mail do clube para notificações', 'text'],
                ['email_do_locatario', 'E-mail do locatário para notificações', 'text'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto', [
                    'O objeto deste contrato é a locação do imóvel situado à [[endereco_do_imovel]], com área total de [[area_do_imovel]], que o [[contratada]] declara receber em perfeitas condições de uso e ocupação, conforme laudo de vistoria assinado pelas partes, com todos os seus pertences e instalações em perfeito funcionamento, obrigando-se a zelar por sua conservação e manutenção durante a vigência.',
                    'O imóvel destina-se exclusivamente a [[destinacao]], sendo vedado ao [[contratada]] mudar a destinação sem autorização prévia e por escrito do [[contratante]], sob pena de infração contratual grave e rescisão.',
                ]),
                self::c('prazo', 'Prazo de vigência', [
                    'O prazo de vigência é de [[prazo_em_meses]] meses, de [[data_de_inicio]] a [[data_de_termino]], renovável por acordo das partes, por escrito.',
                    'Findo o prazo e continuando o [[contratada]] no imóvel, permanecem válidas todas as cláusulas até a entrega das chaves ou a assinatura de novo contrato, no qual o aluguel poderá ser revisto com base no valor de mercado.',
                    'Fica assegurada ao [[contratada]] a preferência na renovação, devendo manifestar seu interesse ao [[contratante]], por escrito, até 90 (noventa) dias antes do término.',
                    'Na rescisão antecipada pelo [[contratada]], mediante aviso prévio por escrito de 30 (trinta) dias, será devida multa de 3 (três) aluguéis vigentes, reduzida proporcionalmente ao prazo cumprido (art. 4º da Lei nº 8.245/1991). O pedido será formalizado no setor Comercial do [[contratante]], com a última conta de energia elétrica quitada.',
                    'Finda ou rescindida a locação, o [[contratada]] devolverá o imóvel nas mesmas condições em que o recebeu, independentemente de interpelação judicial.',
                ]),
                self::c('aluguel', 'Valor do aluguel, impostos e taxas', [
                    'O aluguel mensal é de [[valor_do_aluguel]], com vencimento no dia [[dia_de_vencimento]] de cada mês; não sendo dia útil, o vencimento passa para o primeiro dia útil seguinte.',
                    'O pagamento será feito por boleto bancário, enviado ao e-mail [[email_para_boletos]], ou por outro meio indicado pelo [[contratante]].',
                    'O atraso no pagamento do aluguel e dos encargos sujeita o [[contratada]], entre outras medidas, inclusive judiciais, a multa de 10% (dez por cento), juros de mora de 1% (um por cento) ao mês e correção monetária sobre o débito atualizado.',
                    'Além disso, mediante comunicação prévia, o débito poderá ser protestado (Lei nº 9.492/1997), valendo este contrato como título, e comunicado aos órgãos de proteção ao crédito.',
                    'O [[contratada]] contratará, às suas expensas, seguro do imóvel contra incêndio e outros sinistros, durante toda a locação, com a seguradora de sua escolha, no valor de 100 (cem) aluguéis, tendo o [[contratante]] como beneficiário, e o apresentará em até 30 (trinta) dias do início da locação, sob pena de infração grave. O seguro não cobre os bens do [[contratada]], que ficam sob sua responsabilidade e risco.',
                    'São de responsabilidade do [[contratada]] o IPTU, a taxa de incêndio, a taxa de coleta de lixo, as despesas condominiais, se houver, e as contas de energia, água, esgoto e gás, inclusive ligações e transferências.',
                    'O IPTU será pago em cota única ou nas parcelas do exercício; o [[contratante]] poderá pagá-lo diretamente, avisando antes o [[contratada]], e repassar integralmente o custo nas cobranças mensais.',
                ]),
                self::c('reajuste', 'Reajuste do aluguel', [
                    'Para manter o equilíbrio econômico do contrato, o aluguel será reajustado a cada 12 (doze) meses pela variação positiva do [[indice_de_reajuste]], ou do índice oficial que o substituir, independentemente de aviso, inclusive se o [[contratada]] permanecer no imóvel após o término do prazo.',
                    'Se a lei vier a permitir reajuste em periodicidade menor, ela será aplicada automaticamente.',
                ]),
                self::c('gerais', 'Disposições gerais', [
                    'É vedado ao [[contratada]], sem autorização prévia e por escrito do [[contratante]], fazer alterações, acréscimos, demolições ou benfeitorias no imóvel, devendo apresentar o projeto (croqui ou leiaute). As benfeitorias consentidas integram o imóvel, sem direito a indenização ou retenção (art. 35 da Lei nº 8.245/1991).',
                    'O [[contratada]] poderá instalar divisórias, estantes e armários removíveis, devendo, ao final, retirá-los e repor o imóvel no estado anterior, às suas expensas.',
                    'É vedado ao [[contratada]] sublocar, ceder ou emprestar o imóvel, no todo ou em parte, sem autorização prévia e por escrito do [[contratante]].',
                    'Constatada a sublocação, cessão ou empréstimo vedados no item {ref:gerais.3}, o [[contratada]] pagará multa de 3 (três) aluguéis e devolverá imediatamente o imóvel.',
                    'Alteração do quadro social do [[contratada]] será comunicada previamente e por escrito ao [[contratante]], para que anua com a continuidade da locação, sob pena de infração grave e rescisão motivada.',
                    'O [[contratante]] poderá, por si ou por pessoas de sua confiança, vistoriar o imóvel para apurar o cumprimento deste contrato.',
                    'A locação não inclui vagas de garagem nem o uso comercial das áreas comuns, como corredores, saídas de emergência, escadas e hall.',
                    'A mudança, a instalação e as obras serão combinadas com o gerente do [[contratante]], evitando horários de maior movimento, com autorização prévia nos fins de semana e sem barulho que prejudique o prédio ou os demais locatários.',
                    'Cabem ao [[contratada]] a limpeza e a conservação da área locada e das áreas comuns de seu uso.',
                    'É vedado estocar, manusear ou elaborar no imóvel produtos químicos, tóxicos ou que causem risco de incêndio ou explosão, sob pena de rescisão e de responsabilidade por todos os danos.',
                    'Aumento de carga elétrica depende de autorização prévia do [[contratante]], e o custo de redimensionamento da rede será do [[contratada]].',
                    'A instalação de equipamentos no terraço ou na cobertura depende de pedido prévio e por escrito; equipamento que cause interferência ou exposição indevida de terceiros será retirado imediatamente.',
                    'O gerente do [[contratante]] é a contraparte para gerir os direitos e obrigações deste contrato.',
                    'O [[contratada]] observará as posturas das autoridades públicas e o Regimento Interno do [[nome_do_edificio]], do qual recebe cópia, e o fará cumprir por funcionários, visitantes e prestadores.',
                    'Findo o contrato, as chaves só serão aceitas com o imóvel nas condições da vistoria inicial, correndo o aluguel até a devolução.',
                    'O contrato poderá ser rescindido por descumprimento de qualquer cláusula ou da lei de locações, com cobrança da multa de 3 (três) aluguéis.',
                    'A tolerância do [[contratante]] no cumprimento das obrigações não importa novação nem modificação deste contrato.',
                    'A quitação de um aluguel ou encargo não presume a quitação dos anteriores, renunciando o [[contratada]] à presunção do art. 322 do Código Civil.',
                    'As citações e notificações decorrentes deste contrato poderão ser feitas por e-mail: o do [[contratante]] é [[email_do_locador]] e o do [[contratada]] é [[email_do_locatario]].',
                ]),
                self::c('fianca', 'Garantia fidejussória', [
                    'Assina este contrato como FIADOR, solidariamente responsável com o [[contratada]] por todas as obrigações aqui assumidas, a pessoa qualificada no preâmbulo, que renuncia expressamente ao benefício de ordem (arts. 827 e 828 do Código Civil) e à faculdade de exonerar-se da fiança (art. 835 do Código Civil).',
                    'A fiança vigora até a efetiva entrega das chaves, ainda que a locação seja prorrogada por prazo indeterminado (art. 39 da Lei nº 8.245/1991), e abrange os reajustes e aumentos do aluguel.',
                    'Sendo casado, o FIADOR apresenta a outorga do cônjuge (art. 1.647, III, do Código Civil), que também assina este contrato.',
                    'As citações e notificações ao FIADOR poderão ser feitas por via postal, com aviso de recebimento.',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function teloes(): array
    {
        return self::model('Publicidade nos telões de LED', 'telao', [
            'title' => 'Contrato de veiculação de publicidade em telões de LED',
            'establishment' => 'pet',
            'contractor_term' => 'CLUBE',
            'parties' => [
                ['role' => ContractContent::ROLE_CONTRATADA, 'term' => 'ANUNCIANTE', 'kind' => 'pj'],
            ],
            'variables' => [
                ['locais_de_veiculacao', 'Locais dos telões (ex.: na entrada principal e próximo à Academia)', 'text'],
                ['prazo_em_meses', 'Prazo (meses)', 'number'],
                ['data_de_inicio', 'Início', 'date'],
                ['data_de_termino', 'Término', 'date'],
                ['valor_mensal', 'Valor mensal', 'money'],
                ['valor_total', 'Valor total do período', 'money'],
                ['dia_de_vencimento', 'Dia do vencimento', 'number'],
                ['duracao_maxima_do_video', 'Duração máxima do vídeo (segundos)', 'number'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto', [
                    'Constitui objeto deste contrato a veiculação de publicidade institucional e/ou comercial do [[contratada]] nos telões de LED do [[contratante]], localizados [[locais_de_veiculacao]].',
                ]),
                self::c('prazo', 'Prazo', [
                    'O contrato vigorará por [[prazo_em_meses]] meses, de [[data_de_inicio]] a [[data_de_termino]], podendo ser renovado por termo aditivo.',
                ]),
                self::c('preco', 'Preço e pagamento', [
                    'O valor mensal é de [[valor_mensal]], totalizando [[valor_total]] no período contratado, com pagamento até o dia [[dia_de_vencimento]] de cada mês.',
                    'O [[contratante]] enviará ao [[contratada]], até o dia 1º (primeiro) de cada mês, o boleto bancário e a nota fiscal referentes ao período de veiculação.',
                ]),
                self::c('reajuste', 'Reajuste', [
                    'Em caso de renovação, os valores serão reajustados anualmente pelo IPCA ou pelo índice oficial que o substituir.',
                ]),
                self::c('obrigacoesclube', 'Obrigações do clube', [
                    'Disponibilizar os espaços publicitários, manter os equipamentos em funcionamento e promover a veiculação da publicidade, ressalvadas as interrupções por manutenção ou força maior.',
                ]),
                self::c('obrigacoes', 'Obrigações do anunciante', [
                    'Fornecer as artes e/ou vídeos de até [[duracao_maxima_do_video]] segundos, em formato compatível, garantir a legalidade do conteúdo, efetuar os pagamentos e manter seus dados atualizados.',
                ]),
                self::c('conteudo', 'Conteúdo da publicidade', [
                    'Não será permitida publicidade ilícita, ofensiva, político-partidária, religiosa ou incompatível com os princípios institucionais do [[contratante]], que poderá recusar ou retirar o material nessas hipóteses.',
                ]),
                self::c('inadimplencia', 'Inadimplência', [
                    'O atraso sujeitará o [[contratada]] a multa de 2% (dois por cento), juros de 1% (um por cento) ao mês e correção pelo IPCA. Após 15 (quinze) dias de inadimplência, o [[contratante]] poderá suspender a veiculação.',
                ]),
                self::c('rescisao', 'Rescisão', [
                    'O contrato poderá ser rescindido por acordo, por inadimplemento ou por aviso prévio de 30 (trinta) dias, observado o prazo mínimo contratado.',
                ]),
                self::c('exclusividade', 'Ausência de exclusividade', [
                    'Este contrato não confere exclusividade ao [[contratada]].',
                ]),
                self::c('responsabilidade', 'Responsabilidade', [
                    'O [[contratada]] responde integralmente pelo conteúdo da publicidade veiculada, inclusive por direitos autorais, de imagem e de marca, e manterá o [[contratante]] indene de reclamações de terceiros.',
                ]),
                self::c('cessao', 'Cessão', [
                    'É vedada a cessão ou transferência deste contrato sem autorização prévia do [[contratante]].',
                ]),
                self::c('gerais', 'Disposições gerais', [
                    'Este contrato tem natureza exclusivamente comercial e é regido pelo Código Civil.',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function permuta(): array
    {
        return self::model('Permuta', 'permuta', [
            'title' => 'Contrato de permuta',
            'establishment' => 'pet',
            'variables' => [
                ['o_que_o_clube_oferece', 'O que o clube oferece (ex.: joia e readmissões)', 'text'],
                ['o_que_a_contratada_oferece', 'O que a contratada oferece (ex.: desconto na compra das madeiras do pergolado)', 'text'],
                ['valor_total_da_permuta', 'Valor total da permuta', 'money'],
                ['beneficiarios', 'Beneficiários (nome, CPF e valor de cada um)', 'text'],
                ['pedido_de_compra', 'Pedido de compra (OC) e data da dedução', 'text'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto do contrato', [
                    'Este contrato tem por objeto a permuta de [[o_que_o_clube_oferece]], oferecidos pela [[contratante]], por [[o_que_a_contratada_oferece]], fornecidos pela [[contratada]].',
                ]),
                self::c('valor', 'Valor', [
                    'A [[contratante]] oferece em permuta o valor total de [[valor_total_da_permuta]], com os seguintes beneficiários: [[beneficiarios]].',
                    'O valor da permuta será deduzido do pedido de compra [[pedido_de_compra]].',
                    'Os benefícios concedidos em permuta seguem o Estatuto e os regulamentos do clube e são pessoais e intransferíveis.',
                ]),
                self::c('foro', 'Foro de eleição', self::foro()),
            ],
        ]);
    }

    private static function escolinha(): array
    {
        return self::model('Locação de espaço esportivo — escolinha', 'escolinha', [
            'title' => 'Contrato de locação de espaço para aulas esportivas',
            'establishment' => 'pet',
            'contractor_term' => 'LOCADOR',
            'parties' => [
                ['role' => ContractContent::ROLE_CONTRATADA, 'term' => 'LOCATÁRIO', 'kind' => 'pj'],
            ],
            'variables' => [
                ['espacos_locados', 'Espaços locados (ex.: campos society e quadra de futsal)', 'text'],
                ['modalidades', 'Modalidades (ex.: futebol e futsal)', 'text'],
                ['professores', 'Professores e estagiários', 'text'],
                ['data_de_inicio', 'Início da vigência', 'date'],
                ['data_de_termino', 'Término da vigência', 'date'],
                ['dias_horarios_e_locais', 'Dias, horários e locais das aulas', 'text'],
                ['valores_cobrados', 'Valores cobrados dos alunos (matrícula e contribuição, sócio e não sócio)', 'text'],
                ['taxa_de_servico_mensal', 'Taxa de serviço mensal do clube', 'money'],
                ['percentual_do_clube', 'Percentual do clube sobre o faturamento', 'percent'],
                ['dia_do_repasse', 'Dia do repasse', 'number'],
                ['desconto_membros_dos_poderes', 'Desconto para membros dos poderes do clube', 'percent'],
                ['multa_por_omissao_de_acidente', 'Multa por omissão de acidente de trabalho (por ocorrência)', 'money'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto', [
                    'Constitui objeto deste contrato a locação de [[espacos_locados]] da Praça de Esportes Tabajaras, nos dias e horários definidos neste contrato, para a realização, pelo [[contratada]], de aulas de [[modalidades]] para crianças e adolescentes, sócios ou não do clube.',
                    'Os professores de Educação Física são de responsabilidade do [[contratada]], que arcará com seus salários, alimentação, transporte e tudo o que a legislação exige, excluída qualquer responsabilidade trabalhista do [[contratante]].',
                    'Os professores e estagiários contratados são: [[professores]]. Toda inclusão ou exclusão de profissional será informada por escrito ao [[contratante]].',
                ]),
                self::c('vigencia', 'Prazo de vigência', [
                    'A vigência deste contrato é de [[data_de_inicio]] a [[data_de_termino]], prorrogável por igual período mediante aditivo, havendo interesse das partes e cumprimento das metas por elas estabelecidas.',
                    'Qualquer das partes poderá rescindir o contrato mediante comunicação por escrito com 30 (trinta) dias de antecedência, sem ônus e independentemente do motivo.',
                ]),
                self::c('utilizacao', 'Período de utilização', [
                    'As aulas ocorrerão nos seguintes dias, horários e locais: [[dias_horarios_e_locais]].',
                    'O uso de espaços sujeitos a disponibilidade (como ginásio ou campo principal em eventos) depende de confirmação prévia do [[contratante]].',
                    'Qualquer modificação de dias, horários ou locais só ocorrerá com aprovação do [[contratante]], por escrito e com antecedência.',
                ]),
                self::c('pagamentos', 'Pagamentos e descontos', [
                    'Os valores cobrados dos alunos são: [[valores_cobrados]].',
                    'Quando o recebimento das contribuições dos alunos e o atendimento a eles e a seus responsáveis ficarem a cargo do [[contratante]], será cobrada taxa de serviço mensal de [[taxa_de_servico_mensal]].',
                    'Pela locação dos espaços, o [[contratada]] pagará ao [[contratante]], por mês, [[percentual_do_clube]] do faturamento bruto das modalidades, incluídas as matrículas.',
                    'O repasse será feito até o dia [[dia_do_repasse]] de cada mês, sobre o faturamento do mês anterior, acompanhado de relatório detalhado dos recebimentos, com a identificação dos alunos matriculados, os valores recebidos e o faturamento bruto do período.',
                    'O atraso no repasse implicará juros de mora de 1% (um por cento) ao mês, pro rata die, e multa moratória de 2% (dois por cento) sobre a parcela em atraso.',
                    'O [[contratada]] concederá desconto de [[desconto_membros_dos_poderes]] sobre os valores cobrados aos membros dos poderes do clube e a seus dependentes.',
                    'Os valores cobrados dos alunos poderão ser reajustados anualmente pelo INPC (IBGE), ou pelo índice que o substituir, havendo interesse das partes; a alteração só vale com a anuência do [[contratante]].',
                ]),
                self::c('gerais', 'Disposições gerais', [
                    'O [[contratante]] poderá vetar a frequência de aluno que desobedecer às disposições estatutárias do clube ou, a pedido do [[contratada]], por inadimplência.',
                    'O [[contratada]] comunicará ao [[contratante]] qualquer irregularidade disciplinar ou de conservação das instalações.',
                    'Todo interessado poderá fazer uma aula experimental antes de se matricular.',
                    'O material das aulas é de responsabilidade do [[contratada]], podendo vir de patrocínios, sem ônus para o [[contratante]].',
                    'O [[contratada]] comunicará com antecedência à secretaria da PET quando não houver aula, quando houver alteração de dia ou horário e quando for substituído por outro orientador, informando o nome.',
                    'A carteira de identificação de cada aluno será feita pelo [[contratante]], mediante taxa por ele definida.',
                    'Qualquer alteração deste contrato só valerá por aditivo; eventuais tolerâncias não constituem novação.',
                    'O [[contratada]] informará por escrito aos alunos, responsáveis e à secretaria as normas da escolinha, as formas de pagamento, os recessos e férias e os descontos e isenções que conceder.',
                    'O [[contratada]] informará ao [[contratante]] a matrícula de não sócios, para a liberação de entrada na portaria, e apresentará mensalmente a lista de chamada dos alunos.',
                    'O [[contratada]] e seus auxiliares apresentarão cópia da cédula de identidade profissional do CREF e do comprovante de pagamento da anuidade em dia.',
                    'O [[contratada]] não permitirá o uso de calçados inadequados ao piso, como chuteiras com travas nos campos sintéticos, avisando alunos e responsáveis na matrícula; o descumprimento acarretará rescisão, multa e reparação dos danos.',
                    'A divulgação da escolinha é responsabilidade do [[contratada]], que poderá usar a mídia do clube, respeitadas as normas e com autorização da Diretoria.',
                    'Havendo evento nos espaços locados, as aulas serão suspensas e repostas depois, sem ônus para as partes.',
                    'O [[contratada]] apresentará cópia da CTPS anotada de seus funcionários e, mensalmente, os comprovantes de recolhimento de INSS, FGTS e demais verbas trabalhistas, sob pena de infração contratual e rescisão.',
                ]),
                self::c('responsabilidade', 'Responsabilidade do locatário', [
                    'Correm por conta do [[contratada]] todas as obrigações e encargos trabalhistas e previdenciários de seus empregados e quaisquer outras decorrentes da relação de emprego durante a vigência.',
                    'O [[contratada]] informará ao [[contratante]], em até 2 (dois) dias corridos, qualquer acidente de trabalho ocorrido nas instalações do clube com seus empregados ou com os de subcontratadas.',
                    'A omissão da informação do item {ref:responsabilidade.2}, ou da emissão da CAT nas condições e prazos legais, sujeita o [[contratada]] a multa de [[multa_por_omissao_de_acidente]] por ocorrência.',
                ]),
                self::c('dados', 'Proteção de dados pessoais', [
                    'As partes tratarão os dados pessoais de alunos e responsáveis apenas para a execução deste contrato, conforme a Lei nº 13.709/2018 (LGPD), com especial cuidado aos dados de crianças e adolescentes (art. 14 da LGPD), e comunicarão à outra parte, em até 2 (dois) dias úteis, qualquer incidente de segurança com esses dados.',
                ]),
                self::c('iss', 'Retenção do ISS', [
                    'O [[contratada]] apresentará as guias de recolhimento do ISS incidente sobre as mensalidades dos alunos, conforme a legislação municipal (Lei Municipal nº 5.398/2017).',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    private static function aditivo(): array
    {
        return self::model('Aditivo de contrato feito fora do Lara', 'aditivo', [
            'title' => 'Aditivo de contrato de prestação de serviço',
            'establishment' => 'pet',
            'variables' => [
                ['contrato_mae', 'Contrato-mãe (ex.: CT 145/26)', 'text'],
                ['data_do_contrato_mae', 'Data do contrato-mãe', 'date_long'],
                ['objeto_do_aditivo', 'O que o aditivo inclui ou altera', 'text'],
                ['valor_do_contrato_mae', 'Valor do contrato-mãe (com aditivos anteriores)', 'money'],
                ['valor_do_aditivo', 'Valor deste aditivo', 'money'],
                ['novo_valor_global', 'Novo valor global', 'money'],
                ['forma_de_pagamento', 'Forma de pagamento do aditivo', 'text'],
                ['prazo_de_execucao_em_dias', 'Prazo de execução (dias corridos)', 'number'],
                ['data_de_inicio', 'Início da execução', 'date'],
            ],
            'clauses' => [
                self::c('objeto', 'Objeto do aditivo', [
                    'Este aditivo altera o contrato [[contrato_mae]], celebrado entre as partes em [[data_do_contrato_mae]] (contrato-mãe), para incluir: [[objeto_do_aditivo]].',
                ]),
                self::c('valor', 'Valor do contrato', [
                    'O valor global do contrato passa a ser de [[novo_valor_global]], sendo [[valor_do_contrato_mae]] advindos do contrato-mãe e [[valor_do_aditivo]] oriundos deste aditivo.',
                    'O valor deste aditivo será pago da seguinte forma: [[forma_de_pagamento]].',
                ]),
                self::c('prazo', 'Execução do serviço', [
                    'O prazo de execução dos serviços deste aditivo é de [[prazo_de_execucao_em_dias]] dias corridos a partir de [[data_de_inicio]].',
                ]),
                self::c('ratificacao', 'Ratificação', [
                    'Permanecem em vigor, sem qualquer alteração ou substituição, todas as demais cláusulas do contrato-mãe, que as partes ratificam e se obrigam a cumprir, observados os princípios da boa-fé.',
                ]),
                self::c('foro', 'Foro', self::foro()),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Blocos repetidos
    // ------------------------------------------------------------------

    /** Nota fiscal, conta e vícios — obra e reforma. */
    private static function faturamento(): array
    {
        return [
            'A [[contratada]] entregará a nota fiscal à [[contratante]] com antecedência mínima de 5 (cinco) dias do vencimento do título, sob pena de o pagamento ser postergado, sem penalidade para a [[contratante]].',
            'As notas fiscais serão emitidas em nome de [[clube_nome]], CNPJ [[clube_cnpj]].',
            'A nota fiscal emitida com incorreção ou em desacordo com a legislação será devolvida, e o prazo de pagamento passará a ser de 10 (dez) dias contados da reapresentação.',
            'Os pagamentos serão feitos exclusivamente por depósito na conta de titularidade da [[contratada]], cujos dados constarão do corpo da nota fiscal, servindo o comprovante como prova de quitação.',
            'Detectados vícios em serviços já pagos, registrados no boletim de medição e não corrigidos no mês da notificação, a [[contratante]] poderá estorná-los na medição seguinte ou exigir o ressarcimento em 5 (cinco) dias.',
        ];
    }

    /** Obrigações da contratada em obra (nível 2, sob o item de abertura). */
    private static function obrigacoesDeObra(): array
    {
        return array_map(fn(string $t) => '>' . $t, [
            'responsabilizar-se pelo fiel cumprimento do objeto, com a melhor técnica, obedecendo às instruções da [[contratante]], ao projeto, às especificações, ao cronograma, às normas da ABNT e às especificações aplicáveis, acatando e facilitando a fiscalização e usando produtos e equipamentos de primeira linha;',
            'utilizar mão de obra qualificada e habilitada, arcando com os salários e todos os custos diretos e indiretos daí decorrentes;',
            'confiar a execução a profissionais idôneos e habilitados e substituir imediatamente, sem ônus para a [[contratante]], qualquer empregado considerado inadequado;',
            'manter os profissionais destacados exclusivamente dedicados a este contrato;',
            'cumprir pontualmente todas as obrigações tributárias, trabalhistas, previdenciárias e acidentárias, inclusive a anotação da CTPS e os adicionais de periculosidade e insalubridade, sendo a única responsável por elas. Em ação judicial contra a [[contratante]], a [[contratada]] integrará a lide, assumirá o polo passivo e depositará em juízo ou pagará o pedido ou a condenação, reembolsando custas, despesas e honorários advocatícios, mesmo após o término do contrato;',
            'não utilizar mão de obra infantil (art. 7º, XXXIII, da Constituição) nem trabalho escravo, forçado ou degradante, e exigir o mesmo de seus fornecedores;',
            'não discriminar, na contratação de colaboradores e fornecedores e na execução do contrato, por raça, classe social, nacionalidade, crença, sexo, orientação sexual, filiação sindical ou partidária, entre outras;',
            'fornecer, treinar, tornar obrigatório, fiscalizar e substituir os equipamentos de proteção individual (NR-6), conforme as leis de segurança e medicina do trabalho, as normas ambientais e as instruções da [[contratante]];',
            'providenciar e custear os treinamentos exigidos para a mão de obra alocada;',
            'adotar as medidas de proteção ambiental, respondendo com exclusividade por danos ao meio ambiente causados por ação ou omissão sua, de seus empregados ou prepostos, e recolher e destinar os resíduos conforme as normas ambientais;',
            'adotar todas as medidas de segurança e, em caso de acidente com pessoas ou bens da [[contratante]] ou de terceiros, tomar as providências necessárias, apurar as causas e apresentar relatório detalhado;',
            'emitir a CAT nas condições e prazos legais e informar imediatamente a [[contratante]] sobre acidentes de trabalho com seus empregados;',
            'cumprir a Lei nº 6.514/1977 e as normas regulamentadoras aplicáveis (como NR-4, NR-6, NR-7, NR-10, NR-15, NR-16 e NR-18), declarando conhecer a Portaria nº 3.214/1978 do Ministério do Trabalho;',
            'fornecer os materiais de aplicação e consumo, as ferramentas manuais, elétricas e especiais e os equipamentos necessários, salvo os que este contrato atribui à [[contratante]];',
            'refazer ou reparar às suas expensas o serviço executado fora das especificações por culpa sua, arcando com todos os custos de reparo, substituição, estadia, transporte e seguro;',
            'em caso de recusa ou mora, a [[contratante]] poderá contratar terceiros para executar os serviços, às custas da [[contratada]], sem prejuízo da indenização cabível e das garantias contratuais;',
            'providenciar antes do início, quando aplicável, a ART registrada no CREA-RJ (Resolução Confea nº 1.025/2009), mantendo-a válida durante o contrato, e fixar a placa de responsabilidade técnica desde a mobilização;',
            'ressarcir os danos causados à [[contratante]], a seus funcionários ou a terceiros por seu pessoal, veículos e equipamentos, inclusive condenações impostas à [[contratante]] por esses danos;',
            'fornecer, sempre que solicitado, as certidões negativas (Dívida Ativa da União, tributos federais, INSS e FGTS) e, mensalmente, os comprovantes de pagamento de salários, INSS, FGTS e ISS dos funcionários do contrato;',
            'manter a [[contratante]] indene de reivindicações, demandas e condenações decorrentes de sua ação ou omissão ou de seus colaboradores, inclusive condenação subsidiária ou solidária trabalhista, previdenciária ou tributária, acrescida de custas, honorários e despesas;',
            'permitir à [[contratante]], a qualquer momento e sem aviso prévio, verificar o cumprimento das obrigações legais e contratuais, inclusive entrevistar empregados e pedir documentos, a serem entregues em 72 (setenta e duas) horas;',
            'garantir a continuidade dos serviços em caso de greve a que tenha dado causa;',
            'comunicar os erros, omissões ou discrepâncias encontrados em desenhos, especificações, listas de materiais e outros documentos técnicos;',
            'apresentar o Relatório Diário de Obra (RDO) todos os dias;',
            'aceitar auditorias internas e externas, sem aviso prévio, de segurança e saúde do trabalho, meio ambiente e obrigações fiscais, sociais e trabalhistas;',
            'informar imediatamente os atrasos e seus motivos e apresentar, em até 3 (três) dias úteis, plano de trabalho para mitigá-los; atraso por culpa sua ou de seus fornecedores não gera ônus para a [[contratante]];',
            'contratar seguro contra acidentes de trabalho e de vida de seus empregados e seguro de responsabilidade civil por danos a terceiros.',
        ]);
    }

    /** Conduta no clube: equipamentos, limpeza, circulação, cadastro. */
    private static function normasDoClube(): array
    {
        return [
            'O transporte, a guarda e o risco dos equipamentos correm por conta da [[contratada]]; a [[contratante]] não responde por sua perda ou desaparecimento.',
            'As despesas de estadia e alimentação dos trabalhadores são da [[contratada]].',
            'Cabem à [[contratada]] o isolamento da área e a limpeza diária ao fim do expediente, sob pena de multa de 2% (dois por cento) do valor global por infração.',
            'Os colaboradores da [[contratada]] só circularão pelo clube por necessidade do trabalho, uniformizados e identificados com crachá, e farão as refeições apenas no local indicado pela [[contratante]].',
            'A [[contratada]] enviará ao Setor de Atendimento a relação dos funcionários que trabalharão no clube, para cadastro e orientação sobre as normas de segurança, e cumprirá a rotina administrativa do local.',
            'O desperdício de material é de responsabilidade da [[contratada]], sob pena de multa de 0,5% (meio por cento) do valor global por infração.',
        ];
    }

    private static function disposicoesGerais(): array
    {
        return [
            'Este contrato, com seus anexos, contém o entendimento integral das PARTES sobre o objeto e substitui as tratativas anteriores, escritas ou verbais.',
            'Alteração, aditivo ou modificação deste contrato só valerá por escrito, assinada pelas PARTES.',
            'Os títulos das cláusulas servem apenas para referência e não limitam o seu alcance.',
            'A disposição considerada inválida ou inexequível não prejudica as demais; as PARTES a substituirão pela que mais se aproxime da intenção original.',
            'A tolerância de uma PARTE quanto ao descumprimento de qualquer disposição não importa renúncia nem novação.',
            'Este contrato obriga as PARTES, seus sucessores e cessionários autorizados.',
            'Este contrato não cria sociedade, representação, joint venture nem relação de emprego entre as PARTES ou seus empregados; a [[contratada]] é independente, e as pessoas por ela contratadas são exclusivamente suas empregadas ou prestadoras.',
        ];
    }

    /** Foro, assinatura eletrônica e o fecho — último bloco de todo modelo. */
    private static function foro(): array
    {
        return [
            'Fica eleito o foro da Comarca de Volta Redonda/RJ para dirimir as dúvidas e pendências oriundas deste contrato, com renúncia expressa a qualquer outro, por mais privilegiado que seja.',
            'As partes reconhecem como válida, para comprovação de autoria e integridade, a assinatura eletrônica deste instrumento, inclusive pelo sistema de assinaturas do clube ou pela plataforma gov.br, nos termos do art. 10, § 2º, da Medida Provisória nº 2.200-2/2001.',
            'E, por estarem assim justos e contratados, as partes firmam o presente instrumento, juntamente com 2 (duas) testemunhas.',
        ];
    }

    /**
     * Atração musical (06/11/2026, contrato 283/26 — DJ no evento do clube).
     * Contratado pessoa física (quem assina e recebe), com o nome artístico em
     * variável. Ajustes em relação ao PDF: a multa saiu do título "Obrigações
     * da contratada e multa" e ficou só na rescisão; o "Aplicação: evento e
     * data" repetia o objeto e saiu; o nome artístico da assinatura (que no PDF
     * divergia do objeto) vem da variável; duas testemunhas, como nos demais.
     */
    private static function atracaoMusical(): array
    {
        return self::model('Atração musical — apresentação em evento (DJ, banda, cantor)', 'musica', [
            'title' => 'Contrato de atração musical',
            'establishment' => 'pet',
            'contractor_term' => 'CONTRATANTE',
            'parties' => [['role' => ContractContent::ROLE_CONTRATADA, 'term' => 'CONTRATADO', 'kind' => 'pf', 'gender' => 'm']],
            'variables' => [
                ['nome_artistico', 'Nome artístico (ex.: DJ Sérgio Mamma e DJ Flavinho)', 'text'],
                ['nome_do_evento', 'Nome do evento', 'text'],
                ['data_do_evento', 'Data da apresentação', 'date_long'],
                ['horarios', 'Horários previstos (ex.: 21h, 23h e 1h30)', 'text'],
                ['valor_global', 'Valor global', 'money'],
                ['data_de_pagamento', 'Data do pagamento', 'date'],
                ['multa_por_descumprimento', 'Multa por descumprimento', 'percent'],
            ],
            'clauses' => [
                self::c('objeto', 'Do objeto e da apresentação', [
                    'O objeto deste contrato é a apresentação de [[nome_artistico]] no evento denominado [[nome_do_evento]], promovido pela [[contratante]].',
                    'A apresentação será realizada em [[data_do_evento]], nos horários previstos e previamente acordados entre as partes: [[horarios]].',
                ]),
                self::c('execucao', 'Da execução do serviço', [
                    'Os serviços serão executados em conformidade com o projeto do evento elaborado pela [[contratante]].',
                ]),
                self::c('obrigacoes', 'Das obrigações do contratado', [
                    'Correm por conta do [[contratada]] todas as obrigações trabalhistas e previdenciárias de seus colaboradores, bem como quaisquer outras obrigações legais, durante a vigência deste contrato.',
                    'Toda a estrutura e o equipamento serão transportados por conta e risco do [[contratada]] até o local do evento.',
                ]),
                self::c('contratante', 'Das obrigações da contratante', [
                    'A [[contratante]] se obriga a zelar pela guarda do material do [[contratada]] durante o período de execução do evento.',
                ]),
                self::c('condicoes', 'Das condições contratadas', [
                    'O valor global dos serviços é de [[valor_global]].',
                    'O pagamento será feito em [[data_de_pagamento]], após a apresentação.',
                    'A nota fiscal será emitida em nome de [[clube_nome]], CNPJ [[clube_cnpj]], Inscrição Estadual 80540590 e Inscrição Municipal 002976056, e trará no corpo o número deste contrato ([[numero]]), o que agiliza o processamento do pagamento. Telefone do Setor Financeiro: (24) 2102-2759.',
                ]),
                self::c('rescisao', 'Da rescisão', [
                    'A parte que infringir qualquer das obrigações deste contrato pagará à parte inocente multa de [[multa_por_descumprimento]] sobre o valor global do contrato, além das despesas por perdas e danos.',
                ]),
                self::c('foro', 'Do foro', self::foro()),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Montagem
    // ------------------------------------------------------------------

    private static function c(string $key, string $title, array $items): array
    {
        return ['key' => $key, 'title' => $title, 'items' => $items];
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{name: string, content: array<string, mixed>}
     */
    private static function model(string $name, string $prefix, array $spec): array
    {
        $uids = [];
        $clausulas = [];

        foreach (array_values($spec['clauses']) as $ci => $c) {
            $cuid = $prefix . 'c' . str_pad((string) ($ci + 1), 2, '0', STR_PAD_LEFT);
            $uids[$c['key']] = $cuid;
            $itens = [];

            foreach (array_values($c['items']) as $ii => $html) {
                $nivel = str_starts_with($html, '>') ? 2 : 1;
                $iuid = $cuid . 'i' . str_pad((string) ($ii + 1), 2, '0', STR_PAD_LEFT);
                $uids[$c['key'] . '.' . ($ii + 1)] = $iuid;
                $itens[] = ['uid' => $iuid, 'level' => $nivel, 'html' => $nivel === 2 ? substr($html, 1) : $html];
            }

            $clausulas[] = ['uid' => $cuid, 'title' => $c['title'], 'items' => $itens];
        }

        $resolve = fn(string $html) => (string) preg_replace_callback(
            '/\{ref:([a-z]+(?:\.\d+)?)\}/',
            fn(array $m) => '[[ref:' . ($uids[$m[1]] ?? throw new LogicException("Modelo {$prefix}: remissão {$m[1]} não existe.")) . ']]',
            $html,
        );

        foreach ($clausulas as &$clausula) {
            foreach ($clausula['items'] as &$item) {
                $item['html'] = $resolve($item['html']);
            }
        }
        unset($clausula, $item);

        $partes = $spec['parties'] ?? [['role' => ContractContent::ROLE_CONTRATADA, 'term' => 'CONTRATADA', 'kind' => 'pj']];

        return [
            'name' => $name,
            'content' => ContractContent::normalize([
                'title' => $spec['title'],
                'establishment' => $spec['establishment'],
                'contractor_term' => $spec['contractor_term'] ?? 'CONTRATANTE',
                'parties' => $partes,
                'variables' => array_map(fn(array $v) => ['key' => $v[0], 'label' => $v[1], 'type' => $v[2], 'value' => ''], $spec['variables']),
                'considerations' => $spec['considerations'] ?? [],
                'clauses' => $clausulas,
                'signers' => $spec['signers'] ?? [
                    ['party' => ContractContent::SIGNER_CONTRATADA, 'source' => 'person'],
                    ['party' => ContractContent::SIGNER_TESTEMUNHA, 'source' => 'person'],
                    ['party' => ContractContent::SIGNER_TESTEMUNHA, 'source' => 'person'],
                ],
            ]),
        ];
    }
}
