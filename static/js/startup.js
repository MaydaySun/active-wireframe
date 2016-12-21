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

    preSaveDocument: function (document) {
        if ((document.data.module == "ActiveWireframe") && ((document.data.action == "pages"))) {
            this.saveBoxW2p(document);
        }
    },

    postSaveDocument: function (document) {
        if ((document.data.module == "ActiveWireframe") && ((document.data.action == "pages"))) {
            Ext.Ajax.request({
                url: '/plugin/ActiveWireframe/pages/get-thumbnail',
                method: 'GET',
                cache: false,
                params: {
                    documentId: document.id
                },
                success: function (xhr) {}
            });
        }
    },

    saveBoxW2p: function (document) {
        var documentDom = Ext.get('document_iframe_' + document.id);
        var iframe = documentDom.dom.contentWindow.Ext;
        var page = iframe.get('page');
        var boxW2p = iframe.select('.box-w2p');
        var data = [];

        boxW2p.each(function (box) {
            data.push(this.getData(box, page));
        }.bind(this));

        Ext.Ajax.request({
            url: '/plugin/ActiveWireframe/elements/save-box-w2p',
            method: 'GET',
            cache: false,
            params: {
                id: document.id,
                box: Ext.util.JSON.encode(data)
            },
            success: function (xhr) {
                var response = Ext.util.JSON.decode(xhr.responseText);
                if (!response.success) {
                    Ext.Msg.alert(t('error'), response.message);
                }
            }
        });
    },

    getData: function (bData, page) {
        var boxW2p = {};
        boxW2p.key = bData.getAttribute('data-key');
        boxW2p.top = bData.getStyle('top');
        boxW2p.left = bData.getStyle('left');
        boxW2p.index = bData.getStyle('z-index');
        boxW2p.width = bData.getStyle('width');
        boxW2p.height = bData.getStyle('height');
        boxW2p.mat = bData.getStyle('transform');
        boxW2p.oId = 0;
        boxW2p.elements = [];

        if (bData.select('.w2p-renderlet').elements.length !== 0) {
            boxW2p.oId = bData.select('.w2p-renderlet').elements[0].getAttribute('data-o-id');
        }

        if (bData.select('.w2p-element').elements.length !== 0) {
            var elementW2p = bData.select('.w2p-element');

            elementW2p.each(function (eData) {
                var dataElement = {};
                dataElement.key = eData.getAttribute('data-element-key');
                dataElement.top = eData.getStyle('top');
                dataElement.left = eData.getStyle('left');
                dataElement.index = eData.getStyle('z-index');
                dataElement.width = eData.getStyle('width');
                dataElement.height = eData.getStyle('height');
                dataElement.mat = eData.getStyle('transform');
                boxW2p.elements.push(dataElement)
            });
        }

        return boxW2p;
    }

});

var activewireframePlugin = new pimcore.plugin.activewireframe();

