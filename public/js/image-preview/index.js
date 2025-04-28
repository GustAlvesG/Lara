$(document).ready(function () {
    // Initialize the image preview
    $('.image-upload').change(function () {
        const $preview = $(this);
        
        let name = $preview.attr('name');
        let $img = $(`#${name}_preview`);
        let $row =  $(`#${name}_preview_row`);
        
        let file = this.files[0];

        if (file) {
            console.log("file");
            const reader = new FileReader();
            reader.onload = function (e) {
                $img.attr('src', e.target.result);
            };
            reader.readAsDataURL(file);
            $row.show(); // Show the preview row if a file is selected
        } else {
            $img.attr('src', ''); // Clear the preview if no file is selected
            $row.hide(); // Hide the preview row if no file is selected
        }
    });

    $('.image_preview_remove').click(function () {
        let row = $(this).closest('.image-preview-row');
        
        let img = row.find('img');

        img.attr('src', ''); // Clear the preview image
        row.hide(); // Hide the preview row
    });
});