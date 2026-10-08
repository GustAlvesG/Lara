{{--
    Telas do autoatendimento do Termo de Menores, incluídas em
    quiosque/index.blade.php só no modo "menores". A lógica está em
    menores-js.blade.php. O pareamento do tablet usa a tela de espera do
    quiosque (o leitor de QR), com outro texto.

    Ordem: início (título) → responsável → CPF → dados que faltam do
    responsável → menores → dados que faltam do menor → [quiosque: leitura,
    aceite, traço, foto] → tela verde.
--}}
@verbatim
    <!-- M1. Início: número do título -->
    <section class="screen" id="tela-m-inicio">
      <div class="pad grow">
        <span class="evento hidden" id="mEvento"></span>
        <h1>Termo de Menores</h1>
        <p class="lead">Autorização de entrada de menores no evento, exclusiva para sócios. Digite o número do título.</p>

        <div id="mBoxTitulo">
          <input class="campo-grande" id="mTitulo" inputmode="text" autocomplete="off" autocapitalize="characters"
                 spellcheck="false" maxlength="20" placeholder="Nº do título">
        </div>

        <p class="note note-warn hidden" id="mSemTermo" style="margin-top:18px;">
          Não há termo de menores disponível hoje. Procure a organização do evento.
        </p>
        <p class="note note-danger hidden" id="mErroTitulo" style="margin-top:18px;"></p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-primary" id="mBtnTitulo">Continuar</button>
      </div>
    </section>

    <!-- M2. Quem é o responsável -->
    <section class="screen" id="tela-m-responsavel">
      <div class="pad grow">
        <h2>Quem é o responsável?</h2>
        <p class="lead">Toque no seu nome. O responsável precisa ser maior de idade.</p>
        <div class="lista" id="mAdultos"></div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" data-m-inicio>Voltar</button>
      </div>
    </section>

    <!-- M3. CPF completo do responsável -->
    <section class="screen" id="tela-m-cpf">
      <div class="pad grow">
        <h2>Confirme o seu CPF</h2>
        <p class="lead" id="mCpfInstrucao">Digite o seu CPF completo, apenas números.</p>

        <div class="cpf-box">
          <input class="cpf-input" id="mCpf" inputmode="numeric" autocomplete="off" maxlength="11" readonly
                 style="letter-spacing:4px;font-size:30px;">
          <div class="keypad" id="mTeclado"></div>
        </div>

        <p class="note note-danger hidden" id="mErroCpf" style="margin-top:18px;"></p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="mVoltarResponsavel">Voltar</button>
        <button type="button" class="btn btn-primary" id="mBtnCpf" disabled>Confirmar</button>
      </div>
    </section>

    <!-- M4. O que falta do responsável no cadastro do clube -->
    <section class="screen" id="tela-m-dados-resp">
      <div class="pad grow">
        <h2>Complete os seus dados</h2>
        <p class="lead">Não encontramos no cadastro do clube. Se preferir, pule: o termo sai com "não informado".</p>

        <div class="q hidden" id="mBoxEmail">
          <label class="q-title" for="mEmail">Seu e-mail (a via assinada vai para ele)</label>
          <input class="q-input" type="email" id="mEmail" inputmode="email" autocomplete="off" maxlength="150"
                 placeholder="nome@exemplo.com.br">
        </div>
        <div class="q hidden" id="mBoxRgResp">
          <label class="q-title" for="mRgResp">Seu RG</label>
          <input class="q-input" type="text" id="mRgResp" autocomplete="off" maxlength="20">
        </div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="mPularResp">Pular</button>
        <button type="button" class="btn btn-primary" id="mBtnDadosResp">Continuar</button>
      </div>
    </section>

    <!-- M5. Qual menor -->
    <section class="screen" id="tela-m-menores">
      <div class="pad grow">
        <h2>Qual menor você vai autorizar?</h2>
        <p class="lead" id="mMenoresLead">Aparecem os menores do seu título com sobrenome em comum com o seu. Um termo por menor.</p>
        <div class="lista" id="mMenores"></div>
        <p class="note note-warn hidden" id="mSemMenores">
          Não há menor deste título com sobrenome em comum com o seu. Procure a organização do evento.
        </p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="mSair">Sair</button>
      </div>
    </section>

    <!-- M6. O que falta do menor no cadastro do clube -->
    <section class="screen" id="tela-m-dados-menor">
      <div class="pad grow">
        <h2 id="mDadosMenorTitulo">Dados do menor</h2>
        <p class="lead">Não encontramos no cadastro do clube. Se preferir, pule: o termo sai com "não informado".</p>

        <div class="q hidden" id="mBoxCpfMenor">
          <label class="q-title" for="mCpfMenor">CPF do menor</label>
          <input class="q-input" type="text" id="mCpfMenor" inputmode="numeric" autocomplete="off" maxlength="14"
                 placeholder="000.000.000-00">
        </div>
        <div class="q hidden" id="mBoxRgMenor">
          <label class="q-title" for="mRgMenor">RG do menor</label>
          <input class="q-input" type="text" id="mRgMenor" autocomplete="off" maxlength="20">
        </div>

        <p class="note note-danger hidden" id="mErroDadosMenor"></p>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="mVoltarMenores">Voltar</button>
        <button type="button" class="btn btn-ghost" id="mPularMenor">Pular</button>
        <button type="button" class="btn btn-primary" id="mBtnDadosMenor">Continuar</button>
      </div>
    </section>

    <!-- M7. Tela verde: apresentada na entrada do evento -->
    <section class="screen" id="tela-verde">
      <div class="verde">
        <div class="check">✓</div>
        <div class="apresente">Apresente ao Representante do Clube</div>
        <img class="foto hidden" id="vFoto" alt="Foto do responsável">
        <div class="rotulo">Menor autorizado</div>
        <div class="nome" id="vMenor"></div>
        <div class="sub" id="vIdade"></div>
        <div class="rotulo">Responsável</div>
        <div class="sub" id="vResponsavel" style="font-size:20px;font-weight:800;"></div>
        <div class="sub" id="vEvento" style="margin-top:8px;"></div>
        <div class="sub" id="vHora"></div>
      </div>
      <div class="foot">
        <button type="button" class="btn btn-ghost" id="vOutro">Autorizar outro menor</button>
        <button type="button" class="btn btn-primary" id="vConcluir">Concluir</button>
      </div>
    </section>
@endverbatim
