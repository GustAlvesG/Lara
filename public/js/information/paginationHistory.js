$(document).ready(function() {

    const limit = 1;
    let activePage;
    pageItems(1);

    function totalPagesCalc() {
        let totalItems = $(".element").length - $(".not-match").length;
        return Math.ceil(totalItems / limit);
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
        pageItems(1);
    });

    // Click event for pagination buttons
    $(".page-button").on("click", function() {
        let totalPages = totalPagesCalc();
        let page = $(this).data("page");
        switch (page) {
            case "first":
                if (activePage !== 1) {
                    activePage = 1;
                }
                break;
            case "last":
                if (activePage !== totalPages) {
                    activePage = totalPages;
                }
                break;
            case "previous":
                if (activePage > 1) {
                    activePage--;
                }
                break;
            case "next":
                if (activePage < totalPages) {
                    activePage++;
                }
                break;
        }
        pageItems(activePage);
    });

    function pageItems(page) {
        activePage = page;
        var min = limit * (page - 1);
        var max = limit * page;
        var index = 0;
        var aux = 0;
        $('.element').css({});
        $('.element').each(function() {
            index++
            if (index > min) {
                if (min < max && !$(this).hasClass('not-match')) {
                    $(this).show();
                    min++;
                    aux++;
                    if (aux == limit) aux = 0;
                    else $(this).css({
                            "margin-bottom": "2rem",
                            "border-bottom": "1px solid #ccc",
                            "padding-bottom": "2rem"
                    });
                }
                else {
                    $(this).hide();
                }
            }else{
                $(this).hide();
            }
        });
        $(".actual").text(page);
    }
});