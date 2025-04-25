


$(document).ready(function() {

    // accessRulesText()
    // Check if checkbox is checked
    $('.rule-checkbox').on('change', function() {
        var $this = $(this);

        let parent = $this.closest('.row');
        let rowHidden = parent.find('.onCheck');

        
        rowHidden.toggle();

        if(!$this.is(':checked')) {
            $("#rules").find('input[type="checkbox"]').prop('checked', false);
            $("#rules").find('input[type="date"]').val('');
            $("#rules").find('input[type="time"]').val('');

        }
    });

    $("#type-select").on('change', function(){
        let type = $(this).val();
        if(type == 'include'){
            $(".only-include").show();
        }
        else if(type == 'exclude'){
            //Set value null on all inputs inside .only-include
            $(".only-include").find('input').val('');
            
            $(".only-include").hide();
        }

        $('input[type="checkbox"]').prop('checked', false);
       
    });

    $('.places-checkbox').on('change', function() {
        placesCheckBoxChange(this);
    });


    $('.places-checkbox-all').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.places-checkbox').prop('checked', isChecked);
    });



    function placesCheckBoxChange(checkbox) {
        // Check if all checkboxes are checked
        var allChecked = true;
        $('.places-checkbox').each(function() {
            if (!$(this).is(':checked') && !$(this).hasClass('places-checkbox-all')) {
                allChecked = false;
            }
        });
        // If all checkboxes are checked, check the "all" checkbox
        $('.places-checkbox-all').prop('checked', allChecked);
        
    }

    placesCheckBoxChange();
    

});