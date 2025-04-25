$(document).ready(function() {
    
    $('.optional-field').on('click', function() {

        let name = $(this).attr('name');
        let checked = $(this).is(':checked');
        if (checked) {
            $('.' + name).show();
        } else {
            $('.' + name).hide();
        }
        $('#' + name).val('');
        if (name == 'prices'){
            $('.price_associated').val('');
            $('.name_price').val('');
            $('.price_not_associated').val('');
            $('#newPrice').toggle();
            if ($('#pricesRow').children().length > 1){
                $('#pricesRow').children().slice(1).remove();
            }
        }else if (name == 'responsible'){
            $('.responsible').val('');
            $('.responsible_contact').val('');
            
            $('#newResponsible').toggle();
            if ($('#responsibleRow').children().length > 1){
                $('#responsibleRow').children().slice(1).remove();
            }
        } else if (name == 'day_hour'){
            $('.day_hour').val('');
            
            $('#newDayHour').toggle();
            if ($('#dayHourRow').children().length > 1){
                $('#dayHourRow').children().slice(1).remove();
            }
        }
    });

    $('#newPrice').on('click', function() {
        $('#pricesRow').removeClass('hidden');
        let lastChild = $('#pricesRow').children().last();
        let count = $('#pricesRow').children().length;
        let clone = lastChild.clone();
        //Add text + #Count to every .count_tag
        clone.find('.count_tag').each(function() {
                if ($(this).hasClass('create')) count ++
                if ($(this).hasClass('price_associated')){
                    $(this).text("R$ #" + count);
                }
                else if ($(this).hasClass('name_price')){
                    $(this).text("Título do Preço #" + count);
                }
                else if ($(this).hasClass('price_not_associated')){
                    $(this).text("R$ #" + count);
                }
        });
        clone.removeClass('hidden');
        clone.find('input').val('');
        $('#pricesRow').append(clone);
    });

    $('#newResponsible').on('click', function() {
        $('#responsibleRow').removeClass('hidden');
        let lastChild = $('#responsibleRow').children().last();
        let count = $('#responsibleRow').children().length;
        let clone = lastChild.clone();
        //Add text + #Count to every .count_tag
        clone.find('.count_tag').each(function() {
            if ($(this).hasClass('create')) count ++
            if ($(this).hasClass('responsible')){
                $(this).text("Responsável #" + count);
            }
            else if ($(this).hasClass('responsible_contact')){
                $(this).text("Telefone #" + count);
            }
        });
        clone.removeClass('hidden');
        clone.find('input').val('');
        $('#responsibleRow').append(clone);
    });

    $('#newDayHour').on('click', function() {
        $('#dayHourRow').removeClass('hidden');
        let lastChild = $('#dayHourRow').children().last();
        let count = $('#dayHourRow').children().length;
        let clone = lastChild.clone();
        //Add text + #Count to every .count_tag
        clone.find('.count_tag').each(function() {
            if ($(this).hasClass('create')) count ++
            if($(this).hasClass('day_hour')){
                $(this).text("Dia #" + count);
            }
            else if($(this).hasClass('start_hour')){
                $(this).text("Horário Início #" + count);
            }
            else if($(this).hasClass('end_hour')){
                $(this).text("Horário Fim #" + count);
            }
        });
        //Select the first option
        clone.find('select').val('#');
        clone.find('input').val('');
        clone.find('.timer').val('00:00');
        clone.removeClass('hidden');
        $('#dayHourRow').append(clone);
    });
});