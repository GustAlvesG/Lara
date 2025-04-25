<div class="col-6 flex flex-col justify-center items-center {{ $border ?? '' }}"> <!-- Adicionado classes do Flexbox -->
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        {{ $title }}
    </h2>
    @include('videowall.partials.actionsButtons', [
        'api' => $api,
        'title' => $title
    ])
</div>