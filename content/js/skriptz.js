$(document).ready(function () {
    if (window.innerWidth > 600) {
        var i = 0;

        function yved() {
            i = 1;
            $('.yved:nth-child(' + i + ')').fadeIn(500).delay(4000).fadeOut(500);
        }

        function getRandomInt(min, max) {
            return Math.floor(Math.random() * (max - min + 1)) + min;
        }


        var t = getRandomInt(1, 1);
        setTimeout(function () {
            i = i + 1;
            if (i > 35) i = 1;
            $('.yved:nth-child(' + i + ')').fadeIn(500).delay(4000).fadeOut(500);
        }, t*1000);

        setInterval(function () {

            var t = getRandomInt(4, 10);
            setTimeout(function () {
                    i = i + 1;
                    if (i > 35) i = 1;
                    $('.yved:nth-child(' + i + ')').fadeIn(500).delay(4000).fadeOut(500);
                }, t*1000)
        }, 10000)
    }
});
