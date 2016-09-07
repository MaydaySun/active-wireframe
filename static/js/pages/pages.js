$(document).ready(function () {

    var body = $("#activeWireframe");

    /**
     * Element UI
     * @param element
     */
    function setElementWidgetUi(element) {

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
     * Active w2p box
     */
    function activeBoxW2p() {
        var zindex = 0;

        $('.box-w2p').each(function () {
            if (parseInt($(this).css('z-index')) > zindex) {
                zindex = parseInt($(this).css('z-index'));
            }
        });
        $(this).css({"z-index": zindex + 1});

        // delete css class "selected"
        $('.box-w2p.selected').removeClass('selected');

        // Add css class for the current element
        $(this).addClass('selected');
    }

    /**
     * Active element-w2p
     */
    function activeElementW2p(element) {
        var zindex = 0;

        $('.element-w2p').each(function () {
            if (parseInt($(this).css('z-index')) > zindex) {
                zindex = parseInt($(this).css('z-index'));
            }
        });
        element.css({"z-index": zindex + 1});

        // delete css class "selected"
        $('.element-w2p.selected').removeClass('selected');

        // Add css class for the current element
        element.addClass('selected');
    }

    // Initializaion of widgets box-w2p
    $('.box-w2p').each(function () {
        setElementWidgetUi($(this));
    });

    // Initializaion of widgetss element-w2p
    body.on('click', ".element-w2p", function () {
        setElementWidgetUi($(this));
        activeElementW2p($(this));
    });

    // Delete area active
    body.on('click', '#unselected', function () {
        $('.box-w2p.selected').removeClass('selected');
        $('.element-w2p.selected').removeClass('selected');
    });

    // Activation area
    body.on('click', '.box-w2p', activeBoxW2p);

    /**
     * Active sliders
     * @param id
     * @param name
     * @param o
     */
    // function activeWidgetSliders(id, name, o) {
    // var pt = parseFloat($('input[name="paddingTop"]').val());
    // var pb = parseFloat($('input[name="paddingBottom"]').val());
    // var pl = parseFloat($('input[name="paddingLeft"]').val());
    // var pr = parseFloat($('input[name="paddingRight"]').val());
    //
    // var width = parseFloat($('input[name="pageWidthLandmark"]').val());
    // var height = parseFloat($('input[name="pageHeightLandmark"]').val());
    //
    // var start = pl;
    // var end = parseFloat(width) - pr - 10;
    // var max = parseFloat(width) - 5;
    //
    // if (o === 'vertical') {
    //     start = pb;
    //     end = parseFloat(height) - pt - 10;
    //     max = parseFloat(height) - 5;
    // }
    //
    // var widget = $("#" + id).slider({
    //     orientation: o,
    //     range: true,
    //     min: -5,
    //     max: max,
    //     values: [start, end],
    //     step: 0.5,
    //     // slide: function (event, ui) {
    //     //     widget.children('.ui-slider-handle').addClass(name);
    //     //     $('.' + name + '-0').html(ui.values[0] + "mm");
    //     //     $('.' + name + '-1').html(ui.values[1] + "mm");
    //     // }
    // });
    //
        // Cls
    // widget.children('.ui-slider-handle').addClass(name);
    //
    // // Values
    // $('.' + name).each(function (loop) {
    //     $(this).append('<span class="ui-slider-values ' + name + '-' + loop + '"></span>');
    //     $(this).append('<span class="ui-slider-values ' + name + '-' + loop + '"></span>');
    // });
    //
    // // Init
    // $('.' + name + '-0').html(widget.slider("values", 0) + "mm");
    // $('.' + name + '-1').html(widget.slider("values", 1) + "mm");
    //
    // }
    // activeWidgetSliders('slider-range-h', 'slider-handle-h', 'horizontal');
    // activeWidgetSliders('slider-range-v', 'slider-handle-v', 'vertical');

});