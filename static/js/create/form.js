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

    var $documentId = $('#documentId').val();
    var $selectFormat = $('#selectFormat');
    var $selectOrientation = $('#selectOrientation');

    /**
     * Récupère les vignettes des templates
     * @param {type} f
     * @param {type} o
     * @returns {undefined}
     */
    function getThumbsTemplates(f, o) {

        // classe
        var thumbCls = $('#fieldThumbnails');

        // Vide les vignettes
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

                    // Htlm des vignettes
                    var strTemplates = '';

                    // Création des listes
                    for (var i = 0; i <= files.length - 1; i++) {

                        // Vignettes + Id de l'asset
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
                            ts('active_wireframe_page_even') +
                            '</label>' +
                            '</div>' +
                            '</div>';

                    }

                    // Ajoute les templates
                    thumbCls.append(strTemplates);

                }

            }

        });
    }

    // Changement de format
    $selectFormat.on('selectmenuchange', function () {

        if ($(this).val() === 'null') {

            $('#formatMan').slideDown();
            $('#selectOrientation').val("auto").selectmenu('refresh');


        } else {
            $('#formatMan').slideUp();
        }

        var f = $(this).find(':selected').data('template');
        var o = $selectOrientation.find(':selected').val();

        // Récupère les vignettes des templates
        getThumbsTemplates(f, o);
    });

    // Changement de l'orientation
    $selectOrientation.on('selectmenuchange', function () {

        var f = $('#selectFormat').find(':selected').data('template');
        var o = $(this).find(':selected').val();
        getThumbsTemplates(f, o);

    });

    // 1ère initalisation de template
    var fInit = $selectFormat.find(':selected').data('template');
    var oInit = $selectOrientation.find(':selected').val();
    getThumbsTemplates(fInit, oInit);

    /**
     * prepareUpload
     * @param event
     */
    var files = false;
    var prepareUpload = function (event) {
        files = event.target.files;
    };

    // Upload de fichiers
    $('#fileToUpload').on('change', prepareUpload);

    // Soumission du formulaire
    $("#btnSubmit").on('click', function (event) {
        event.preventDefault();

        uploadFiles();
    });

    /**
     * uploadFiles
     */
    function uploadFiles() {

        // LOADING
        pimcore.helpers.loadingShow();

        // Create a formdata object and add the files
        var data = new FormData();
        if (files.hasOwnProperty('length')) {
            $.each(files, function (key, value) {
                data.append(key, value);
            });
        }

        $.ajax({
            url: '/plugin/ActiveWireframe/create/upload-files',
            type: 'POST',
            cache: false,

            processData: false,
            dataType: 'json',
            data: data,

            contentType: false,

            success: function (data, textStatus) {
                // L'upload c'est bien passé
                if (data.success) {
                    submitForm(data);
                } else {
                    pimcore.helpers.showNotification(t('error'), 'ERRORS: ' + textStatus, 'error');
                    pimcore.helpers.loadingHide();
                }
            },

            error: function (jqXHR, textStatus) {
                pimcore.helpers.showNotification(t('error'), 'ERRORS: ' + textStatus, 'error');
                pimcore.helpers.loadingHide();
            }

        });
    }

    /**
     * Soumet le formulaire de création
     * @param data
     */
    function submitForm(data) {
        // Create a jQuery object from the form
        var $form = $('#form');

        // Serialize the form data
        var formData = $form.serialize() + '&documentId=' + $documentId;

        // You should sterilise the file names
        if (data.files !== '') {
            if (data.files.hasOwnProperty('length')) {
                $.each(data.files, function (key, value) {
                    formData = formData + '&filenames[]=' + value;
                });
            }
        }

        $.ajax({
            url: '/plugin/ActiveWireframe/create/catalog',
            type: 'POST',
            cache: false,

            dataType: 'json',
            data: formData,

            success: function (data) {
                // Catalogue crée
                if (data.success) {
                    pimcore.helpers.showNotification(t('success'), data.msg);
                } else {
                    // Affiche un message d'erreur
                    pimcore.helpers.showNotification(t('error'), data.msg, 'error');
                }
            },

            error: function (jqXHR, textStatus) {
                pimcore.helpers.showNotification(t('error'), 'ERRORS: ' + textStatus, 'error');
            },

            complete: function () {
                // Rechargement de l'arbre
                var store = pimcore.globalmanager.get("layout_document_tree").tree.getStore();
                store.load({node: store.findRecord("id", 1)});

                // Ferme le document et le réouvre -> prendre en compte le changement de controlleur
                pimcore.helpers.closeDocument($documentId);
                pimcore.helpers.openDocument($documentId, 'printcontainer');

                // Supprime le loader
                pimcore.helpers.loadingHide();
            }
        });
    }

    // changement d'etape de création
    $('.btn-nav-role').on('click', function (event) {
        event.preventDefault();

        var id = $(this).attr('data-nav-id');
        $('#nav-' + id).trigger('click');
        $('#scrollUp').trigger('click');

    });

    // Création d'un chapitre ou page dans le formulaire
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

    // Supprime une page ou un chapitre
    $(document).on("click", ".close-page-chap", function () {
        $(this).parent().remove();
    });

});