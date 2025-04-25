$(document).ready(function() {

    // accessRulesText()
    // Check if checkbox is checked
    $('.rule-checkbox').on('change', function() {
        var $this = $(this);


        if($this.attr('id') == 'status') $("#rules").toggle();
        else{
            
            let parent = $this.closest('.row');
            let rowHidden = parent.find('.onCheck');
            
            rowHidden.toggle();
        }
        if(!$this.is(':checked')) {
            $("#rules").find('input[type="checkbox"]').prop('checked', false);
            $("#rules").find('input[type="date"]').val('');
            $("#rules").find('input[type="time"]').val('');
            $(".onCheck").hide();
        }
        // accessRulesText()

    });

    

});