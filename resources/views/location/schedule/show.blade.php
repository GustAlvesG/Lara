<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Projeto 2XKO - Alocações - Agendamento Detalhes
        </h2>
    </x-slot>

    <x-slot name="css">
       
    </x-slot>

    <div class="row">
        <div class="col-6">
            <x-block>
                <x-slot name="content">
                @include('location.schedule.partials.form', ['item' => $schedule])
                </x-slot>
            </x-block>
        </div>
        <div class="col-6">

            <x-block>
                <x-slot name="content">
                    <form action="{{ route('schedule.update') }} " method="POST">
                        @csrf
                        @method('PUT')
                        @if(isset($other_schedules) && $other_schedules->count() > 0)
        
                            <div class="p-4 mb-4 rounded-lg bg-indigo-50 border border-indigo-200 shadow-md">
                                <span class="text-indigo-800 font-semibold text-base leading-tight">
                                    {{-- <svg class="w-5 h-5 inline-block mr-2 align-text-bottom" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> --}}
                                    Há {{ $other_schedules->count()-1 }} outros agendamentos do membro neste dia. Selecione-os caso deseje alterar múltiplos agendamentos simultaneamente.
                                </span>
                            </div>
                            
                            <div class="space-y-4 mb-6">
                                @foreach($other_schedules as $other_schedule)
                                    @include('location.schedule.partials.element', ['item' => $other_schedule])
                                @endforeach
                            </div>
                        @endif
                    @include('location.schedule.partials.buttons-change', ['item' => $schedule])
                    </form>
                </x-slot>
            </x-block>
        </div>
    </div>

    {{-- {{ $schedules_today }} --}}
    

   
    <x-slot name="js">
        <script src="{{ asset('js/company/form-rules.js') }}"></script>
        <script src="{{ asset('js/pagination.js') }}"></script>
        
    </x-slot>

    
</x-app-layout>
