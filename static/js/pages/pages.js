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

    // Element body
    var body = $("#activeWireframe");

    function setElementWidgetUi(element) {
        // Handle
        var handle = (element.children(".poignee").length > 0)
            ? element.children(".poignee")
            : false;

        // Draggable
        element.draggable({
            handle: handle,
            scroll: true,
            cursor: "move",
            opacity: 0.45,
            snapMode: "outer",
            snapTolerance: 10
        });

        // Resizable
        element.resizable({
            handles: 'all'
        });

        // Ratation
        element.rotatable({wheelRotate: false});
    }

    /**
     * Active un box-w2p
     */
    function activeBoxW2p() {

        // Modifie le Z-index
        var zindex = 0;

        $('.box-w2p').each(function () {
            if (parseInt($(this).css('z-index')) > zindex) {
                zindex = parseInt($(this).css('z-index'));
            }
        });
        $(this).css({"z-index": zindex + 1});

        // Supprime la classe 'selected'
        $('.box-w2p.selected').removeClass('selected');
        // $('.element-w2p.selected').removeClass('selected');

        // Ajout de la classe 'selected' à l'élément sélectionné
        $(this).addClass('selected');
    }

    /**
     * Active un element-w2p
     */
    function activeElementW2p(element) {

        // Modifie le Z-index
        var zindex = 0;

        $('.element-w2p').each(function () {
            if (parseInt($(this).css('z-index')) > zindex) {
                zindex = parseInt($(this).css('z-index'));
            }
        });
        element.css({"z-index": zindex + 1});

        // Supprime la classe 'selected'
        $('.element-w2p.selected').removeClass('selected');

        // Ajout de la classe 'selected' à l'élément sélectionné
        element.addClass('selected');
    }

    // Initialise les widgets box-w2p
    $('.box-w2p').each(function () {
        setElementWidgetUi($(this));
    });

    // Initialise les widgets element-w2p
    body.on('click', ".element-w2p", function () {
        setElementWidgetUi($(this));
        activeElementW2p($(this));
    });

    // Désactive les aréas sélectionnées
    body.on('click', '#unselected', function () {
        $('.box-w2p.selected').removeClass('selected');
        $('.element-w2p.selected').removeClass('selected');
    });

    // Activation d'une area lors d'un clique sur celle ci
    body.on('click', '.box-w2p', activeBoxW2p);


    /**
     * Active les règles
     * @param id
     * @param name
     * @param o
     */
    function activeWidgetSliders(id, name, o) {
        // Init
        var pt = parseFloat($('input[name="paddingTop"]').val());
        var pb = parseFloat($('input[name="paddingBottom"]').val());
        var pl = parseFloat($('input[name="paddingLeft"]').val());
        var pr = parseFloat($('input[name="paddingRight"]').val());

        var width = parseFloat($('input[name="pageWidthLandmark"]').val());
        var height = parseFloat($('input[name="pageHeightLandmark"]').val());

        // Ajuste les positions par rapport aux marges
        var start = pl;
        var end = parseFloat(width) - pr - 10;
        var max = parseFloat(width) - 5;

        if (o === 'vertical') {
            // Padding inverssé puisque valeur inverssé
            start = pb;
            end = parseFloat(height) - pt - 10;
            max = parseFloat(height) - 5;
        }

        /**
         * Création des règles
         * @type {*|jQuery}
         */
        var widget = $("#" + id).slider({
            orientation: o,
            range: true,
            min: -5,
            max: max,
            values: [start, end],
            step: 0.5,
            slide: function (event, ui) {
                widget.children('.ui-slider-handle').addClass(name);
                $('.' + name + '-0').html(ui.values[0] + "mm");
                $('.' + name + '-1').html(ui.values[1] + "mm");
            }
        });

        // Cls
        widget.children('.ui-slider-handle').addClass(name);

        // Valeurs
        $('.' + name).each(function (loop) {
            $(this).append('<span class="ui-slider-values ' + name + '-' + loop + '"></span>');
            $(this).append('<span class="ui-slider-values ' + name + '-' + loop + '"></span>');
        });

        // Init
        $('.' + name + '-0').html(widget.slider("values", 0) + "mm");
        $('.' + name + '-1').html(widget.slider("values", 1) + "mm");

    }

    // Initialise les sliders
    activeWidgetSliders('slider-range-h', 'slider-handle-h', 'horizontal');
    activeWidgetSliders('slider-range-v', 'slider-handle-v', 'vertical');

});