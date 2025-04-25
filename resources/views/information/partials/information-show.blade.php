<div class="justify-center sm:px-6 lg:px-8 space-y-6 element">

    
    <div class="row">
        <div class="col-8">
           @include('information.partials.basic-show')
        </div>

        <div class="col-4">
            <div class="p-6 sm:p-8 bg-white text-gray-800 dark:text-gray-200 dark:bg-gray-800 shadow sm:rounded-lg">
                @include('information.partials.options-show')
            </div>
            @if(Route::currentRouteName() == 'information.history')
                <div class="row pt-3">
                    <div class="col">
                        <div class="p-6 sm:p-8 bg-white text-gray-800 dark:text-gray-200 dark:bg-gray-800 shadow sm:rounded-lg">
                            <div class="row">
                                Versão criada por {{ $item->user->name }} em {{ $item->created_at->format('d/m/Y H:i') }}
                            </div>
                            <div class="row">
                                @isset($previous)
                                <hr class="my-2">
                                Versão atualizada por {{ $previous->user->name }} em {{ $previous->created_at->format('d/m/Y H:i') }}
                                @endisset
                                
                            </div>

                        </div>
                    </div>
                </div>
            @endif
            <div class="row py-3 flex justify-center items-center">
                <div class="col">
                    @if (Route::currentRouteName() != 'information.history')
                    <x-secondary-button-a href="{{ route('information.history', $info->information_id) }}">
                        Ver histórico de alterações
                    </x-secondary-button-a>
                    @else
                    
                        @isset($previous)
                        <form action="{{ route('information.update', $info) }} " method="POST">
                            @csrf
                            @method('PUT')
                            <x-secondary-button type="submit">
                                Tornar versão atual
                            </x-secondary-button>
                        </form>
                        @endisset
                    @endif

                </div>
            </div>
        </div>
        
    </div>

    
</div>