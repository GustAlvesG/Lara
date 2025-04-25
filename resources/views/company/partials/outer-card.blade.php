<!-- filepath: /c:/Users/gustavo.alves/Documents/Projetos/lpr-app/resources/views/company/partials/rule-card.blade.php -->


<div class="rule-card {{ $outer->applicable ? "rule-card-access" : 'rule-card-block' }} border p-4 rounded shadow-sm text-gray-800 dark:text-gray-200 leading-tight font-bold text-lg mb-2 ">
    
    <div class="mb-2 align-items-center">
        <img src="{{ asset('images/'. $outer->image) }} " alt="">
    </div>
    
    <h2 class="font-semibold text-md text-gray-800 dark:text-gray-200 leading-tight text-center">
        {{ $outer->name }}       
    </h2>

    <div class="mb-2">
        <strong>Cel:</strong>
        <span>{{ $outer->telephone }}</span>
    </div>

    <div class="mb-2">
        <strong>Regra Especial:</strong>
        <span>{{ "SIM" }}</span>
    </div>

    @if(Route::currentRouteName() != 'outer.show')
        <div class="mb-2 items-center">
            <x-secondary-button-a class="text-center" href="{{ route('outer.show', $item->id) }}">
                Ver funcionário
            </x-secondary-button-a>
        </div>
    @endif
</div>