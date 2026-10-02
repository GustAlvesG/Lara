<x-app-layout :bootstrap-grid="false">
<div>
    <div class="mx-auto flex w-full max-w-[900px] flex-col gap-5 px-4 pb-12 pt-6 sm:px-6 lg:px-8">
        <x-page-title title="Novo modelo" :back="route('signature-templates.index')">
            O texto do documento e como a identidade é conferida no tablet.
        </x-page-title>

        @include('partials.alerts')

        <form action="{{ route('signature-templates.store') }}" method="POST">
            @csrf
            @include('signature.templates.partials.form', ['template' => null])

            <div class="mt-6 flex justify-end gap-3">
                <a href="{{ route('signature-templates.index') }}" class="px-6 py-3 rounded-full font-bold text-ink-2 hover:bg-subtle transition">Cancelar</a>
                <button type="submit" class="px-6 py-3 bg-grena text-white rounded-full font-bold hover:bg-grena-hover transition">Criar modelo</button>
            </div>
        </form>
    </div>
</div>
</x-app-layout>
