


$(document).ready(function() {
    // accessRulesText()
    // Check if checkbox is checked

    function handleRuleCheckBox(checkbox) {
        var $this = $(checkbox);
        let parent = $this.closest('.row');
        let rowHidden = parent.find('.onCheck');

        
        rowHidden.toggle();

        if(!$this.is(':checked')) {

            rowHidden.find('input[type="checkbox"]').prop('checked', false);
            rowHidden.find('input[type="date"]').val('');
            rowHidden.find('input[type="time"]').val('');
            rowHidden.find('input[type="number"]').val('');
            rowHidden.find('input[type="text"]').val('');
        }
    }

    $('.rule-checkbox').on('change', function() {
        handleRuleCheckBox(this);
    });

    // ...existing code...
    function handleTypeSelect() {
        let type = $("#type-select").val();
        if (type == 'include') {
            $(".only-include").show();
        } else if (type == 'exclude') {
            // Set value null on all inputs inside .only-include
            $(".only-include").find('input').val('');
            $(".only-include").hide();
        }

        $(".only-include").find('.rule-checkbox').each(function() {
            handleRuleCheckBox(this);
            this.checked = false;
        });
        
    }

    $("#type-select").on('change', handleTypeSelect);

    // Executa no load
    handleTypeSelect();

    $('.places-checkbox').on('change', function() {
        handlePlacesCheckBoxChange(this);
    });


    $('.places-checkbox-all').on('change', function() {
        var isChecked = $(this).is(':checked');
        $('.places-checkbox').prop('checked', isChecked);
    });

    function handlePlacesCheckBoxChange(checkbox) {
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

    handlePlacesCheckBoxChange();
});