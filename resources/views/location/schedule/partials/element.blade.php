<div class="rule-card border border-gray-200 bg-white p-4 rounded-xl shadow-lg hover:shadow-xl transition duration-300 mb-4">

    <div class="flex justify-between items-center"> 
        
        @if(request()->routeIs('schedule.show'))
        <div class="flex-shrink-0 mr-2"> <input 
                type="checkbox" 
                class="form-checkbox h-5 w-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300"
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

        <a href="{{ route('schedule.show', $item->id) }}" class="flex-grow font-extrabold text-xl text-gray-800 leading-tight truncate">
            Reserva {{ $item->id }}
        </a>

       <div class="flex-shrink-0 ml-2">

            <x-status-span :status="$item->status" />
    
        </div>

    </div>

    <hr class="my-2 border-gray-500">

    <div class="space-y-2 text-base">
        
        <div class="flex justify-between">
            <strong>Local:</strong>
            <span class="font-medium text-gray-600">{{ $item->place->name }}</span>
        </div>

        <div class="flex justify-between">
            <strong>Horário:</strong>
            <span class="font-medium text-gray-600">
                {{ \Carbon\Carbon::parse($item->start_schedule)->format('H:i') }} - {{ \Carbon\Carbon::parse($item->end_schedule)->format('H:i') }}
            </span>
        </div>
        
        @if(request()->routeIs('schedule.index'))
            

            <div class="flex justify-between">
                <strong>Sócio:</strong>
                <span class="font-medium text-gray-600">
                    {{ $item->member->name }}
                </span>
            </div>
        @endif
        
    </div>
</div>