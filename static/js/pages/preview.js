$(document).ready(function() {

    function updateDimensions(frag, id) {
        var images = [],
            i = 0;

        function imageLoaded() {
            ++i;
            if (images.ready && i == images.length) {
                $('div[id="' + id + '"]').ready(function () {
                    var element = $('div[id="' + id + '"]');
                    var elBoxW2p = element.parent().parent().parent();
                    elBoxW2p.css("width", element.width() + "px").css("height",  element.height() + "px");
                });
            }
        }

        $(frag).find('img').each(function() {
            var i = new Image();
            i.onload = i.onerror = imageLoaded;
            i.src = this.src;
            images[images.length] = i;
        });
        images.ready = true;
    }

    $(document).find('.w2p-renderlet').each(function() {
        updateDimensions(this, this.id);
    })

});