<div class="row mt-4 w-full">
    <div class="hidden title">{{ $title }} </div>
    <div class="col-4">
        <!-- Botao para Atualizar Video Wall 1 -->
        <x-primary-button class="mt-2 mx-1 api-request" data-api-route="update">
            {{ __('Atualizar') }}
        </x-primary-button>
    </div>
    <div class="col-4">
        <!-- Botao para Atualizar Video Wall 1 -->
        <x-primary-button class="mt-2 mx-1 api-request" data-api-route="spotify">
            {{ __('Spotify') }}
        </x-primary-button>
    </div>
    <div class="col-4">
        <!-- Botao para Atualizar Video Wall 1 -->
        <x-primary-button class="mt-2 mx-1 api-request" data-api-route="restart">
            {{ __('Reiniciar') }}
        </x-primary-button>
    </div>
</div>