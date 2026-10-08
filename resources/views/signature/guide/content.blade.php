{{--
    Guia do usuário do módulo de assinatura — a página que aparece em
    Assinaturas → Guia e a fonte do PDF que ela oferece para baixar.

    É um documento HTML completo, com o CSS dele, e não um pedaço do layout do
    painel: entra na tela dentro de um iframe (signature/guide/index) e vira
    PDF pelo dompdf. Por causa do dompdf o CSS é o de sempre — tabelas e
    blocos, sem flex/grid.

    Fica INTEIRO dentro de um bloco verbatim: o texto ensina a escrever
    marcadores com colchetes e chaves, e o Blade tentaria interpretá-los. A
    única coisa dinâmica é o endereço da tela do tablet — o GuideController
    troca %%ENDERECO_DO_TABLET%% pelo caminho da rota depois de renderizar.
--}}
@verbatim
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Assinatura de documentos no Lara — Guia do dia a dia</title>

<style>
    @page { margin: 22mm 18mm 20mm 18mm; }

    body { font-family: "Segoe UI", "DejaVu Sans", Arial, sans-serif; font-size: 10.5pt; line-height: 1.5;
           color: #241a1d; margin: 0; background: #ffffff; }
    .page { max-width: 820px; margin: 0 auto; padding: 0 22px 60px; }
    /* No PDF a margem é a do papel (@page); a da tela sobraria em dobro. */
    @media print { .page { max-width: none; padding: 0; } }

    h1 { font-size: 25pt; line-height: 1.15; margin: 0 0 8px; color: #8a1538; }
    h2 { font-size: 17pt; line-height: 1.2; margin: 0 0 12px; padding-bottom: 7px; color: #8a1538;
         border-bottom: 2px solid #8a1538; page-break-after: avoid; }
    h3 { font-size: 12.5pt; margin: 22px 0 8px; color: #241a1d; page-break-after: avoid; }
    p { margin: 0 0 10px; }
    ul, ol { margin: 0 0 10px 22px; padding: 0; }
    li { margin-bottom: 5px; }
    a { color: #8a1538; }
    code, .mk { font-family: "Consolas", "DejaVu Sans Mono", monospace; font-size: 10pt; background: #fde9a8;
                padding: 1px 4px; border-radius: 3px; white-space: nowrap; }
    .section { page-break-before: always; padding-top: 6px; }
    .lead { font-size: 12.5pt; color: #5a4a4e; margin-bottom: 18px; }
    .small { font-size: 9.5pt; color: #6d6062; }

    /* Capa */
    .cover { padding: 26px 0 10px; }
    .cover .tag { font-size: 10pt; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: #6d6062; }
    .cover .bar { width: 70px; height: 6px; background: #8a1538; margin: 18px 0 22px; }

    /* Sumário */
    table.toc { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table.toc td { padding: 7px 4px; border-bottom: 1px solid #e3ddde; vertical-align: top; }
    table.toc td.n { width: 34px; font-weight: bold; color: #8a1538; font-size: 13pt; }
    table.toc td.who { width: 170px; text-align: right; color: #6d6062; font-size: 9.5pt; }
    table.toc a { text-decoration: none; color: #241a1d; font-weight: bold; }

    /* Passos numerados */
    table.steps { width: 100%; border-collapse: collapse; margin: 6px 0 14px; }
    table.steps td { vertical-align: top; padding: 0 0 13px; }
    table.steps td.n { width: 42px; }
    .num { display: block; width: 28px; padding: 4px 0 5px; line-height: 1.3; border-radius: 14px;
           background: #8a1538; color: #ffffff; text-align: center; font-weight: bold; font-size: 11pt; }
    table.steps .t { font-weight: bold; }

    /* Avisos */
    .box { border-radius: 8px; padding: 11px 14px; margin: 12px 0 14px; page-break-inside: avoid; }
    .box p:last-child, .box ul:last-child { margin-bottom: 0; }
    .tip { background: #eef6f0; border-left: 5px solid #147a45; }
    .warn { background: #fbefd8; border-left: 5px solid #935700; }
    .stop { background: #fce4e4; border-left: 5px solid #c22b2b; }
    .box b.h { display: block; margin-bottom: 3px; }

    /* "Botão" e "menu" como aparecem na tela */
    .btn { background: #8a1538; color: #ffffff; font-weight: bold; font-size: 9.5pt;
           padding: 1px 9px 2px; border-radius: 9px; white-space: nowrap; }
    .btn2 { background: #eeeaea; color: #241a1d; font-weight: bold; font-size: 9.5pt;
            padding: 1px 9px 2px; border-radius: 9px; white-space: nowrap; }
    .menu { font-weight: bold; color: #8a1538; white-space: nowrap; }

    /* Folha do Word */
    .word { border: 1px solid #d0c7c9; background: #fffefb; padding: 16px 20px; margin: 10px 0 14px;
            font-family: "Times New Roman", "DejaVu Serif", serif; font-size: 10.5pt; line-height: 1.5;
            page-break-inside: avoid; }
    .word .cap { font-family: "Segoe UI", "DejaVu Sans", Arial, sans-serif; font-size: 8.5pt; font-weight: bold;
                 letter-spacing: 1px; text-transform: uppercase; color: #6d6062; margin-bottom: 8px; }
    .word p { margin: 0 0 7px; text-align: justify; }
    .word .ttl { text-align: center; font-weight: bold; }
    .word table { width: 100%; border-collapse: collapse; }
    .word td { width: 50%; padding: 8px 6px 0; vertical-align: top; }

    /* Tabelas de referência */
    table.ref { width: 100%; border-collapse: collapse; margin: 8px 0 14px; font-size: 9.5pt; }
    table.ref th { background: #f2ecea; text-align: left; padding: 5px 8px; border: 1px solid #d8cbc9; }
    table.ref td { padding: 5px 8px; border: 1px solid #d8cbc9; vertical-align: top; }

    /* Título e a tabela dele ficam na mesma página. */
    .keep { page-break-inside: avoid; }

    /* Caminho do documento */
    table.flow { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin: 12px 0 6px; }
    table.flow td { background: #f6e3e9; border-radius: 8px; padding: 9px 6px; text-align: center; font-size: 9.5pt;
                    vertical-align: top; width: 20%; }
    table.flow b { display: block; color: #8a1538; font-size: 10.5pt; margin-bottom: 2px; }

    /* Telas do tablet */
    table.tablet { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 8px 0 12px; }
    table.tablet td { border: 2px solid #d0c7c9; border-radius: 10px; padding: 9px 8px; vertical-align: top;
                      width: 25%; font-size: 9.5pt; }
    table.tablet b { display: block; color: #8a1538; margin-bottom: 3px; font-size: 10pt; }
    table.tablet .opt { color: #935700; font-size: 8.5pt; font-weight: bold; text-transform: uppercase; }

    .foot { margin-top: 14px; padding-top: 8px; border-top: 1px solid #e3ddde; }
</style>
</head>
<body>
<div class="page">

<!-- ====================================================== CAPA -->
<div class="cover">
    <div class="tag">Lara · Clube dos Funcionários da CSN</div>
    <div class="bar"></div>
    <h1>Assinatura de documentos</h1>
    <p class="lead">Guia do dia a dia: como preparar um documento no Word, cadastrar o modelo, emitir o documento
        no balcão, acompanhar a assinatura no tablet — ou pelo <b>gov.br</b>, para quem não vem ao clube — e
        encontrar o documento assinado depois.</p>

    <table class="flow">
        <tr>
            <td><b>1. Word</b>Escrever o texto e marcar os campos</td>
            <td><b>2. Modelo</b>Enviar o Word e ajustar os campos</td>
            <td><b>3. Documento</b>Preencher os dados e congelar</td>
            <td><b>4. Tablet ou gov.br</b>A pessoa lê, confirma e assina</td>
            <td><b>5. Arquivo</b>PDF assinado, com comprovante</td>
        </tr>
    </table>
    <p class="small">Os passos 1 e 2 são feitos uma vez por tipo de documento. Os passos 3 a 5 se repetem a cada
        atendimento.</p>

    <table class="toc">
        <tr><td class="n">1</td><td><a href="#visao">Como funciona, em uma página</a></td><td class="who">todos</td></tr>
        <tr><td class="n">2</td><td><a href="#word">Preparar o documento no Word</a></td><td class="who">quem escreve os modelos</td></tr>
        <tr><td class="n">3</td><td><a href="#modelo">Cadastrar o modelo</a></td><td class="who">quem escreve os modelos</td></tr>
        <tr><td class="n">4</td><td><a href="#campos">Os tipos de campo e as perguntas no tablet</a></td><td class="who">quem escreve os modelos</td></tr>
        <tr><td class="n">5</td><td><a href="#revisar">Revisar um modelo e ajustar o papel timbrado</a></td><td class="who">quem escreve os modelos</td></tr>
        <tr><td class="n">6</td><td><a href="#documento">Emitir um documento no balcão</a></td><td class="who">atendimento</td></tr>
        <tr><td class="n">7</td><td><a href="#tablet">O que a pessoa vê no tablet</a></td><td class="who">atendimento</td></tr>
        <tr><td class="n">8</td><td><a href="#depois">Depois de assinado: onde está o documento</a></td><td class="who">todos</td></tr>
        <tr><td class="n">9</td><td><a href="#govbr">Assinatura pelo gov.br (quem não vem ao balcão)</a></td><td class="who">atendimento</td></tr>
        <tr><td class="n">10</td><td><a href="#problemas">Problemas comuns</a></td><td class="who">todos</td></tr>
        <tr><td class="n">11</td><td><a href="#cola">Cola rápida dos marcadores</a></td><td class="who">quem escreve os modelos</td></tr>
    </table>
</div>

<!-- ====================================================== 1 -->
<div class="section" id="visao">
    <h2>1. Como funciona, em uma página</h2>

    <p>O sistema trabalha com duas coisas diferentes, e vale não confundir:</p>
    <table class="ref">
        <tr><th style="width:24%">O que é</th><th>Para que serve</th><th style="width:26%">Quem mexe</th></tr>
        <tr>
            <td><b>Modelo</b></td>
            <td>O texto-padrão de um tipo de documento (termo, ficha, contrato). É cadastrado uma vez.</td>
            <td>Gerência, jurídico</td>
        </tr>
        <tr>
            <td><b>Documento</b></td>
            <td>Um modelo preenchido para uma pessoa. É o que vai para o tablet e é assinado.</td>
            <td>Atendimento</td>
        </tr>
    </table>

    <h3>Onde fica cada coisa no menu</h3>
    <ul>
        <li><span class="menu">Assinaturas → Modelos</span> — cadastrar e revisar modelos; ajustar o cabeçalho e o rodapé.</li>
        <li><span class="menu">Assinaturas → Documentos</span> — emitir um documento, acompanhar a assinatura e baixar o PDF.
            Na página de cada documento, a aba <span class="menu">Assinatura gov.br</span> é por onde ele é
            assinado à distância (seção 9).</li>
        <li>O <b>tablet do balcão</b> fica aberto na tela de assinatura (endereço do sistema terminado em
            <code>%%ENDERECO_DO_TABLET%%</code>) e não precisa de login.</li>
        <li>Um item do menu que não aparece para você depende de permissão: peça o acesso à TI.</li>
    </ul>

    <h3>Duas formas de assinar</h3>
    <table class="ref">
        <tr><th style="width:24%">Forma</th><th>Quando usar</th></tr>
        <tr>
            <td><b>Tablet do balcão</b></td>
            <td>A pessoa está no clube. Ela lê no tablet, confirma a identidade e assina com o dedo. É o caminho de
                todo dia (seções 6 e 7).</td>
        </tr>
        <tr>
            <td><b>gov.br</b></td>
            <td>A pessoa não pode vir. Ela recebe o PDF por e-mail, assina com a própria conta gov.br e devolve o
                arquivo; o sistema confere a assinatura (seção 9).</td>
        </tr>
    </table>
    <p class="small">Cada documento é assinado por <b>uma</b> das duas formas: todos no tablet, ou todos pelo
        gov.br. A seção 9 explica por quê.</p>

    <h3>As situações de um documento</h3>
    <table class="ref">
        <tr><th style="width:26%">Situação</th><th>O que significa</th></tr>
        <tr><td><b>Rascunho</b></td><td>Ainda pode ser editado. Não aparece no tablet.</td></tr>
        <tr><td><b>Aguardando assinatura</b></td><td>Foi congelado: o texto e os dados não mudam mais. Pode ser liberado para o tablet.</td></tr>
        <tr><td><b>Assinado</b></td><td>Todos assinaram. O sistema está montando o PDF final (leva alguns segundos) — ou
            esperando um <b>anexo obrigatório</b> que ainda não foi enviado; o quadro Anexos diz qual.</td></tr>
        <tr><td><b>Finalizado</b></td><td>O PDF assinado está pronto para baixar.</td></tr>
        <tr><td><b>Recusado, Cancelado ou Expirado</b></td><td>Encerrado sem assinatura: a pessoa recusou no tablet, o
            atendente cancelou, ou ninguém assinou dentro do prazo (por padrão, 24 horas no balcão e 7 dias pelo
            gov.br).</td></tr>
    </table>

    <div class="box warn">
        <b>A regra mais importante:</b> depois de <b>congelado</b>, o documento não pode ser corrigido — só cancelado
        e emitido de novo. Confira o texto com a pessoa <b>antes</b> de congelar.
    </div>
</div>

<!-- ====================================================== 2 -->
<div class="section" id="word">
    <h2>2. Preparar o documento no Word</h2>
    <p class="lead">Você escreve o documento no Word, como sempre, e marca com colchetes duplos os lugares onde
        entra uma informação que muda a cada atendimento.</p>

    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td><span class="t">Abra o documento no Word.</span><br>
                Se ele só existe em PDF ou no formato antigo <code>.doc</code>, abra-o no Word e use
                <b>Arquivo → Salvar como → Documento do Word (.docx)</b>. O sistema só aceita <code>.docx</code>.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td><span class="t">Onde entra um dado, escreva o nome do campo entre colchetes duplos.</span><br>
                Por exemplo <span class="mk">[[Data do evento]]</span> ou <span class="mk">[[Valor total]]</span>.
                O que você escrever ali é o nome que o atendente vai ver na hora de preencher — escolha um nome claro.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">3</span></td>
            <td><span class="t">Onde a pessoa assina, escreva <span class="mk">[[assinatura]]</span> numa linha só dela.</span><br>
                Não escreva o nome nem o CPF de quem assina: eles entram sozinhos ali, embaixo da linha.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">4</span></td>
            <td><span class="t">Salve como .docx.</span> Está pronto para enviar ao sistema (capítulo 3).</td>
        </tr>
    </table>

    <div class="word">
        <div class="cap">Exemplo — termo de uma pessoa só</div>
        <p class="ttl">TERMO DE RESPONSABILIDADE</p>
        <p>Declaro estar ciente das condições de uso do espaço <span class="mk">[[Espaço utilizado]]</span> do Clube
            dos Funcionários da CSN, pelo período de <span class="mk">[[Validade do termo]]</span>.</p>
        <p>Telefone para contato: <span class="mk">[[Telefone]]</span></p>
        <p>Volta Redonda, <span class="mk">[[Data da assinatura]]</span>.</p>
        <p><span class="mk">[[assinatura]]</span></p>
    </div>

    <h3>Documento com mais de uma parte (contrato)</h3>
    <p>Quando duas partes assinam, cada uma no seu lugar, diga quem assina em cada ponto:
        <span class="mk">[[assinatura: Contratante]]</span> e <span class="mk">[[assinatura: Contratado]]</span>.
        O nome depois dos dois-pontos sai impresso embaixo do nome de quem assinar.</p>
    <p>Para as duas assinaturas ficarem <b>lado a lado</b>, escreva os dois marcadores em linhas seguidas, ou
        coloque-os nas duas células de uma tabela do Word:</p>

    <div class="word">
        <div class="cap">Exemplo — fim de um contrato</div>
        <p>E, por estarem justas e contratadas, as partes assinam o presente instrumento.</p>
        <table>
            <tr>
                <td><span class="mk">[[assinatura: Contratante]]</span></td>
                <td><span class="mk">[[assinatura: Contratado]]</span></td>
            </tr>
        </table>
    </div>

    <div class="keep">
    <h3>O que o sistema aproveita do seu Word</h3>
    <table class="ref">
        <tr><th style="width:50%">Entra no documento</th><th>Não entra</th></tr>
        <tr>
            <td>Parágrafos e títulos (estilos Título 1, 2, 3)<br>
                Negrito, itálico e sublinhado<br>
                Marcadores (bolinhas) e tabelas<br>
                Numeração automática das cláusulas</td>
            <td>Fonte, cor, tamanho e alinhamento do texto<br>
                Cabeçalho e rodapé do Word<br>
                Imagens e caixas de texto<br>
                Células mescladas de tabela</td>
        </tr>
    </table>
    </div>
    <p>A aparência é a mesma em todos os documentos: a fonte padrão do sistema, com o cabeçalho e o rodapé da
        empresa (capítulo 5). O logotipo não precisa estar no Word.</p>

    <div class="box tip">
        <b class="h">Dicas que evitam retrabalho</b>
        <ul>
            <li>Use sempre <b>dois</b> colchetes de cada lado. <code>[Data]</code> ou <code>{{Data}}</code> não são reconhecidos.</li>
            <li>O mesmo campo pode aparecer várias vezes no texto: escreva o nome igual e ele é preenchido uma vez só.</li>
            <li>Não escreva o título do documento no topo do Word: o título é impresso automaticamente.</li>
            <li>Imagens do Word não entram no modelo. Para um documento com imagens, envie-o pronto em PDF
                (capítulo 6).</li>
            <li>Se o campo é um valor em dinheiro, não escreva <code>R$</code> antes do marcador — o sistema já escreve
                (veja "Valor em reais" no capítulo 4).</li>
            <li>Se o documento passou por revisão com "controlar alterações", aceite as alterações antes de enviar.</li>
        </ul>
    </div>
</div>

<!-- ====================================================== 3 -->
<div class="section" id="modelo">
    <h2>3. Cadastrar o modelo</h2>
    <p class="lead">Com o .docx pronto, o cadastro leva poucos minutos. O sistema lê o arquivo e monta a lista de
        campos sozinho.</p>

    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td>Abra <span class="menu">Assinaturas → Modelos</span> e clique em <span class="btn">Novo modelo</span>.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td><span class="t">Dê um nome e uma descrição.</span><br>
                O <b>nome</b> é o título que sai no documento e o nome da pasta onde os assinados são guardados.
                A <b>descrição</b> ajuda o atendente a escolher o modelo certo ("Para quem usa piscina ou academia").</td>
        </tr>
        <tr>
            <td class="n"><span class="num">3</span></td>
            <td><span class="t">Clique em <span class="btn">Escolher arquivo .docx</span> e selecione o seu Word.</span><br>
                Em alguns segundos aparece a mensagem com quantos campos foram encontrados, e logo abaixo a prévia
                <b>Como o documento vai ficar</b>. Na prévia, cada campo aparece destacado: em amarelo o que o
                atendente preenche, em azul o que a pessoa responde no tablet, em cinza o que o sistema preenche.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">4</span></td>
            <td><span class="t">Confira a lista "Campos do documento".</span><br>
                Para cada campo, veja o <b>Tipo</b> (o sistema sugere um pelo nome — troque se estiver errado),
                marque <b>Obrigatório</b> se não puder ficar em branco e, se quiser, escreva a <b>Pergunta no
                tablet</b>. Quem responde cada campo não se decide aqui: é no documento. O capítulo 4 explica.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">5</span></td>
            <td><span class="t">Se o documento tem partes, confira "Quem assina".</span><br>
                As partes do Word (Contratante, Contratado) já chegam listadas. Ajuste o nome se precisar.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">6</span></td>
            <td><span class="t">Escolha as regras de assinatura</span> (tabela abaixo).</td>
        </tr>
        <tr>
            <td class="n"><span class="num">7</span></td>
            <td>Clique em <span class="btn">Criar modelo</span>. Ele já fica disponível para o atendimento.</td>
        </tr>
    </table>

    <div class="keep">
    <h3>As regras de assinatura</h3>
    <table class="ref">
        <tr><th style="width:30%">Opção</th><th>O que faz</th></tr>
        <tr>
            <td><b>Conferência de identidade</b></td>
            <td>O que a pessoa digita no tablet antes de assinar: os <b>quatro primeiros dígitos do CPF</b> (o
                normal), o <b>CPF completo</b> (para contratos) ou o <b>código enviado por e-mail</b> — o sistema
                manda um código de 6 números ao e-mail do signatário, e a pessoa o digita no tablet. Nessa última,
                todo signatário precisa ter e-mail para o documento ser congelado.</td>
        </tr>
        <tr>
            <td><b>Capturar foto na confirmação</b></td>
            <td>O tablet tira uma foto de quem assina, como comprovante. A pessoa é avisada antes.</td>
        </tr>
        <tr>
            <td><b>Visto em todas as páginas</b></td>
            <td>Depois de assinar, a pessoa faz a rubrica no tablet, e ela é aplicada ao pé de cada página do
                documento. Use nos contratos.</td>
        </tr>
        <tr>
            <td><b>Prazo de guarda (meses)</b></td>
            <td>Por quanto tempo o documento deve ser guardado. É só um registro: nada é apagado sozinho.</td>
        </tr>
    </table>
    </div>

    <div class="box warn">
        <b class="h">Evite "Sem conferência" por enquanto</b>
        A opção "Sem conferência" está com um problema conhecido: documentos de modelos configurados assim podem
        não concluir a assinatura no tablet. Use os quatro dígitos, o CPF completo ou o código por e-mail.
    </div>

    <div class="box tip">
        <b class="h">Se a prévia não ficou como você esperava</b>
        Corrija no Word e clique de novo em <span class="btn">Escolher arquivo .docx</span>. Os ajustes que você já
        fez nos campos (tipo, pergunta, opções) são mantidos para os campos que continuam existindo. Nada é gravado
        até você clicar em <b>Criar modelo</b>.
    </div>
</div>

<!-- ====================================================== 4 -->
<div class="section" id="campos">
    <h2>4. Os tipos de campo e as perguntas no tablet</h2>

    <div class="keep">
    <h3>Quem preenche cada campo</h3>
    <table class="ref">
        <tr><th style="width:30%">Quem</th><th>Como configurar</th><th style="width:30%">Quando usar</th></tr>
        <tr>
            <td><b>O atendente</b>, ao preparar o documento</td>
            <td>É o padrão: no documento, preencha o campo.</td>
            <td>Dados do atendimento: espaço, data do evento, valor.</td>
        </tr>
        <tr>
            <td><b>Quem assina</b>, no tablet</td>
            <td>No <b>documento</b>, marque <b>Perguntar ao signatário</b> ao lado do campo. No modelo, escreva, se
                quiser, a <b>Pergunta no tablet</b> (por exemplo "Qual é o seu telefone para contato?"); em branco,
                a pergunta é o nome do campo. Documento com pergunta ao signatário só é assinado no tablet — para
                o gov.br, preencha todos os campos.</td>
            <td>Dados que só a pessoa sabe ou que ela precisa declarar: telefone, e-mail, opções de uso.</td>
        </tr>
        <tr>
            <td><b>O sistema</b>, sozinho</td>
            <td>Escolha o tipo <b>Data da assinatura — automática</b>.</td>
            <td>A data do documento.</td>
        </tr>
    </table>
    </div>

    <h3>Os tipos</h3>
    <table class="ref">
        <tr><th style="width:27%">Tipo</th><th style="width:36%">O que a pessoa digita</th><th>Como sai no documento</th></tr>
        <tr><td>Texto</td><td>Qualquer texto curto</td><td>Como foi digitado</td></tr>
        <tr><td>Texto longo</td><td>Várias linhas</td><td>Como foi digitado</td></tr>
        <tr><td>Número</td><td>150 ou 2,5</td><td>150 ou 2,5</td></tr>
        <tr><td>Valor em reais (R$)</td><td>1.500,00</td><td>R$ 1.500,00</td></tr>
        <tr><td>CPF</td><td>Com ou sem pontos; o sistema confere se é válido</td><td>123.456.789-09</td></tr>
        <tr><td>CNPJ</td><td>Com ou sem pontos; o sistema confere se é válido</td><td>11.222.333/0001-81</td></tr>
        <tr><td>E-mail</td><td>Um endereço válido</td><td>maria@exemplo.com</td></tr>
        <tr><td>Telefone</td><td>DDD e número</td><td>(24) 99999-1234</td></tr>
        <tr><td>CEP</td><td>8 números</td><td>27255-125</td></tr>
        <tr><td>Data abreviada</td><td>Escolhe no calendário</td><td>03/10/2026</td></tr>
        <tr><td>Data por extenso</td><td>Escolhe no calendário</td><td>3 de outubro de 2026</td></tr>
        <tr><td>Hora</td><td>14:30</td><td>14:30</td></tr>
        <tr><td>Opção única</td><td>Escolhe <b>uma</b> das opções que você listou</td><td>A opção escolhida</td></tr>
        <tr><td>Múltipla escolha</td><td>Marca <b>várias</b> das opções que você listou</td><td>Piscina, Academia e Quadra</td></tr>
        <tr><td>Sim ou não</td><td>Sim / Não</td><td>Sim ou Não</td></tr>
        <tr><td>Data da assinatura — automática</td><td>Ninguém digita</td><td>A data do dia, abreviada ou por extenso</td></tr>
    </table>

    <h3>Campos de escolha: como informar as opções</h3>
    <p>Ao escolher <b>Opção única</b> ou <b>Múltipla escolha</b>, aparece a caixa de opções. Escreva cada opção
        numa linha. São necessárias pelo menos duas.</p>

    <div class="box warn">
        <b class="h">Atenção ao "Valor em reais"</b>
        Esse tipo já escreve o <code>R$</code>. Se o texto do Word tiver <code>R$ [[Valor]]</code>, o documento sai
        com "R$ R$ 1.500,00". Deixe só <span class="mk">[[Valor]]</span> no texto.
    </div>

    <div class="box tip">
        <b class="h">Sobre a data automática</b>
        A data é a do dia em que a pessoa abre o documento no tablet para assinar, e não a do dia em que o atendente
        o preparou. Em documento com mais de um signatário, vale a data do primeiro que assinar.
    </div>
</div>

<!-- ====================================================== 5 -->
<div class="section" id="revisar">
    <h2>5. Revisar um modelo e ajustar o papel timbrado</h2>

    <h3>Revisar o texto de um modelo</h3>
    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td>Em <span class="menu">Assinaturas → Modelos</span>, abra o modelo e clique em <span class="btn">Revisar</span>.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td>Envie o Word corrigido em <span class="btn">Escolher arquivo .docx</span> — ou ajuste só os campos e as regras.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">3</span></td>
            <td>Clique em <span class="btn">Salvar como versão 2</span> (o número sobe a cada revisão).</td>
        </tr>
    </table>

    <div class="box tip">
        <b class="h">Revisar nunca altera o que já foi assinado</b>
        Cada revisão cria uma <b>versão nova</b>. Os documentos já emitidos continuam com o texto da versão em que
        foram gerados; os próximos usam a nova. O histórico de versões fica na página do modelo.
    </div>

    <p>Um modelo que já gerou documentos não é apagado: ao excluí-lo, ele fica <b>inativo</b>.</p>

    <h3>Cabeçalho e rodapé da empresa</h3>
    <p>O papel timbrado é <b>um só</b> para todos os documentos.</p>
    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td>Em <span class="menu">Assinaturas → Modelos</span>, clique em <span class="btn2">Cabeçalho e rodapé</span>.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td><span class="t">Envie a imagem do cabeçalho e a do rodapé</span> (PNG ou JPG, até 2 MB cada).<br>
                Informe a altura em milímetros e a posição (esquerda, centro ou direita). Para uma arte que vai de
                uma borda à outra da folha, marque <b>Ocupar a largura inteira do papel</b>.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">3</span></td>
            <td><span class="t">Se quiser, preencha o "Texto do rodapé"</span> — uma linha com razão social, CNPJ e endereço.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">4</span></td>
            <td>Clique em <span class="btn">Salvar papel timbrado</span> e depois em <b>Ver exemplo em PDF</b> para conferir.</td>
        </tr>
    </table>
    <p>O código de validação continua no rodapé de todas as páginas. A mudança vale para os documentos congelados
        <b>a partir dali</b>; os anteriores mantêm o cabeçalho que tinham.</p>
</div>

<!-- ====================================================== 6 -->
<div class="section" id="documento">
    <h2>6. Emitir um documento no balcão</h2>
    <p class="lead">Este é o passo a passo de cada atendimento.</p>

    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td>Abra <span class="menu">Assinaturas → Documentos</span>, clique em <span class="btn">Novo documento</span>
                e escolha o modelo.<br>
                A tela é dividida em passos — <b>1. Documento</b>, <b>2. Signatários</b>, <b>3. Dados do
                documento</b>, <b>4. Anexos</b> —, um de cada vez. Confira o título e clique em
                <span class="btn">Continuar</span>; <span class="btn2">Voltar</span> e os nomes dos passos, no alto,
                levam de volta a um passo já visto.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td><span class="t">Informe quem vai assinar.</span><br>
                Cada pessoa tem um cartão — "Signatário 1", "Signatário 2". Se o modelo tem partes, comece por
                <b>Assina como</b>: a parte escolhida entra no título do cartão ("Signatário 1 - Contratante").<br>
                Digite o nome, o título ou o CPF em <b>Buscar associado</b> e clique no resultado: nome, CPF, e-mail e
                telefone são preenchidos. Pelo <b>título</b> aparecem o titular e os dependentes. Quem não é
                associado é digitado à mão. O <b>e-mail</b> é o que permite à pessoa receber a via assinada.<br>
                Para mais gente (uma testemunha, um responsável legal), use <b>+ Acrescentar signatário</b> — até
                cinco.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">3</span></td>
            <td><span class="t">Preencha os "Dados do documento".</span><br>
                Aparecem todos os campos do modelo; os com <b>*</b> são obrigatórios. Para que a própria pessoa
                responda um campo no tablet, marque <b>Perguntar ao signatário</b> ao lado dele: o campo some e vira
                pergunta. Se o documento for para o <b>gov.br</b>, não marque nenhum — lá não há perguntas. Um aviso
                logo abaixo mostra o que o sistema preenche sozinho.<br>
                Quase sempre o nome, o CPF e o contato pedidos são os de quem assina: clique em
                <span class="btn2">Preencher com os dados dos signatários</span> e confira os campos destacados.
                O que não for preenchido sozinho tem, embaixo, a lista <b>Usar dados do signatário…</b> — escolha
                ali o dado e de quem.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">4</span></td>
            <td>Clique em <span class="btn">Criar rascunho</span>.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">5</span></td>
            <td><span class="t">Confira o texto com a pessoa.</span><br>
                A página do documento mostra o texto já preenchido. Se algo estiver errado, clique em
                <span class="btn2">Editar</span> e corrija.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">6</span></td>
            <td><span class="t">Clique em <span class="btn">Congelar e liberar</span> e confirme.</span><br>
                A partir daqui o documento não muda mais. A situação passa a "Aguardando assinatura".</td>
        </tr>
        <tr>
            <td class="n"><span class="num">7</span></td>
            <td><span class="t">No quadro "Assinatura no tablet", clique em <span class="btn">Liberar para assinatura</span>.</span><br>
                Aparece um QR Code com contagem regressiva (por padrão, 5 minutos). Peça para a pessoa apontar a
                câmera do tablet para ele.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">8</span></td>
            <td><span class="t">Acompanhe pela sua tela.</span><br>
                Ela mostra, sozinha, em que etapa a pessoa está: tablet conectado, documento visualizado, identidade
                confirmada, assinado. Quando termina, a página se atualiza.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">9</span></td>
            <td><span class="t">Se houver outro signatário,</span> clique de novo em <span class="btn">Liberar para
                assinatura</span>: é um QR Code por pessoa. <b>A ordem é livre</b> — com mais de uma pessoa
                faltando, escolha em <b>Quem vai assinar agora</b> quem está no balcão.</td>
        </tr>
    </table>

    <div class="keep">
    <h3>Situações do balcão</h3>
    <table class="ref">
        <tr><th style="width:38%">Aconteceu</th><th>O que fazer</th></tr>
        <tr><td>O QR Code expirou antes de a pessoa ler</td><td>Clique em <span class="btn2">Gerar outro código</span>.</td></tr>
        <tr><td>Liberei para a pessoa errada, ou ela desistiu</td><td>Clique em <b>Cancelar liberação</b>. O documento continua valendo; libere de novo quando for a hora.</td></tr>
        <tr><td>A pessoa demorou e o tablet voltou à tela inicial</td><td>A sessão do tablet dura 15 minutos por padrão. Gere outro código e recomece.</td></tr>
        <tr><td>Percebi um erro depois de congelar</td><td>Clique em <b>Cancelar</b> no topo da página e emita um documento novo.</td></tr>
        <tr><td>O tablet não tem câmera funcionando</td><td>Se aparecer na sua tela "Sem câmera no tablet? Dite este código", dite o código de 8 caracteres; no tablet, a pessoa toca em <b>Digitar código</b>.</td></tr>
        <tr><td>O sistema não deixa congelar</td><td>Leia a mensagem: falta um campo obrigatório, um signatário, ou alguém para assinar por uma das partes.</td></tr>
        <tr><td>Todos assinaram, mas o documento não conclui</td><td>Falta um anexo obrigatório: o quadro <b>Anexos</b> diz qual. Envie, e o documento conclui sozinho.</td></tr>
    </table>
    </div>

    <div class="keep">
    <h3>Anexos: identidade, comprovante</h3>
    <p>O modelo pode pedir arquivos — identidade, comprovante de residência —, e o documento pode pedir outros, só
        dele, no passo <b>Anexos</b> do formulário (<span class="btn2">+ Pedir anexo</span>, marcando
        <b>Obrigatório</b> se for o caso).</p>
    <ul>
        <li>Envie cada arquivo na página do documento, no quadro <b>Anexos</b>: escolha o arquivo e clique em
            <span class="btn">Enviar</span>. Frente e verso? <span class="btn2">Enviar mais um arquivo</span> no
            mesmo item. Um arquivo que ninguém pediu vai em <b>Outro anexo</b>, com o nome do que é.</li>
        <li>Aceita <b>PDF, JPG ou PNG</b>, até 10 MB cada. Foto do celular serve.</li>
        <li>Os anexos podem chegar antes ou depois da assinatura. Mas o documento só <b>conclui</b> com os
            <b>obrigatórios</b>: se todos assinaram e falta algum, ele fica <b>Assinado</b>, esperando, e conclui
            sozinho quando o último chegar.</li>
        <li>No rascunho, dá para remover e enviar de novo. Depois de congelar, ainda dá para enviar, mas o que foi
            enviado não sai mais. Concluído, nada muda.</li>
        <li>Os anexos não entram no PDF assinado: ficam guardados com ele, e o manifesto lista cada um. Não vão na
            via por e-mail.</li>
    </ul>
    </div>

    <h3 style="page-break-before: always; margin-top: 0;">Documento pronto, com imagens: enviar em PDF</h3>
    <p>Para um documento que precisa ir <b>como está</b>, com fotos ou diagramação própria (o modelo guarda só o
        texto). O PDF entra inteiro; o sistema só acrescenta assinaturas, visto e uma linha de validação no pé da
        página — deixe 2 cm livres no rodapé. Arquivo recusado? Use <b>Imprimir → Salvar como PDF</b> e envie o novo.</p>
    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td>Em <span class="btn">Novo documento</span>, escolha <b>Enviar documento pronto (PDF)</b>. Informe o
                título, o arquivo, as regras de assinatura e quem assina, e clique em
                <span class="btn">Enviar e criar rascunho</span>.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td><span class="t">Marque o lugar de cada assinatura.</span><br>
                Em <b>Lugar das assinaturas</b>, clique no nome da pessoa e depois no documento, em cima da linha
                onde ela assina. Repita para cada um e clique em <span class="btn">Salvar lugares</span>. Quem ficar
                sem lugar assina numa folha de assinaturas ao fim. Depois, congele e libere como sempre.</td>
        </tr>
    </table>
</div>

<!-- ====================================================== 7 -->
<div class="section" id="tablet">
    <h2>7. O que a pessoa vê no tablet</h2>
    <p class="lead">Conhecer as telas ajuda a orientar quem está assinando. As etapas marcadas como "se o modelo
        pedir" só aparecem em alguns modelos.</p>

    <table class="tablet">
        <tr>
            <td><b>1. Leitura do QR</b>A pessoa aponta a câmera para o código na tela do atendente.</td>
            <td><span class="opt">se o modelo pedir</span><b>2. Perguntas</b>"Antes de ler, responda": as perguntas do modelo. Depois, <i>Continuar</i>.</td>
            <td><b>3. Documento</b>Rola o texto até o fim; só então o botão vira <i>Li o documento</i>.</td>
            <td><b>4. Identidade</b>Digita os quatro primeiros dígitos do CPF (ou o CPF completo, ou o código que
                chegou no e-mail — se não chegar, há <b>Reenviar código</b>, depois de um minuto).</td>
        </tr>
        <tr>
            <td><b>5. Aceite</b>Marca "Li e concordo" e, quando o modelo pede foto, marca também que
                <b>autoriza a captura da imagem</b> — sem as duas marcações o tablet não segue. Se quiser, pede a
                via por e-mail.</td>
            <td><b>6. Assinatura</b>Assina com o dedo ou a caneta. <i>Limpar</i> apaga e deixa refazer.</td>
            <td><span class="opt">se o modelo pedir</span><b>7. Visto</b>"Agora faça o seu visto": a rubrica que vai em todas as páginas.</td>
            <td><span class="opt">se o modelo pedir</span><b>8. Foto</b>A câmera abre; a pessoa toca em <span class="btn">Tirar foto</span> quando estiver pronta, e o tablet conta 3 segundos e fotografa. Depois: "Assinatura concluída".</td>
        </tr>
    </table>

    <h3>O que vale saber</h3>
    <ul>
        <li><b>As respostas entram no documento antes da leitura.</b> O texto que a pessoa lê já traz o que ela
            respondeu. Se ela notar um erro, o botão <b>Corrigir respostas</b>, na tela do documento, volta às
            perguntas.</li>
        <li><b>Depois da assinatura, as respostas não mudam mais</b> — nem para o próximo signatário, que já vê o
            documento respondido e não recebe as perguntas.</li>
        <li><b>Recusar</b> está disponível em todas as etapas. Se a pessoa recusa, o documento inteiro é encerrado
            como "Recusado".</li>
        <li><b>O tablet avisa antes de encerrar por inatividade.</b> Basta tocar em <i>Continuar</i>.</li>
        <li><b>Nada fica guardado no tablet.</b> Ao fim de cada atendimento ele volta sozinho à tela de espera.</li>
        <li>A via por e-mail só é oferecida a quem tem e-mail informado no documento.</li>
    </ul>
</div>

<!-- ====================================================== 8 -->
<div class="section" id="depois">
    <h2>8. Depois de assinado: onde está o documento</h2>

    <h3>No sistema</h3>
    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td>Abra <span class="menu">Assinaturas → Documentos</span>. A busca aceita título, nome do signatário, CPF ou
                código de validação, e há filtro por situação.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td>Abra o documento e clique em <span class="btn">PDF assinado</span>, no topo.<br>
                O botão <span class="btn2">PDF original</span> traz o documento como a pessoa leu, sem as assinaturas.</td>
        </tr>
    </table>

    <h3>O que vem no PDF assinado</h3>
    <ul>
        <li>O documento, com a assinatura de cada pessoa no lugar dela e, se o modelo pedir, o visto em todas as páginas.</li>
        <li>Ao final, o <b>manifesto de assinatura</b>: quem assinou, quando, a foto (se houver), o registro de cada
            etapa e um QR Code de validação. O CPF aparece mascarado.</li>
        <li>Documento assinado <b>pelo gov.br</b> é diferente: o PDF assinado é o próprio arquivo que voltou do gov.br,
            sem página a mais. O que seria o manifesto vem num arquivo separado, o <span class="btn2">Relatório
            gov.br</span>, com o botão ao lado de <span class="btn">PDF assinado</span> (ver a seção 9).</li>
        <li>Os <b>anexos</b> (identidade, comprovante) não entram no PDF: ficam guardados com o documento, e o
            manifesto lista cada um.</li>
    </ul>

    <div class="keep">
    <h3>Outros caminhos</h3>
    <table class="ref">
        <tr><th style="width:30%">Onde</th><th>O que é</th></tr>
        <tr>
            <td><b>E-mail da pessoa</b></td>
            <td>Quem marcou no tablet que quer a via e tem e-mail informado recebe o PDF assinado alguns instantes depois.
                Quem assinou pelo gov.br recebe sempre, com o relatório junto.</td>
        </tr>
        <tr>
            <td><b>Pasta de rede</b><br><span class="small">(se o arquivamento estiver ligado)</span></td>
            <td>Uma cópia de cada PDF assinado é guardada no servidor de arquivos, em<br>
                <code>Lara/DocumentosAssinados</code> → pasta do <b>modelo</b> → pasta da <b>pessoa</b> (o primeiro
                signatário).<br>
                O arquivo se chama, por exemplo:<br>
                <span class="small">2026-10-03 - Maria de Souza e Joao Pereira - 6W5YTTTJGRCU.pdf</span><br>
                (data da assinatura, quem assinou e código de validação), sem acentos. Com três ou mais pessoas,
                aparecem as duas primeiras e “e mais N”. Documento enviado pronto, em PDF, fica na pasta
                <b>Documentos avulsos</b>, com o título no nome do arquivo. A pasta é uma <b>cópia</b>: renomear ou
                apagar um arquivo ali não muda nada no sistema.</td>
        </tr>
        <tr>
            <td><b>Página de validação</b></td>
            <td>Qualquer pessoa pode conferir se um documento é autêntico pelo endereço impresso no rodapé de cada
                página, ou lendo o QR Code do manifesto. A página confirma o documento; ela não entrega o PDF.
                Documento assinado pelo gov.br também pode ser conferido no validador oficial do governo,
                <code>validar.iti.gov.br</code>.</td>
        </tr>
    </table>
    </div>

</div>

<!-- ====================================================== 9 -->
<div class="section" id="govbr">
    <h2>9. Assinatura pelo gov.br (quem não vem ao balcão)</h2>

    <p>Quem não pode vir ao clube assina o PDF do documento no portal do gov.br, com a própria conta gov.br
        (nível <b>prata ou ouro</b>), e devolve o arquivo. O sistema confere se é este documento, se nada mudou
        depois da assinatura, se o certificado é do gov.br e se o CPF é de um dos signatários — e, se estiver tudo
        certo, registra a assinatura, como o tablet. O sistema não é acessível de fora: o arquivo sempre volta
        <b>por você</b>.</p>

    <table class="steps">
        <tr>
            <td class="n"><span class="num">1</span></td>
            <td>Prepare e <b>congele</b> o documento como sempre. Abra a aba <span class="menu">Assinatura gov.br</span>
                e clique em <span class="btn">Preparar para o gov.br</span>.<br>
                <span class="small">A partir daí o documento é assinado só pelo gov.br (o tablet não libera mais), a
                data da assinatura entra no texto, se o modelo tiver esse campo, e o prazo passa a ser de 7 dias.</span></td>
        </tr>
        <tr>
            <td class="n"><span class="num">2</span></td>
            <td>A aba lista quem falta assinar. Confira o e-mail de quem vai assinar e clique em
                <span class="btn">Enviar por e-mail</span> ao lado do nome — <b>em qualquer ordem</b>. A pessoa
                recebe o PDF para assinar e o passo a passo.<br>
                <span class="small">Prefere enviar por conta própria? Baixe o PDF pelo link da aba (sempre
                <b>depois</b> de preparar) e envie como quiser.</span></td>
        </tr>
        <tr>
            <td class="n"><span class="num">3</span></td>
            <td>A pessoa abre <code>assinador.iti.br</code>, entra com a conta gov.br, envia o PDF, assina e baixa o
                arquivo assinado. Depois, <b>responde ao e-mail</b> com esse arquivo — a resposta chega no
                <b>seu</b> e-mail.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">4</span></td>
            <td>Salve o arquivo que chegou, abra a aba <span class="menu">Assinatura gov.br</span>, escolha o arquivo
                e clique em <span class="btn">Conferir assinatura</span>. Se estiver certo, a pessoa passa a
                <b>Assinou</b>. Se não, a aba mostra o que não passou.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">5</span></td>
            <td><span class="t">Tem mais alguém para assinar?</span> Clique em <span class="btn">Enviar por e-mail</span>
                ao lado do nome dela. O e-mail já leva <b>o arquivo com as assinaturas de quem já assinou</b> — as
                assinaturas vão se somando no mesmo arquivo. Repita os passos 3 e 4.</td>
        </tr>
        <tr>
            <td class="n"><span class="num">6</span></td>
            <td>Quando todos assinaram, o documento fica <b>Assinado</b> e, em seguida, <b>Finalizado</b>: o PDF
                assinado é o arquivo que voltou do gov.br, acompanhado do <b>relatório</b> da conferência. A via (os
                dois arquivos) vai por e-mail a quem tem e-mail cadastrado e a cópia vai para a pasta de rede.</td>
        </tr>
    </table>

    <div class="keep">
    <h3>Um de cada vez, em qualquer ordem</h3>
    <p>Não importa quem assina primeiro. O que importa é que seja <b>um de cada vez</b>: cada pessoa assina o
        arquivo que já traz as assinaturas anteriores. Se você mandar o convite a duas pessoas ao mesmo tempo, as
        duas recebem o mesmo arquivo; a primeira que voltar é registrada, e a segunda é <b>recusada</b>
        ("não traz a assinatura de…"). Nesse caso, clique de novo em <span class="btn">Enviar por e-mail</span>
        para a segunda pessoa — o e-mail novo leva o arquivo certo — e peça que assine de novo.</p>
    </div>

    <div class="keep">
    <h3>Tablet e gov.br no mesmo documento: não dá</h3>
    <p>Cada documento é assinado todo no tablet ou todo pelo gov.br — <b>nos dois sentidos</b>:</p>
    <ul>
        <li><b>gov.br e depois tablet:</b> a assinatura do gov.br fica dentro do arquivo, e qualquer mudança nele a
            desfaz. Para pôr a assinatura do tablet, o sistema precisaria montar o PDF de novo — e isso apagaria a
            do gov.br.</li>
        <li><b>Tablet e depois gov.br:</b> a assinatura do tablet só é desenhada no PDF na hora de finalizar. O
            arquivo que a pessoa assinaria no gov.br não teria a assinatura do tablet, e a finalização, ao desenhá-la,
            apagaria a do gov.br.</li>
    </ul>
    <p>Por isso, quando alguém já assinou no tablet, o botão <span class="btn">Preparar para o gov.br</span> não
        aparece; e depois de preparado, o tablet não libera mais aquele documento. Se precisar mudar de caminho,
        cancele e emita um documento novo.</p>
    </div>

    <div class="keep">
    <h3>O que vale saber</h3>
    <ul>
        <li><b>Não serve para todo documento.</b> Modelo com perguntas respondidas no tablet e modelo com visto em
            todas as páginas são assinados só no balcão — a aba avisa.</li>
        <li><b>O arquivo tem de ser o que saiu do gov.br, sem mexer.</b> Abrir e salvar de novo em outro programa
            (inclusive "imprimir em PDF") desfaz a assinatura, e o arquivo é recusado.</li>
        <li><b>Quem confere é o CPF, não o nome.</b> A assinatura de alguém que não está na lista de signatários é
            recusada, mesmo que o nome pareça certo.</li>
        <li><b>O e-mail não tem link para o sistema</b>, que não é acessível de fora: o arquivo sempre volta por
            você. A aba mostra cada convite enviado, para quem e quando.</li>
        <li><b>Foto e conferência de identidade do tablet não se aplicam:</b> a identidade é a da conta gov.br,
            provada pelo certificado com o CPF.</li>
        <li><b>Anexos</b> (identidade, comprovante) funcionam igual: envie-os no quadro Anexos da página do
            documento. Sem os obrigatórios, o documento assinado não conclui.</li>
        <li>O PDF assinado não ganha página de manifesto (acrescentar uma página desfaria as assinaturas): o registro
            sai no <span class="btn2">Relatório gov.br</span>, que vai junto na via por e-mail e fica para baixar na
            página do documento.</li>
        <li>A hora da assinatura é a informada pelo gov.br. A revogação do certificado ainda não é conferida — a
            tela diz isso; o validador oficial, <code>validar.iti.gov.br</code>, confere.</li>
    </ul>
    </div>
</div>

<!-- ====================================================== 10 -->
<div class="section" id="problemas">
    <h2>10. Problemas comuns</h2>

    <table class="ref">
        <tr><th style="width:38%">O que aconteceu</th><th>Causa provável e o que fazer</th></tr>
        <tr>
            <td>O sistema recusou o meu arquivo do Word</td>
            <td>Ele não está em <code>.docx</code>. Abra no Word e use <b>Salvar como → Documento do Word (.docx)</b>.
                PDF e <code>.doc</code> não são aceitos.</td>
        </tr>
        <tr>
            <td>O tablet disse "Falha na comunicação com o servidor (código …)" ao salvar</td>
            <td>A assinatura <b>não</b> foi gravada: o tablet volta ao traço e a pessoa pode tentar de novo. Se repetir,
                anote o <b>código</b> e o horário e passe à TI — é um erro do servidor, não da conexão. "Sem resposta
                do servidor" é que é problema de rede (Wi-Fi do tablet).</td>
        </tr>
        <tr>
            <td>O arquivo do gov.br foi recusado</td>
            <td>Abra o envio na aba <b>Assinatura gov.br</b> e veja o item marcado com X. Os mais comuns: a pessoa
                devolveu o PDF <b>sem assinar</b>; assinou <b>outro documento</b> (de outro atendimento, um PDF
                baixado antes de "Preparar para o gov.br", ou um arquivo salvo de novo); ou quem assinou <b>não está na
                lista de signatários</b>. Peça que assine de novo o PDF certo, em <code>assinador.iti.br</code>, e envie
                o arquivo baixado de lá.</td>
        </tr>
        <tr>
            <td>O arquivo do gov.br é "válido", mas não registrou a assinatura</td>
            <td>A aba diz o motivo em amarelo. Os mais comuns: o documento não foi <b>preparado para o gov.br</b>; ou
                a pessoa assinou o <b>original</b> (ou o mesmo arquivo que outra pessoa assinou ao mesmo tempo) em vez
                do arquivo com as assinaturas anteriores. Reenvie o convite a ela — o e-mail novo leva o arquivo
                certo — e peça que assine de novo.</td>
        </tr>
        <tr>
            <td>Um campo não apareceu na lista</td>
            <td>Confira no Word se ele está com <b>dois</b> colchetes de cada lado, sem quebra de linha no meio do
                nome. Corrija e envie de novo.</td>
        </tr>
        <tr>
            <td>O documento saiu com <code>[[alguma coisa]]</code> impresso</td>
            <td>O marcador foi digitado errado no Word (por exemplo, com um colchete só de um lado). Corrija o Word e
                revise o modelo.</td>
        </tr>
        <tr>
            <td>Saiu "R$ R$ 1.500,00"</td>
            <td>O texto tem <code>R$</code> antes de um campo do tipo "Valor em reais". Tire o <code>R$</code> do Word
                e revise o modelo.</td>
        </tr>
        <tr>
            <td>A numeração das cláusulas saiu diferente</td>
            <td>A numeração automática do Word é copiada como texto. Se alguma saiu errada, digite os números à mão
                no Word e revise o modelo.</td>
        </tr>
        <tr>
            <td>Saiu uma lacuna <code>________</code> no texto</td>
            <td>É um campo que a pessoa responde no tablet, ou a data automática, e ainda não tem valor. Some quando
                o documento é aberto no tablet e as perguntas são respondidas.</td>
        </tr>
        <tr>
            <td>O tablet diz "QR Code expirado" ou "já foi usado"</td>
            <td>Cada código serve uma vez e por poucos minutos. Clique em <b>Gerar outro código</b>.</td>
        </tr>
        <tr>
            <td>O tablet diz que "os dados não conferem"</td>
            <td>Os dígitos do CPF digitados não batem com o CPF informado no documento. São cinco tentativas. Se o
                CPF do documento estiver errado, cancele e emita outro.</td>
        </tr>
        <tr>
            <td>O documento ficou em "Assinado" e não passa a "Finalizado"</td>
            <td>O PDF final é montado em segundo plano. Se passar de alguns minutos, avise a TI: o serviço que monta
                o arquivo está parado. Nada se perde — a assinatura está registrada.</td>
        </tr>
        <tr>
            <td>O botão "Preparar para o gov.br" não aparece</td>
            <td>A aba diz o motivo: alguém já assinou no tablet, há campo marcado em <b>Perguntar ao
                signatário</b>, o modelo tem visto em todas as páginas, ou o documento ainda não foi congelado.
                Campo marcado: cancele e refaça o documento preenchendo esse campo você mesmo.</td>
        </tr>
        <tr>
            <td>A pessoa não recebeu a via por e-mail</td>
            <td>Ela precisa ter marcado a opção no tablet e ter e-mail informado no documento. Você pode baixar o
                <b>PDF assinado</b> e enviar.</td>
        </tr>
    </table>
</div>

<!-- ====================================================== 10 -->
<div id="cola" style="padding-top: 22px;">
    <h2>11. Cola rápida dos marcadores</h2>
    <p>Para deixar ao lado do computador de quem escreve os modelos.</p>

    <table class="ref">
        <tr><th style="width:42%">Escreva no Word</th><th style="width:58%">O que acontece</th></tr>
        <tr><td><span class="mk">[[Nome do campo]]</span></td><td>Vira um campo. O nome é o que o atendente vê.</td></tr>
        <tr><td><span class="mk">[[assinatura]]</span></td><td>Lugar onde todos os signatários assinam.</td></tr>
        <tr><td><span class="mk">[[assinatura: Contratante]]</span></td><td>Lugar onde assina a parte "Contratante".</td></tr>
        <tr><td>Dois marcadores de assinatura em linhas seguidas ou numa tabela</td><td>As assinaturas saem lado a lado.</td></tr>
        <tr><td><span class="mk">[[Data da assinatura]]</span></td><td>O sistema sugere o tipo "Data da assinatura — automática".</td></tr>
        <tr><td><span class="mk">[[CPF ...]]</span> <span class="mk">[[E-mail]]</span><br>
                <span class="mk">[[Telefone]]</span> <span class="mk">[[CEP]]</span><br>
                <span class="mk">[[Valor ...]]</span> <span class="mk">[[Data ...]]</span></td>
            <td>O sistema sugere o tipo certo pelo nome. Confira sempre na tela do modelo.</td></tr>
    </table>

    <h3>Antes de enviar o Word, confira</h3>
    <ul>
        <li>O arquivo está em <code>.docx</code>, e todo campo tem dois colchetes de cada lado.</li>
        <li>Há um marcador de assinatura — ou um por parte.</li>
        <li>Não há <code>R$</code> antes de um campo de valor, nem o título repetido no topo do texto.</li>
        <li>As alterações de revisão foram aceitas.</li>
    </ul>

    <h3>Antes de congelar um documento, confira</h3>
    <ul>
        <li>O nome e o CPF de quem assina — e, em contrato, quem assina por cada parte.</li>
        <li>Os dados preenchidos, lendo o texto na tela com a pessoa.</li>
        <li>O e-mail, se a pessoa quiser receber a via.</li>
    </ul>

    <p class="small foot">Assinatura de documentos no Lara — guia do usuário. Os prazos citados (5 minutos do QR Code,
        15 minutos de sessão do tablet, 24 horas do documento) são os padrões do sistema e podem ter sido ajustados
        pela TI.</p>
</div>

</div>
</body>
</html>
@endverbatim
