$(document).ready(function() {

    onLoad(1);
    function onLoad(page) {
        $('.page').each(function() {
            //Get all elements with class .element inside the current .page
            let elements = $(this).find('.element');
            let limit = $(this).data('limit');
            pageElements(elements, limit, page)

            //Set actual page on data-actual
            $(this).data('actual', page);
            console.log($(this).data('actual'));
        });
    }

    function pageElements(elements, limit, page){

        let totalPages = totalPagesCalc(removeNotMatches(elements), limit);
        let min = (page - 1) * limit;
        let max = page * limit;

        let index = 0;
        let aux = 0;

        elements.each(function() {
            index++;
            if (index > min) {
                if (min < max && !$(this).hasClass('not-match')) {
                    $(this).show();
                    min++;
                    aux++;
                    if (aux == limit) aux = 0;
                    else $(this).css({
                            // "margin-bottom": "2rem",
                            // "border-bottom": "1px solid #ccc",
                            // "padding-bottom": "2rem"
                    });
                }
                else {
                    $(this).hide();
                }
            }else{
                $(this).hide();
            }
        });

    }

    function removeNotMatches(elements) {
        let response = [];
        elements.each(function() {
            if (!$(this).hasClass('not-match')) {
                response.push($(this));
            }
        });

        return response;
    }

    $('.page-button').on('click', function() {
        group = $(this).closest('.page-group');
        page = group.find('.page');


        elements = page.find('.element');


        let actualPage = page.data('actual');
        let action = $(this).data('page');

        let totalPages = totalPagesCalc(removeNotMatches(elements), page.data('limit'));

        switch (action) {
            case "first":
                actualPage = 1;
                break;
            case "last":
                actualPage = totalPages;
                break;
            case "previous":
                if (actualPage > 1) {
                    actualPage--;
                }
                break;
            case "next":
                if (actualPage < totalPages) actualPage++
                
                break;
            default:
                actualPage = parseInt(actualPage);
        }
        page.data('actual', actualPage);
        group.find('.actual').text(actualPage);
        pageElements(elements, page.data('limit'), actualPage);
    }); 

    function totalPagesCalc(elements, limit) {
        return Math.ceil(elements.length / limit);
    }

    $("#search").on("keyup", function() {
        var value = $(this).val().toLowerCase();
        $(".element").each(function() {
            if ($(this).text().toLowerCase().search(value) == -1) {
                $(this).addClass("not-match");
            }
            else {
                $(this).removeClass("not-match");
            }
        });


        $('.page').each(function() {
            let elements = $(this).find('.element');
            let limit = $(this).data('limit');
            pageElements(elements, limit, 1);
            $(this).data('actual', 1);
        });
    });
    
});

// $(document).ready(function() {

//     const limit = 5;
//     let activePage;
//     pageItems(1);

//     function totalPagesCalc() {
//         let totalItems = $(".element").length - $(".not-match").length;
//         return Math.ceil(totalItems / limit);
//     }

//     $("#search").on("keyup", function() {
//         var value = $(this).val().toLowerCase();
//         $(".element").each(function() {
//             if ($(this).text().toLowerCase().search(value) == -1) {
//                 $(this).addClass("not-match");
//             }
//             else {
//                 $(this).removeClass("not-match");
//             }
//         });
//         pageItems(1);
//     });

//     // Click event for pagination buttons
//     $(".page-button").on("click", function() {
//         let totalPages = totalPagesCalc();
//         let page = $(this).data("page");
//         switch (page) {
//             case "first":
//                 if (activePage !== 1) {
//                     activePage = 1;
//                 }
//                 break;
//             case "last":
//                 if (activePage !== totalPages) {
//                     activePage = totalPages;
//                 }
//                 break;
//             case "previous":
//                 if (activePage > 1) {
//                     activePage--;
//                 }
//                 break;
//             case "next":
//                 if (activePage < totalPages) {
//                     activePage++;
//                 }
//                 break;
//         }
//         pageItems(activePage);
//     });

//     function pageItems(page) {
//         activePage = page;
//         var min = limit * (page - 1);
//         var max = limit * page;
//         var index = 0;
//         var aux = 0;
//         $('.element').css({});
//         $('.element').each(function() {
//             index++
//             if (index > min) {
//                 if (min < max && !$(this).hasClass('not-match')) {
//                     $(this).show();
//                     min++;
//                     aux++;
//                     if (aux == limit) aux = 0;
//                     else if (!$(this).hasClass("rule-card-pagination")) $(this).css({
//                             "margin-bottom": "2rem",
//                             "border-bottom": "1px solid #ccc",
//                             "padding-bottom": "2rem"
//                     });
//                 }
//                 else {
//                     $(this).hide();
//                 }
//             }else{
//                 $(this).hide();
//             }
//         });
//         $(".actual").text(page);
//     }
// });