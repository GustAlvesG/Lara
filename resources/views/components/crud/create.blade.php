<form action="{{ $route }}" method="{{ $method ?? 'POST' }}" enctype="multipart/form-data">
    @csrf
    @if(isset($put))
        @method('PUT')
    @endif

    {{ $formInputs }}

    <div class="row">
        <div class="col-2">
            <x-primary-button class="mt-2" id="submit">{{ $buttonText ?? "Enviar" }}</x-primary-button>
        </div>
        <div class="col-2">
            <x-secondary-button class="mt-2" id="cancel" onclick="window.history.back();">
                Cancelar
            </x-secondary-button>
        </div>
    </div>

    
    <script src="https://cdn.ckeditor.com/4.22.1/standard/ckeditor.js"></script>
        <script>
            CKEDITOR.config.versionCheck = false;
            CKEDITOR.config.height = 400;
            CKEDITOR.replace( 'description' );
        </script>
</form>