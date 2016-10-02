$(document).ready(function () {

    // plugin scrool up
    $.scrollUp({
        scrollName: 'scrollUp',
        topDistance: '5',
        topSpeed: 300,
        animation: 'fade',
        animationInSpeed: 200,
        animationOutSpeed: 200,
        scrollText: '',
        activeOverlay: false
    });

    $(".tabs").tabs();

    $(".accordion").accordion();

    $(".button").button();

    $(".radioset").buttonset();

    $(".datepicker").datepicker({
        inline: true
    });

    $(".tooltip").tooltip();

    $(".selectmenu").selectmenu();

    $("img.lazy").lazyload();

});