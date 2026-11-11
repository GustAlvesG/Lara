{{--
    Kiosk › Atendimento: CPF, busca por função, contratos do dia, falta, cadastro e o menu do freelancer.
    Os ids e data-* são lidos por resources/js/kiosk.js: renomear aqui exige renomear lá.
--}}
<!-- ===== CPF ===== -->
<section class="screen" id="s-cpf">
  <div class="screen-body">
    <p class="eyebrow">Atendimento</p>
    <h2 class="title">Localizar freelancer</h2>
    <p class="subtitle">Digite os 11 números do CPF</p>
    <div class="display mt-lg">
      <div class="val placeholder" id="cpfVal">•••.•••.•••-••</div>
      <div class="hint" id="cpfHint">&nbsp;</div>
    </div>
    <div class="keypad" id="cpfKeypad"></div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="cpfNext" disabled>Buscar freelancer</button>
    <button class="btn btn-ghost" id="cpfBusca">Precisa chamar alguém? Buscar por função</button>
    <button class="btn btn-ghost" id="cpfDia">Contratos do dia · registrar falta</button>
  </div>
</section>

<!-- ===== BUSCA POR FUNÇÃO: escolha ===== -->
<section class="screen" id="s-busca">
  <div class="screen-body">
    <p class="eyebrow">Buscar freelancer</p>
    <h2 class="title">Qual função você precisa?</h2>
    <p class="subtitle">Aparecem os freelancers que já atuaram na função, dos que mais atuaram para os que menos.</p>
    <p class="field-label gap">Para quando</p>
    <div class="chipset" id="buscaDays">
      <button class="opt sel" data-busca-day="0">Hoje</button>
      <button class="opt" data-busca-day="1">Amanhã</button>
    </div>
    <p class="field-label gap">Função</p>
    <div class="chipset col" id="buscaFnList"></div>
  </div>
  <div class="screen-foot">
    <button class="btn-quiet btn" data-go="cpf">Voltar ao atendimento</button>
  </div>
</section>

<!-- ===== BUSCA POR FUNÇÃO: resultado ===== -->
<section class="screen" id="s-busca-lista">
  <div class="screen-body">
    <p class="eyebrow">Buscar freelancer · <span id="buscaDayLabel">hoje</span></p>
    <h2 class="title" id="buscaFnName">—</h2>
    <p class="subtitle" id="buscaResumo"></p>
    <div class="clist mt" id="buscaList"></div>
    <h3 class="lote-h" id="buscaBlockedH" style="display:none">Bloqueados · limite semanal</h3>
    <p class="subtitle" id="buscaBlockedSub" style="display:none;margin:-4px 0 12px"></p>
    <div class="clist" id="buscaBlocked"></div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-ghost" id="buscaTrocar">Trocar função</button>
    <button class="btn-quiet btn" data-go="cpf">Voltar ao atendimento</button>
  </div>
</section>

<!-- ===== CONTRATOS DO DIA =====
     Um dia inteiro, de todos os freelancers: quem estava escalado, quem
     assinou e quem não apareceu. É por aqui que se dá baixa no turno que
     não foi cumprido — a falta devolve a vaga da semana, e o freelancer que
     faltou na quarta pode ser contratado no sábado sem a liberação do
     coordenador.

     Só de hoje para trás: num dia que ainda não chegou não há falta a
     registrar, e poder marcá-la seria o caminho para esvaziar a agenda da
     semana e furar o limite. -->
<section class="screen" id="s-dia">
  <div class="screen-body">
    <p class="eyebrow">Contratos do dia</p>
    <h2 class="title" id="diaTitle">Hoje</h2>
    <p class="subtitle">Quem estava escalado, quem já assinou e quem não apareceu.</p>
    <div class="chipset mt" id="diaDays">
      <button class="opt" data-dia-day="0">Hoje</button>
      <button class="opt" data-dia-day="-1">Ontem</button>
    </div>
    <p class="field-label gap">Outra data</p>
    <input type="date" class="txt-input" id="diaDate">
    <p class="subtitle" id="diaResumo" style="margin-top:18px"></p>
    <div class="clist mt" id="diaList"></div>
  </div>
  <div class="screen-foot">
    <button class="btn-quiet btn" data-go="cpf">Voltar ao atendimento</button>
  </div>
</section>

<!-- ===== LIMITE SEMANAL: algum daqueles dias não foi trabalhado? =====
     Antes de gastar a liberação do coordenador, a tela mostra os contratos
     que estão ocupando as vagas da semana. Se um deles não foi cumprido, o
     caso é marcar falta: o problema é de cadastro, não uma exceção à regra.
     Sem nenhum candidato a falta, esta tela nem aparece. -->
