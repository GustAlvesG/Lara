@php
    $isBlocked = $slot['excluded_by_rule'] ?? false;
    $isBooked = isset($slot['colided_member']) && $slot['colided_member'] !== null;
    $isPast = isset($slot['past_date']) && $slot['past_date'] === true;
@endphp
{{-- {{ Array to string }} --}}
@if($isBooked)
    <a type="button" 
        href="{{ route('schedule.show', ['id' => $slot['colides']['id']]) }}" class="{{ $slot['colided_status_id'] == '1' ? 'bg-indigo-600' : 'bg-yellow-500' }} rounded-xl p-3 text-white shadow-md flex flex-col justify-between min-h-[80px] opacity-90">
        <div class="flex justify-between items-start mb-1">
            <span class="text-sm font-black">{{ $slot['start_time'] }} - {{ $slot['end_time'] }}</span>
            <div class="h-5 w-5 rounded bg-white/20 flex items-center justify-center text-[8px] font-bold">
                {{ strtoupper(substr($slot['colided_member']['name'], 0, 2)) }}
            </div>
        </div>
        <p class="text-[10px] font-bold truncate">{{ $slot['colided_member']['name'] }}</p>
    </a>
@elseif($isBlocked)
    <div class="bg-gray-800 rounded-xl p-3 text-white shadow-md border border-gray-700 flex flex-col justify-between min-h-[80px]">
        <div class="flex justify-between items-start mb-1">
            <span class="text-sm font-black text-gray-400">{{ $slot['start_time'] }} - {{ $slot['end_time'] }}</span>
            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
        </div>
        <p class="text-[10px] font-bold text-gray-300 truncate">{{ $slot['excluded_by_rule']['name'] ?? 'Indisponível' }}</p>
        <p class="text-[9px] text-red-400 font-bold uppercase tracking-widest">Indisponível</p>
    </div>
@elseif($isPast)
    <div class="bg-gray-300 rounded-xl p-3 text-gray-600 shadow-md border border-gray-400 flex flex-col justify-between min-h-[80px]">
        <div class="flex justify-between items-start mb-1">
            <span class="text-sm font-black">{{ $slot['start_time'] }}  - {{ $slot['end_time'] }}</span>
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
        </div>
        <p class="text-[10px] font-bold text-gray-500 truncate">Data passada</p>
        <p class="text-[9px] text-gray-600 font-bold uppercase tracking-widest">Indisponível</p>
    </div>
@else
    <input value="{{ $slot['start_time']}} - {{ $slot['end_time'] }}" class="hidden" type="checkbox" name="selected_slots[]" id="">
    <button type="button" 
        onclick="toggleSlot(this, '{{ $place['id'] }}', '{{ $slot['start_time'] }}')"
        class="slot-button bg-white border-2 border-dashed border-gray-100 rounded-xl p-3 hover:border-green-400 hover:bg-green-50 transition duration-200 flex flex-col justify-between min-h-[80px] text-left">
        <div class="flex justify-between items-start w-full">
            
            <span class="text-sm font-black text-gray-400">{{ $slot['start_time'] }} - {{ $slot['end_time'] }}</span>
            <div class="h-6 w-6 rounded-full bg-gray-50 flex items-center justify-center icon-container transition-colors">
                <svg class="w-3 h-3 text-gray-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>
        <p class="text-[9px] font-black text-gray-300 uppercase tracking-widest status-text">Livre</p>
    </button>
@endif