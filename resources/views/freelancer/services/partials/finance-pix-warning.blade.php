{{-- Com o Pix ligado, pagar transfere dinheiro de verdade. O aviso fica no topo
     de toda tela que tem botão de pagar, porque o botão é o mesmo de antes e
     ninguém deve descobrir a diferença depois de clicar. --}}
@if($pixEnabled)
    <div class="mb-6 rounded-2xl border-l-4 border-amber-500 bg-amber-50 dark:bg-amber-900/20 p-4">
        <p class="text-sm font-bold text-amber-800 dark:text-amber-300">
            Pix automático ativo
            @if($pixAmbiente !== 'producao')
                <span class="ml-1 px-2 py-0.5 rounded-full bg-gray-800 text-white text-xs uppercase">{{ $pixAmbiente }}</span>
            @endif
        </p>
        <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
            Dar baixa <strong>transfere o valor</strong> para a chave PIX do freelancer.
            A baixa só é registrada quando o banco confirmar — até lá o contrato aparece como
            <em>em processamento</em>. Não clique duas vezes: o sistema recusa um segundo envio para o mesmo contrato.
        </p>
    </div>
@endif
