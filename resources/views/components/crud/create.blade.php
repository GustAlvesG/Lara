<form action="{{ $route }}" method="{{ $method ?? 'POST' }}" enctype="multipart/form-data">
    @csrf
    @if(isset($put))
        @method('PUT')
    @endif

    {{ $formInputs }}

    <x-primary-button class="mt-2" id="submit">Enviar</x-primary-button>
    
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
        <script>
            CKEDITOR.config.versionCheck = false;
            CKEDITOR.config.height = 400;
            CKEDITOR.replace( 'description' );
        </script>
</form>