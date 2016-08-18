pimcore.registerNS("pimcore.plugin.activewireframe");

pimcore.plugin.activewireframe = Class.create(pimcore.plugin.admin, {

    getClassName: function () {

        return "pimcore.plugin.activewireframe";

    },

    initialize: function () {

        pimcore.plugin.broker.registerPlugin(this);

    },

    pimcoreReady: function (params, broker) {

    },

    /**
     * Enregistre les position X et Y des blocs box-w2p et element-w2p
     * @param document
     */
    preSaveDocument: function (document) {

        if ((document.data.module == "ActiveWireframe") && ((document.data.action == "pages"))) {
            this.saveBoxW2p(document);
            this.saveElementW2p(document);
        }

    },

    /**
     * Enregistrement des box-w2p.
     * @returns {undefined}
     */
    saveBoxW2p: function (document) {

        // Initialisations des tableau
        var aElements = [];

        // Récupère l'iframe
        var documentDom = Ext.get('document_iframe_' + document.id);
        var iframe = documentDom.dom.contentWindow.Ext;

        // Récupère le bloc page et les blocs box-w2p
        var page = iframe.get('page');
        var elems = iframe.select('.box-w2p');

        // Pour chaques bloc box-w2p
        elems.each(function (el) {
            aElements.push(this.getData(el, page, 'box-w2p'));
        }.bind(this));

        // Enregistre les informations de l'élément box-w2p
        Ext.Ajax.request({
            url: '/plugin/ActiveWireframe/elements/save-box-w2p',
            method: 'GET',
            cache: false,
            params: {
                id: document.id,
                elements: Ext.util.JSON.encode(aElements)
            },
            success: function (xhr) {
                var response = Ext.util.JSON.decode(xhr.responseText);
                if (!response.success) {
                    Ext.Msg.alert(t('error'), response.message);
                }
            }
        });
    },

    /**
     * Enregistrement des element-w2p.
     * @returns {undefined}
     */
    saveElementW2p: function (document) {

        // Initialisations des tableau
        var aElements = [];

        // Récupère l'iframe
        var documentDom = Ext.get('document_iframe_' + document.id);
        var iframe = documentDom.dom.contentWindow.Ext;

        // Récupère le bloc page et les blocs box-w2p
        var page = iframe.get('page');
        var elems = iframe.select('.element-w2p');

        // Pour chaques bloc box-w2p
        elems.each(function (el) {
            aElements.push(this.getData(el, page, 'element-w2p'));
        }.bind(this));

        // Enregistre les informations de l'élément box-w2p
        Ext.Ajax.request({
            url: '/plugin/ActiveWireframe/elements/save-element-w2p',
            method: 'GET',
            cache: false,
            params: {
                id: document.id,
                elements: Ext.util.JSON.encode(aElements)
            },
            success: function (xhr) {
                var response = Ext.util.JSON.decode(xhr.responseText);
                if (!response.success) {
                    Ext.Msg.alert(t('error'), response.message);
                }
            }
        });
    },

    /**
     * Récupère les informations de positions et de tailles.
     * @param e
     * @param page
     * @param classe
     * @returns {{}}
     */
    getData: function (e, page, classe) {

        var objectElement = {};

        // Récupère l'id si l'area est une area produit
        if (classe == 'box-w2p') {

            //data-key w2p
            objectElement.key = e.getAttribute('data-key');

            // Récupère l'ID de l'objet placé dans le renderlet
            if (e.select('.object-w2p').elements.length !== 0) {
                objectElement.oId = e.select('.object-w2p').elements[0].getAttribute('data-o-id');
            }

        } else if (classe == 'element-w2p') {

            // data element key w2p
            objectElement.key = e.getAttribute('data-element-key');

            // Récupère l'ID de l'objet placé dans le renderlet
            if (e.parent('.object-w2p') !== null) {
                objectElement.oId = e.parent('.object-w2p').getAttribute('data-o-id');
            }

        }

        // Autres attribut de l'area
        objectElement.top = e.getStyle('top');
        objectElement.left = e.getStyle('left');
        objectElement.index = e.getStyle('z-index');
        objectElement.width = e.getStyle('width');
        objectElement.height = e.getStyle('height');
        objectElement.mat = e.getStyle('transform');
        return objectElement;
    },

    /**
     * postSaveDocument
     * @param document
     */
    postSaveDocument: function (document) {

        if ((document.data.module == "ActiveWireframe") && (document.data.action == "pages")) {
            Ext.Ajax.request({
                url: document.data.path + document.data.key,
                method: 'GET',
                success: function (xhr) {
                }
            });
        }

    }

});

var activewireframePlugin = new pimcore.plugin.activewireframe();

