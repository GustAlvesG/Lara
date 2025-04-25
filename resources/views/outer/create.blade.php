<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ 'Novo Funcionário - ' . $company->name }}
        </h2>
    </x-slot>

    <x-slot name="css">

    </x-slot>

    <div class="row">
        <div class="col-6">
            <x-block>
                <x-slot name="content">
                    <form method="POST" action="{{ route('outer.store') }}" enctype="multipart/form-data">
                        @csrf
        
                        <input type="hidden" name="company_id" value="{{ $company->id }}">
        
                        @include('outer.form')
        
                        <x-primary-button class="mt-2" id="submit">Salvar</x-primary-button>
                    </form>
                </x-slot>
            </x-block>
        </div>
        <div class="col-6" style="visibility: hidden;">
            <div id="my_camera"></div>
        </div>
    </div>


    <script language="JavaScript">

        Webcam.set({
            width: 490,
            height: 350,
            image_format: 'jpeg',
            jpeg_quality: 90
        });

        Webcam.attach( '#my_camera' );

    
        function take_snapshot() {
            Webcam.snap( function(data_uri) {
                $(".image-tag").val(data_uri);
                document.getElementById('results').innerHTML = '<img src="'+data_uri+'"/>';
            } );
    
        }
    
    </script>

    <x-slot name="js">
        <script src="{{ asset('js/company/form-outer.js')}}"></script>
    </x-slot>

</x-app-layout>
