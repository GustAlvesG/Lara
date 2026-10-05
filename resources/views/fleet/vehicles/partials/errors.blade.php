@if ($errors->any())
    <div class="rounded-2xl bg-danger-soft p-4 text-sm text-danger" role="alert">
        <p class="mb-1 font-bold">Corrija antes de salvar:</p>
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
