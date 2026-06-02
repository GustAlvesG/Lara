<div class="element mb-4 pb-8 border-b border-gray-200 dark:border-gray-700">
    <div class="row">
        <div class="col-2 flex justify-center items-center">
            <img class="img-responsive" src="{{ $image }}" alt="">
        </div>
        <div class="col-8 text-gray-800 dark:text-gray-200">
            {{ $bodyElement }}       
        </div>
        
        <div class="col-2 flex justify-center items-center">
        @if (isset($form))
            <form action="{{ $formRoute }}" method="post">
                @csrf
                @method($formMethod)
                {{ $form }}
            </form>
        @else
            <x-primary-button-a href="{{ $showMoreRoute ?? '#' }}">
                {{ $showMoreText ?? 'Ver mais' }}
            </x-primary-button-a>
        @endif
        </div>
    </div>
</div>