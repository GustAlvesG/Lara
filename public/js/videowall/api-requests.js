$(document).ready(function() {
    $('.api-request').click(function() {
        route = $(this).attr('data-api-route');
        //Find inside the parent element the input with the class 'title'
        title = $(this).parent().parent().find('.title').text();

        //IF title contains the word "Esquerda"
        if (title.indexOf('Esquerda') >= 0) {
            //Send a POST request to the route
            route = 'http://192.168.100.111:5000/' + route;
        }
        else {
            //Send a POST request to the route
            route = 'http://192.168.100.110:5000/' + route;
        }

        $.ajax({
            type: 'GET',
            url: route,
            success: function(data) {
                console.log(data);
            }
        });

    });
});