<div class="p-6 sm:p-8 bg-white text-gray-800 dark:text-gray-200 dark:bg-gray-800 shadow sm:rounded-lg">
    <div class="form-group py-1">
        <x-input-label for="name">Nome</x-input-label>
        <x-text-input name="name" id="name" class="form-control" value="{{ $info->name }}" disabled />
    </div>
    <div class="form-group py-1">
        <x-input-label for="description">Descrição</x-input-label>
        <textarea name="description" id="description" class="form-control pt-1 description" disabled >
            {{ $info->description }}
        </textarea>
    </div>
</div>