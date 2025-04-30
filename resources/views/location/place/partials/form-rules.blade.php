<div class="row">
    <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group">
        <div class="page" data-limit="4" data-actual="">
            @php
                $cont = 0;
            @endphp
            @if ($rules != null)
                @foreach ($rules as $rule)
                    @if ($cont % 4 == 0)
                        <div class="row">
                    @endif
                    <div class="col-3 element rule-card-pagination">

                        @include('location.placeGroup.partials.rule-card', ['rule' => $rule, 'checkbox' => true])
                    </div>
                    @if ($cont % 4 == 3)
                        </div>
                    @endif
                    @php
                        $cont++;   
                    @endphp
                @endforeach
                @if (count($rules) > 4)
                <div class="flex justify-center sm:px-6 lg:px-8 space-y-6 my-3">
                    <div class="p-6 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg pagination">
                        @include('partials.navPagination')
                    </div>
                </div>
            @endif
            @endif
            
        </div>
    </div>
</div>