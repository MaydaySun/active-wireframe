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

    /**
     * Zoom la page
     * @param event
     */
    function changeZoom(event) {

        // Stop
        event.stopPropagation();
        event.preventDefault();

        // var clsIndicator
        var clsIndicator = $("#indicatorZoom");

        // Nouvelle valeur du zoome
        var scaleChange = ($(this).attr("id") === "zoomUp")
            ? parseFloat(clsIndicator.attr('data-scale')) + 0.1
            : parseFloat(clsIndicator.attr('data-scale')) - 0.1;

        // Ecriture de la nouvelle valeur du zoom
        clsIndicator.attr('data-scale', scaleChange);
        clsIndicator.html('(' + (100 * scaleChange).toFixed(2) + '%)');

        // Modification de la valeur CSS scale
        $("#main-section-page").css({
            "transform": "scale(" + scaleChange + ")",
            "-webkit-transform": "scale(" + scaleChange + ")",
            "-moz-transform": "scale(" + scaleChange + ")",
            "-ms-transform": "scale(" + scaleChange + ")"
        });
    }

    // Evenement pour zoomé la page
    $("#zoomUp, #zoomDown").on("click", changeZoom);

    /**
     * Change les classes CSS pour modifier l'appercut des vignettes
     * @param event
     */
    function changeViewThumbs(event) {
        // Stop
        event.stopPropagation();
        event.preventDefault();

        // Modification de la classe active
        $('.position-thumbs-active').removeClass('position-thumbs-active');
        $(this).addClass('position-thumbs-active');

        // Modification de la classe "position" sur la balise section principal
        $('.position-thumbs').each(function () {

            var oldClassThumb = $(this).attr('data-position');
            $("#main-section-tree").removeClass(oldClassThumb);

        });
        var newClass = $(this).attr('data-position');
        $("#main-section-tree").addClass(newClass);
    }

    // Positions des vignettes pour le document BAT
    $('.position-thumbs').on('click', changeViewThumbs);

    // Initialise le type d'apperçut des vignettes
    $('.position-first').trigger('click');

    /**
     * Génère une nouvelle pagination
     * @param event
     */
    function createNewPagination(event) {
        // Stop
        event.stopPropagation();
        event.preventDefault();

        // Loading
        pimcore.helpers.loadingShow();

        $.ajax({
            url: "/plugin/ActiveWireframe/menu/generate-pagination",
            type: "POST",
            cache: false,
            dataType: 'json',
            data: {
                index: $('#inputIndexPaginator').val(),
                documentId: $documentId
            },
            complete: function () {

                // Rechargement de l'arbre
                var store = pimcore.globalmanager.get("layout_document_tree").tree.getStore();
                store.load({node: store.findRecord("id", 1)});

                // Ferme le document et le réouvre -> prendre en compte le changement de controlleur
                pimcoreOnUnload();

                // Supprime le loading
                pimcore.helpers.loadingHide();

            }
        });
    }

    var dialog = $("#dialog-form-pagination").dialog({
        autoOpen: false,
        height: "auto",
        modal: true,
        buttons: {
            Cancel: function () {
                dialog.dialog("close");
            },
            Ok: createNewPagination
        }
    });

    // Pagination
    $("#btn-pagination").on("click", function (event) {
        event.preventDefault();
        dialog.dialog("open");
    });

    /**
     * Génération des apercut
     */
    function reloadThumbnailTree() {
        $.ajax({
            url: "/plugin/ActiveWireframe/menu/reload-catalog",
            type: "GET",
            cache: false,
            dataType: 'json',
            data: {
                documentId: $documentId
            },

            complete: function () {

                // Supprime le loading
                pimcore.helpers.loadingHide();

                // Rechargement
                pimcoreOnUnload();

            }
        });
    }

    // Génération des apperçut du BAT
    $("#reload-catalog").on('click', function (event) {
        // Stop
        event.stopPropagation();
        event.preventDefault();

        // Loading
        pimcore.helpers.loadingShow();

        // Génération des PDF
        reloadThumbnailTree();

    });

    /**
     * Affiche les repères de la pages
     */
    function showSliders() {

        // Supprime les repère
        if ($(this).hasClass('page-target-on')) {

            $('#slider-range-h').fadeOut(150);
            $('#slider-range-v').fadeOut(150);
            $(this).removeClass('page-target-on');
            $('body').removeClass('target-visible');
            $(this).children("[data-target = 0]").addClass('is-hidden');
            $(this).children("[data-target = 1]").removeClass('is-hidden');

            // Ajoute les répère
        } else {

            $('body').addClass('target-visible');
            $(this).addClass('page-target-on');
            $(this).children("[data-target = 1]").addClass('is-hidden');
            $(this).children("[data-target = 0]").removeClass('is-hidden');
            $('#slider-range-h').fadeIn(150);
            $('#slider-range-v').fadeIn(150);
        }
    }

    // Evenement sur l'affichages des repères
    var targetSliders = $(".page-target");
    targetSliders.on('click', showSliders);
    targetSliders.trigger('click');

});