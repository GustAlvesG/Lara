<div class="rule-card border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 rounded-xl shadow-lg hover:shadow-xl transition duration-300 mb-4">

    <div class="flex justify-between items-center"> 
        
        @if(request()->routeIs('schedule.show'))
        <div class="flex-shrink-0 mr-2"> 
            <input 
                type="checkbox" 
                class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300 dark:border-gray-600 dark:bg-gray-900"
                name="selected_reservations[]" 
                value="{{ $item->id }}"
                id="reservation-{{ $item->id }}"

                @if($item->id == request()->route('id')) 
                    checked
                    {{-- Block Edit but keeping the box sending the value --}}
                    onclick="return false;"
                 @endif
            >
        </div>
        @else
        <div class="w-7 flex-shrink-0"></div> @endif

        <a href="{{ route('schedule.show', $item->id) }}" class="flex-grow font-extrabold text-xl text-gray-800 dark:text-white leading-tight truncate">
            Reserva {{ $item->id }}
        </a>

        <div class="flex-shrink-0 ml-2">
            {{-- 
               Nota: Se o componente x-status-span não tiver suporte nativo a dark mode,
               você precisará ajustar o arquivo desse componente separadamente.
            --}}
            <x-status-span :status="$item->status" />
        </div>

    </div>

    <hr class="my-2 border-gray-500 dark:border-gray-600">

    <div class="space-y-2 text-base dark:text-gray-300">
        
        <div class="flex justify-between">
            <strong>Local:</strong>
            <span class="font-medium text-gray-600 dark:text-gray-400">{{ $item->place->name }}</span>
        </div>

        <div class="flex justify-between">
            <strong>Horário:</strong>
            <span class="font-medium text-gray-600 dark:text-gray-400">
                {{ \Carbon\Carbon::parse($item->start_schedule)->format('H:i') }} - {{ \Carbon\Carbon::parse($item->end_schedule)->format('H:i') }}
            </span>
        </div>
        
        @if(request()->routeIs('schedule.index'))
            <div class="flex justify-between">
                <strong>Sócio:</strong>
                <span class="font-medium text-gray-600 dark:text-gray-400">
                    @php
                        $names = explode(' ', $item->member->name);
                        $firstName = ucfirst(strtolower($names[0]));
                        $lastName = ucfirst(strtolower($names[count($names) - 1]));
                        @endphp
                        {{ $firstName }} {{ $lastName }}
                </span>
            </div>
        @endif
        
    </div>
</div>