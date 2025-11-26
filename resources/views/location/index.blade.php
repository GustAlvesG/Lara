<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Projeto 2XKO - Alocações
        </h2>
    </x-slot>

    <x-slot name="css">
       
    </x-slot>

    <x-block>
        <x-slot name="content">
            <div class="row">
                 <div class="col-6">
                    @include('partials.search')
                </div>
                <div class="col-6 flex justify-center items-center"> <!-- Adicionado classes do Flexbox -->
                    <x-primary-button-a href="{{ route('place-group.index') }}">
                        {{ __('Gerenciar') }}
                    </x-primary-button-a>
                </div>
            </div>
        </x-slot>
    </x-block>



    @php
        $cont = 0;
    @endphp
    
    @foreach ($schedules_today as $key_date=>$item_date)
        <x-block>

            <x-slot name="content">

                <h3 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    @php
                        $date = \Carbon\Carbon::parse($key_date);
                        $today = \Carbon\Carbon::today();
                        $tomorrow = \Carbon\Carbon::tomorrow();
                        
                        @endphp
                    @if($date->isSameDay($today))
                    Hoje - {{ $date->format('d/m/Y') }}
                    @elseif($date->isSameDay($tomorrow))
                    Amanhã - {{ $date->format('d/m/Y') }}
                    @else
                    {{ $date->format('d/m/Y') }}
                    @endif
                </h3>
                
                @foreach($item_date as $sport=>$schedules)
                {{-- Title --}}
                    @php
                        $cont = 0;
                    @endphp
                    <h3 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ $sport }}
                    </h3>

                    <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
                        <div class="page" data-limit="4" data-actual="">
                        @foreach ($schedules as $item)
                            @if ($cont % 4 == 0)
                                <div class="row">
                            @endif
                                <div class="col-3 rule-card-pagination">
                                    @include('location.schedule.partials.element', ['item' => $item])
                                </div>
                            @if ($cont % 4 == 3 || $cont == count($schedules) - 1)
                                </div>
                            @endif
                            @php
                                $cont++;
                            @endphp
                        @endforeach
                    </div>
                </div>
                @endforeach
            </x-slot>
        </x-block>
    @endforeach

        {{ $schedules_today }}

    {{-- {{ $schedules_today }} --}}
    

   
    <x-slot name="js">
        <script src="{{ asset('js/company/form-rules.js') }}"></script>
        <script src="{{ asset('js/pagination.js') }}"></script>
        
    </x-slot>

    
</x-app-layout>
