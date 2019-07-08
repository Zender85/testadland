$(document).ready(function () {
        $(".toform").click(function () {
            var $scroll_block = $('form').offset().top;
            $("html,body").animate({scrollTop: $scroll_block}, 600);
            return false;
        });
    });