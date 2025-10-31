<div class="row text-center mb-3">
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        Regras de Locação              
    </h2>
</div>

<div class="row mb-3">
    <div class="col-md-4 offset-md-4 flex justify-center col-sm-12">
        <x-primary-button-a href="{{ route('place-group.createScheduleRule', $item->id) }}">
            Nova Regra
        </x-primary-button-a>
    </div>
</div>
<div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
    <div class="page" data-limit="2" data-actual="">
        @php
            $cont = 0;
        @endphp
        @if ($rules != null)
            @foreach ($rules as $rule)
                @if ($cont % 2 == 0)
                    <div class="row">
                @endif
                <div class="col-6 element rule-card-pagination">
                    @include('location.placeGroup.partials.rule-card', ['rule' => $rule])
                    {{-- <pre>{{ json_encode($rule, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre> --}}
                </div>
                @if ($cont % 2 == 1)
                    </div>
                @endif
                @php
                    $cont++;   
                @endphp
            @endforeach
            @if (count($rules) > 2)
            <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
                <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                    @include('partials.navPagination')
                </div>
            </div>
        @endif
        @endif
        
    </div>
</div>