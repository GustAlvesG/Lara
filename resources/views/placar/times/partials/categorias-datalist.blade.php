{{--
    Sugestão de categorias já cadastradas. O campo continua livre (dá para
    criar categoria nova), mas quem digita vê primeiro o que já existe — e o
    que for equivalente é casado no servidor por CategoriaService::resolver(),
    então "Sub 15" cai no "Sub-15" que já existe em vez de criar um time
    duplicado.
--}}
<datalist id="categorias-existentes">
    @foreach($categorias ?? [] as $categoriaExistente)
        <option value="{{ $categoriaExistente }}"></option>
    @endforeach
</datalist>

<p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
    @if(($categorias ?? collect())->isEmpty())
        Ex.: Adulto, Sub-15, Sub-17.
    @else
        Já cadastradas: {{ $categorias->take(6)->join(', ') }}@if($categorias->count() > 6) e mais {{ $categorias->count() - 6 }}@endif.
        Variações da mesma categoria são unificadas automaticamente.
    @endif
</p>
