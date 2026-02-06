<div class="mb-6 flex justify-end gap-3">
            
    <!-- Botão Exportar PDF -->
    <button onclick="generatePDFTable({{ json_encode($modalities) }})" class="inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-xl font-bold text-sm shadow-lg hover:bg-red-700 transition duration-150 transform hover:scale-[1.02]">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 9h1.5m1.5 0H13m-4 4h1.5m1.5 0H13m-4 4h1.5m1.5 0H13"></path>
        </svg>
        Exportar PDF
    </button>
    <!-- Botão Configurações -->
    <a href="{{ route('place-group.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-200 text-gray-700 rounded-xl font-bold text-sm shadow-sm hover:bg-gray-50 transition duration-150 group">
        <svg class="w-5 h-5 mr-2 text-gray-400 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
        </svg>
        Configurações
    </a>
    </div>

    <!-- HEADER E FILTRO DE DATA -->
    <div class="mb-8 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
    <div>
        <h1 class="text-3xl font-extrabold text-gray-900 leading-tight">Reservas</h1>
        <p class="text-gray-500 font-medium">Visualize ocupação e horários disponíveis por modalidade.</p>
    </div>

    <div class="flex items-center gap-3 bg-white p-2 rounded-2xl shadow-xl border border-gray-100">
        <a href="{{ request()->fullUrlWithQuery(['date' => date('Y-m-d', strtotime($date . ' -1 day'))]) }}" class="p-2 hover:bg-gray-100 rounded-lg transition text-gray-400 hover:text-indigo-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        </a>
        
        <form action="{{ url()->current() }}" method="GET" class="px-4 text-center">
            <input id="report-date" type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="font-bold text-gray-800 border-none focus:ring-0 cursor-pointer bg-transparent">
        </form>

        <a href="{{ request()->fullUrlWithQuery(['date' => date('Y-m-d', strtotime($date . ' +1 day'))]) }}" class="p-2 hover:bg-gray-100 rounded-lg transition text-gray-400 hover:text-indigo-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
        </a>
    </div>
</div>