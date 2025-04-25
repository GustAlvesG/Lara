
<div class="row">
    <div class="col-6">
        <x-primary-button-a href="{{ $routeEdit }}">
            {{ $editText ?? __('Editar') }}
        </x-primary-button-a>
    </div>

    <div class="col-6">
        <form action="{{ $routeDelete }}" method="POST" >
            @csrf
            @method('DELETE')
            <x-danger-button onclick="return confirm('{{ $deleteMessage ?? 'Tem certeza que deseja deletar?' }}')">
                {{ $deleteText ?? __('Excluir') }}
            </x-danger-button>
        </form>
    </div>

</div>