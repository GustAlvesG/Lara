<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        </h2>
    </x-slot>

    <x-slot name="css">

    </x-slot>

    <div class="row">
        <div class="col-6">       
            <x-block> 
                <x-slot name="content">
                    @include('company.partials.outer-card', ['outer' => $outer])
                </x-slot>
            </x-block>
        </div>
        <div class="col-6">  
            <div class="row">
                <x-block> 
                    <x-slot name="content">
                        <div class="row">
                            <h2 class="font-semibold text-md text-gray-800 dark:text-gray-200 leading-tight text-center mb-2">
                                Regras Individuais           
                            </h2>

                        </div>
                        <div class="row mb-3">
                            <div class="col-12 offset-12 flex justify-center">
                                <x-primary-button-a href="{{ route('accessrule.createOuter', $outer->id ) }}">
                                    Nova Regra
                                </x-primary-button-a>
                            </div>
                        </div>
                        <div class="mx-auto sm:px-6 lg:px-8 space-y-6 page-group row">
                            <div class="page" data-limit="3" data-actual="">
                                @if ($outer->accessRules == null)
                                    <div class="row">
                                        <div class="col-12">
                                            <div class="alert alert-info" role="alert">
                                                Nenhuma regra cadastrada
                                            </div>
                                        </div>
                                    </div>
                                @else
                                    {{ $outer->accessRules }}
                                    @foreach ($outer->accessRules as $rule)
                                        {{ $rule }} -
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </x-slot>
                </x-block>    
            </div>     
  
        </div>
    </div>


    <x-slot name="js">
        <script src="{{ asset('js/company/form-outer.js')}}"></script>
    </x-slot>

</x-app-layout>
