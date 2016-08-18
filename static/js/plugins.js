/**
 * LICENSE
 *
 * This source file is subject to the new Creative Commons license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/4.0/
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contact@active-publishing.fr so we can send you a copy immediately.
 *
 * @author      Active Publishing <contact@active-publishing.fr>
 * @copyright   Copyright (c) 2016 Active Publishing (http://www.active-publishing.fr)
 * @license     http://creativecommons.org/licenses/by-nc-nd/4.0/
 */
$(document).ready(function () {

    // plugin scrool up
    $.scrollUp({
        scrollName: 'scrollUp', // Element ID
        topDistance: '5', // Distance from top before showing element (px)
        topSpeed: 300, // Speed back to top (ms)
        animation: 'fade', // Fade, slide, none
        animationInSpeed: 200, // Animation in speed (ms)
        animationOutSpeed: 200, // Animation out speed (ms)
        scrollText: '', // Text for element
        activeOverlay: false // Set CSS color to display scrollUp active point, e.g '#00FFFF'
    });

    // plugin jquery-ui
    $(".tabs").tabs();
    $(".accordion").accordion();
    $(".button").button();
    $(".radioset").buttonset();
    $(".datepicker").datepicker({inline: true});
    $(".tooltip").tooltip();
    $(".selectmenu").selectmenu();

});