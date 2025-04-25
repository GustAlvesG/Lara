<div id="option-area">
    <div class="form-group py-1 image">
        <img class="img-responsive" src="{{ $info->image ? asset('images/'. $info->image) : asset('images/defaultImage.jpg') }}" alt="">
    </div>
    @isset($info->fee)
    <div class="form-group py-1 fee">
	    <x-input-label for="fee">Taxa de Matrícula</x-input-label>
	     <x-text-input name="fee" id="fee" class="form-control" value="{{ $info->fee }}" disabled />
    </div>
    @endisset
    @isset($info->name_price)
    {{-- Make a border to div --}}
    <div class="form-group py-1 prices" >
            @foreach ($info->name_price as $index => $value)
            @if ($value != '' or $info->price_associated[$index] != '' or $info->price_not_associated[$index] != '')
            <h2 class="pt-1 font-semibold text-lg text-gray-800 dark:text-gray-200 leading-tight">
                {{ $value }}
            </h2>
                <div class="row py-1">
                  
                    <div class="col-6">
                        <x-input-label for="price_associated">R$ Sócio</x-input-label>
                        <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_associated[]" id="price_socio_{{ $index }}" class="form-control" value="{{ $info->price_associated[$index] ?? '' }}" disabled/>
                    </div>
                    <div class="col-6">
                        <x-input-label for="price_not_associated">R$ Não Sócio</x-input-label>
                        <x-text-input type="number" min="0.00" max="99999.99" step="0.01" name="price_not_associated[]" id="price_nao_socio_{{ $index }}" class="form-control" value="{{ $info->price_not_associated[$index] ?? '' }}" disabled/>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    @endisset

    @isset($info->day_hour)
    <div class="form-group py-1 day_hour">
        @foreach ($info->day_hour as $item)
        @if ($item != '')
            @php
                $day_hour = explode(',', $item);
                $day = $day_hour[0];
                $start_hour = $day_hour[1];
                $end_hour = $day_hour[2];
            @endphp
            <div class="row py-1">
                <div class="col-6">
                    <x-input-label for="day_hour">Dia</x-input-label>
                    <x-text-input name="day_hour[]" id="day_hour_{{ $index }}" class="form-control" value="{{ $day ?? '' }}" disabled/>
                </div>
                <div class="col-6">
                    <x-input-label for="day_hour">Horário</x-input-label>
                    <x-text-input name="day_hour[]" id="day_hour_{{ $index }}" class="form-control" value="{{ $start_hour ?? '' }} - {{ $end_hour ?? '' }}" disabled/>
                </div>
            </div>
        @endif
        @endforeach
    </div>
    @endisset

    @isset($info->category)
        <div class="form-group py-1 category"  >
            <x-input-label for="category">Categoria</x-input-label>
            <x-text-input name="category" id="category" class="form-control" value="{{ $info->category }}" disabled />
        </div>
    @endisset
    

    @isset($info->responsible)
        <div class="form-group py-1 responsible">
            @foreach ($info->responsible as $index => $value)
            @if ($value != '' or $info->responsible_contact[$index] != '')
                <div class="row py-1">
                    <div class="col-6">
                        <x-input-label for="responsible_{{ $index }}">Responsável {{ $index + 1 }}</x-input-label>
                        <x-text-input name="responsible[]" id="responsible_{{ $index }}" class="form-control" value="{{ $value }}" disabled />
                    </div>
                    <div class="col-6">
                        <x-input-label for="responsible_contact{{ $index }}">Telefone</x-input-label>
                        <x-text-input name="responsible_contact[]" id="responsible_contact{{ $index }}" class="form-control" value="{{ $info->responsible_contact[$index] ?? '' }}" disabled />
                    </div>
                </div>
            @endif
            @endforeach
        </div>
    @endisset

    @isset($info->slots)
        <div class="form-group py-1 slots"  >
            <x-input-label for="slots">Número de Vagas</x-input-label>
            <x-text-input type="number" min="0" name="slots" id="slots" class="form-control" value="{{ $info->slots }}" disabled />
        </div>
    @endisset
    
    @isset($info->status)
        <div class="form-group py-1 status"  >
            <x-input-label for="status">Status</x-input-label>
            <x-text-input name="status" id="status" class="form-control" value="{{ $info->status }}" disabled />
        </div>
    @endisset

    @isset($info->location)
        <div class="form-group py-1 location"  >
            <x-input-label for="location">Localização</x-input-label>
            <x-text-input name="location" id="location" class="form-control" value="{{ $info->location }}"/>
        </div>
    @endisset

    
</div>

