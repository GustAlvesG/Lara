
@php
    $isCheckedPrice = count($info->name_price) === 0 && count($info->price_associated) && count($info->price_not_associated) ? true : false;
    $isCheckedDayHour = count($info->day_hour) === 0 ? false : true;
    $isCheckedResponsible = count($info->responsible) === 0 ? false : true;
@endphp

<div class="row optionalFieldsButtons">
    <div class="col-4 pricesButton flex justify-center items-center">
        <x-primary-button class="{{ $isCheckedPrice ? '' : 'hidden' }} mt-2 text-center" id="newPrice">Novo preço</x-primary-button>
    </div>
    <div class="col-4 responsibleButton flex justify-center items-center">
        <x-primary-button class="{{ $isCheckedResponsible ? '' : 'hidden' }} mt-2 text-center" id="newResponsible">Novo responsável</x-primary-button>
    </div>
    <div class="col-4 dayHourButton flex justify-center items-center ">
        <x-primary-button class="{{ $isCheckedDayHour ? '' : 'hidden' }} mt-2 text-center " id="newDayHour">Novo dia</x-primary-button>
    </div>
</div>