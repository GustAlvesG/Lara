<div class="row">
    <div class="col-6">
        <x-primary-button-a href="{{ route('information.edit', $info->id) }}">
            {{ __('Editar Informação') }}
        </x-primary-button-a>
    </div>
    <div class="col-6">
        <form action="{{ route('information.destroy', $info->information_id) }}" method="POST" >
            @csrf
            @method('DELETE')
            <x-danger-button onclick="return confirm('Você tem certeza que deseja apagar essa informação? Essa ação é irreversível.')">
                {{ __('Excluir Informação') }}
            </x-danger-button>
        </form>
    </div>
</div>