<div class="element" style='margin-bottom: 1rem; border-bottom: 1px solid #ccc; padding-bottom: 2rem'>
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