<x-app-layout>
    <x-slot name="header">
       <div class="flex justify-between items-center">
        <div class="div">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Reservas
            </h2>
        </div>
        
        <div class="div">

            <div class="flex items-center"> 
            
                <x-primary-button-a href="{{ route('schedule.create') }}">
                    {{ __('Criar Reserva') }}
                </x-primary-button-a>

                <x-primary-button-a href="{{ route('schedule.generatePDF') }}"
                    style="margin-left: 1rem !important;">
                    {{ __('Exportar Reservas') }}
                </x-primary-button-a>
                
                <x-primary-button-a 
                    href="{{ route('place-group.index') }}" 
                    style="margin-left: 1rem !important;">
                    {{ __('Gerenciar') }}
                </x-primary-button-a>
                    
            </div>
            
        </div>
    </x-slot>

    <x-slot name="css">
       
    </x-slot>

    {{-- <x-block>
        <x-slot name="content"> --}}
            <x-accordion>
                <x-slot name="title">
                    <h2 class="text-xl text-gray-800 dark:text-white font-bold flex items-center">
                        <svg class="w-6 h-6 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        Pesquisa Avançada
                    </h2>
                </x-slot>

                <x-slot name="body">
                    @include('location.schedule.partials.search-option')
                </x-slot>
                   
            </x-accordion>
        {{-- </x-slot>
    </x-block> --}}



    @php
        $cont = 0;
    @endphp
    
    @foreach ($schedules as $key_date=>$item_date)
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
   
    <x-slot name="js">
        <script src="{{ asset('js/company/form-rules.js') }}"></script>
        <script src="{{ asset('js/pagination.js') }}"></script>
        <script src="{{ asset('js/accordion.js') }}"></script>
        
    </x-slot>

    
</x-app-layout>