<section class="screen" id="s-falta">
  <div class="screen-body">
    <p class="eyebrow">Limite semanal</p>
    <h2 class="title">Algum destes dias não foi trabalhado?</h2>
    <p class="subtitle" id="faltaSub"></p>
    <div class="clist mt" id="faltaList"></div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="faltaNenhum">Todos foram trabalhados · pedir liberação</button>
    <button class="btn-quiet btn" data-go="previa">Voltar à prévia</button>
  </div>
</section>

<!-- ===== CADASTRO ===== -->
<section class="screen" id="s-cadastro">
  <div class="screen-body">
    <p class="eyebrow">CPF não cadastrado</p>
    <h2 class="title">Cadastrar freelancer</h2>
    <p class="subtitle">A chave PIX será o próprio CPF. E-mail é opcional.</p>
    <div class="form-grid" id="cadForm">
      <label class="frow"><span class="field-label">Nome completo *</span><input class="txt-input" data-f="name" autocomplete="off"></label>
      <label class="frow"><span class="field-label">RG *</span><input class="txt-input" data-f="rg" autocomplete="off"></label>
      <label class="frow"><span class="field-label">Nacionalidade *</span><input class="txt-input" data-f="nacionality" autocomplete="off" value="Brasileira"></label>
      <label class="frow"><span class="field-label">Estado civil *</span><input class="txt-input" data-f="civil_status" autocomplete="off"></label>
      <label class="frow"><span class="field-label">E-mail (opcional)</span><input class="txt-input" data-f="email" type="email" autocomplete="off"></label>
      <label class="frow"><span class="field-label">Endereço *</span><input class="txt-input" data-f="address" autocomplete="off"></label>
      <label class="frow"><span class="field-label">Telefone *</span><input class="txt-input" data-f="telephone" inputmode="tel" autocomplete="off"></label>
    </div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="cadSave">Cadastrar e continuar</button>
    <button class="btn-quiet btn" data-go="cpf">Cancelar</button>
  </div>
</section>

<!-- ===== COMPLETAR CADASTRO ===== -->
<section class="screen" id="s-completar">
  <div class="screen-body">
    <p class="eyebrow">Cadastro incompleto</p>
    <h2 class="title">Completar cadastro</h2>
    <p class="subtitle">Preencha os dados que faltam para liberar o contrato. E-mail é opcional.</p>
    <div class="form-grid" id="compForm">
      <label class="frow"><span class="field-label">Nome completo *</span><input class="txt-input" data-f="name" autocomplete="off"></label>
      <label class="frow"><span class="field-label">RG *</span><input class="txt-input" data-f="rg" autocomplete="off"></label>
      <label class="frow"><span class="field-label">Nacionalidade *</span><input class="txt-input" data-f="nacionality" autocomplete="off"></label>
      <label class="frow"><span class="field-label">Estado civil *</span><input class="txt-input" data-f="civil_status" autocomplete="off"></label>
      <label class="frow"><span class="field-label">E-mail (opcional)</span><input class="txt-input" data-f="email" type="email" autocomplete="off"></label>
      <label class="frow"><span class="field-label">Endereço *</span><input class="txt-input" data-f="address" autocomplete="off"></label>
      <label class="frow"><span class="field-label">Telefone *</span><input class="txt-input" data-f="telephone" inputmode="tel" autocomplete="off"></label>
    </div>
  </div>
  <div class="screen-foot">
    <button class="btn btn-primary" id="compSave">Salvar e continuar</button>
    <button class="btn-quiet btn" data-go="menu">Cancelar</button>
  </div>
</section>

<!-- ===== MENU ===== -->
<section class="screen" id="s-menu">
  <div class="screen-body">
    <p class="eyebrow">Freelancer</p>
    <div class="person k-band">
      <div class="avatar" id="menuAvatar">M</div>
      <div><b id="menuName">—</b><span id="menuCpf">—</span></div>
    </div>
    <div class="incomplete" id="menuIncomplete" style="display:none">
      <b>Cadastro incompleto</b>
      <span class="subtitle" id="menuMissing"></span>
      <button class="btn btn-primary" id="cardCompletar">Completar cadastro</button>
    </div>
    <button class="menu-card" id="cardNovo">
      <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg></span>
      <span class="tx"><b>Novo contrato</b><span>Registrar um turno de trabalho</span></span>
      <span class="chev"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>
    </button>
    <button class="menu-card" id="cardContratos">
      <span class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6M9 16h6M9 8h6M5 3h14a1 1 0 0 1 1 1v16l-3-2-3 2-3-2-3 2V4a1 1 0 0 1 1-1z"/></svg></span>
      <span class="tx"><b>Meus contratos</b><span>Assinar contratos em aberto ou fazer aditivo</span></span>
      <span class="chev"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>
    </button>
  </div>
  <div class="screen-foot">
    <button class="btn-quiet btn" data-go="cpf">Trocar de freelancer</button>
  </div>
</section>
