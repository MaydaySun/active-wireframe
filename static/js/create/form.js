$(document).ready(function () {

    var $documentId = $('#documentId').val();
    var $selectFormat = $('#selectFormat');
    var $selectOrientation = $('#selectOrientation');

    /**
     * Get templates thumbnail
     * @param {type} f
     * @param {type} o
     * @returns {undefined}
     */
    function getThumbsTemplates(f, o) {

        var thumbCls = $('#fieldThumbnails');
        thumbCls.empty();

        $.ajax({
            url: '/plugin/ActiveWireframe/create/get-templates',
            type: "GET",
            cache: false,

            dataType: "json",
            data: {
                format: f,
                orientation: o,
                documentId: $documentId
            },

            success: function (data) {

                var $blocChooseTemplate = $('#bloc-choose-template');

                if (!data.success) {

                    $blocChooseTemplate.find('.alert-info').hide();
                    $blocChooseTemplate.find('.alert-warning').show();

                } else if (Array.isArray(data.templates) && !empty(data.templates)) {

                    $blocChooseTemplate.find('.alert-info').show();
                    $blocChooseTemplate.find('.alert-warning').hide();

                    // files
                    var files = data.templates;
                    var strTemplates = '';

                    for (var i = 0; i <= files.length - 1; i++) {

                        var fileThumb = files[i]["thumb"];
                        var fileId = files[i]["id"];

                        strTemplates += '<div>' +
                            '<img src="' + fileThumb + '" alt="template" class="img-thumbnail"/>' +
                            '<div class="radio">' +
                            '<label>' +
                            '<input type="radio" name="template-even" value="' + fileId + '">' +
                            ts('active_wireframe_page_even') +
                            '</label>' +
                            '</div>' +
                            '<div class="radio">' +
                            '<label>' +
                            '<input type="radio" name="template-odd" value="' + fileId + '">' +
                            ts('active_wireframe_page_odd') +
                            '</label>' +
                            '</div>' +
                            '</div>';

                    }

                    thumbCls.append(strTemplates);
                }

            }

        });
    }

    // update format
    $selectFormat.on('selectmenuchange', function () {

        if ($(this).val() === 'null') {

            $('#formatMan').slideDown();
            $('#selectOrientation').val("auto").selectmenu('refresh');

        } else {
            $('#formatMan').slideUp();
        }

        var f = $(this).find(':selected').data('template');
        var o = $selectOrientation.find(':selected').val();
        getThumbsTemplates(f, o);

    });

    // Update orientation
    $selectOrientation.on('selectmenuchange', function () {
        var f = $('#selectFormat').find(':selected').data('template');
        var o = $(this).find(':selected').val();
        getThumbsTemplates(f, o);
    });

    // Init template
    var fInit = $selectFormat.find(':selected').data('template');
    var oInit = $selectOrientation.find(':selected').val();
    getThumbsTemplates(fInit, oInit);

    // Submit
    $("#btnSubmit").on('click', submitForm);

    /**
     * Submit form for creation of catalog
     */
    function submitForm() {
        $('#form').submit(function(e) {

            // Serialize the form data
            var data = new FormData(this);
            data.append('documentId', $documentId);

            $.ajax({
                url: '/plugin/ActiveWireframe/create/catalog',
                type: 'POST',
                cache: false,
                dataType: 'json',
                data: data,
                processData: false,
                contentType: false,

                beforeSend: function (jqXHR, settings) {
                    pimcore.helpers.loadingShow();
                },

                success: function (data, textStatus, jqXHR) {
                    if (data.success) {
                        pimcore.helpers.showNotification(t('success'), data.msg);
                    } else {
                        pimcore.helpers.showNotification(t('error'), data.msg, 'error');
                    }
                },

                error: function (jqXHR, textStatus) {
                    pimcore.helpers.showNotification(t('error'), 'ERRORS: ' + textStatus, 'error');
                },

                complete: function () {
                    // Reload tree
                    var store = pimcore.globalmanager.get("layout_document_tree").tree.getStore();
                    store.load({node: store.findRecord("id", 1)});

                    // close and open document
                    pimcore.helpers.closeDocument($documentId);
                    pimcore.helpers.openDocument($documentId, 'printcontainer');
                    pimcore.helpers.loadingHide();
                }
            });
            e.preventDefault();
        })
    }

    // Next and back creation step
    $('.btn-nav-role').on('click', function (event) {
        event.preventDefault();
        var id = $(this).attr('data-nav-id');
        $('#nav-' + id).trigger('click');
        $('#scrollUp').trigger('click');
    });

    // Event for chapter creation or page
    var contentChapter = $('.divAddChapter > article').clone(true);
    var contentPage = $('.divAddPages > article').clone(true);

    $(".btn-action").on("click", function () {

        var action = $(this).attr("data-btn-container");
        if (action === "chapter") {
            var chapterCopy = contentChapter.clone(true);
            chapterCopy.appendTo(".divAddChapter");
        } else if (action === "page") {
            var pageCopy = contentPage.clone(true);
            pageCopy.appendTo(".divAddPages");
        }
        return false;

    });

    // Delete page or chapter
    $(document).on("click", ".close-page-chap", function () {
        $(this).parent().remove();
    });

});