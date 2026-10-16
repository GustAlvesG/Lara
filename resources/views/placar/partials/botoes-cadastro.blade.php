{{--
    Rodapé dos formulários de criação do Placar: Cancelar, "Cadastrar e
    criar outro" e "Cadastrar". O destino de cada um é decidido no
    controller (RedirecionaAposCadastro), pelo valor de `acao`.

    "Cadastrar" vem primeiro no HTML porque o Enter dispara o primeiro botão
    de submit do formulário — e o Enter tem de ser o cadastro simples. A
    ordem visual (ele à direita) é do `order-last`.

    Parâmetro: $voltar — URL do Cancelar (a lista).
--}}
<div class="mt-6 flex flex-wrap items-center justify-end gap-3">
    <button type="submit" name="acao" value="salvar"
        class="order-last px-6 py-3 bg-grena text-white rounded-full font-bold shadow-card hover:bg-grena-hover transition">
        Cadastrar
    </button>
    <a href="{{ $voltar }}" class="px-6 py-3 rounded-xl font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
    <button type="submit" name="acao" value="continuar"
        class="px-6 py-3 rounded-full font-bold border border-grena text-grena-ink hover:bg-grena-tint transition">
        Cadastrar e criar outro
    </button>
</div>
