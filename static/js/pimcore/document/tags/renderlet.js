/**
 * Active Publishing
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2014-2016 Active Publishing http://www.activepublishing.fr
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License version 3 (GPLv3)
 */

pimcore.document.tags.renderlet = Ext.extend(pimcore.document.tags.renderlet, {

    updateDimensions: function () {
        if (this.options.webtoprint) {
            var self = this;

            var frag = this.getBody().dom,
                images = [],
                i = 0;

            function imageLoaded() {
                ++i;
                if (images.ready && i == images.length) {
                    $('div[id="' + self.getBody().id + '"]').ready(function () {
                        var element = $('div[id="' + self.getBody().id + '"]');
                        var width = element.find('.w2p-renderlet').width();
                        var height = element.find('.w2p-renderlet').height();

                        var elBoxW2p = element.parent().parent().parent().parent().parent().parent();
                        elBoxW2p.css("width", width + "px").css("height", height + "px");

                        self.getBody().setStyle({
                            height: height + "px",
                            width: width + "px"
                        });
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

        } else {
            this.getBody().setStyle({
                height: "auto"
            });
        }
    },

    onContextMenu: function (e) {
        var menu = new Ext.menu.Menu();
        var self = this;

        if(this.data["id"]) {

            // get allowed areas
            var url = '/plugin/ActiveWireframe/pages/get-areas-listing';
            this.sendAjax(url, null, 'GET', false, function(store) {

                // The data store containing the list of states
                var dataStore = Ext.create('Ext.data.Store', {
                    fields: ['id', 'name'],
                    sorters: 'name',
                    data : store
                });

                var subMenu = [];
                dataStore.each(function(record) {

                    var oId = self.data['id'];
                    var dId = self.data['documentId'];
                    var oldArea = self.name;
                    var newArea = record.data.id;

                    subMenu.push({
                        text: record.data.name,
                        iconCls: "pimcore_icon_clone",
                        handler: function () {
                            self.updateArea(oId, dId, oldArea, newArea);
                        }
                    });
                });

                menu.add(new Ext.menu.Item({
                    text: "Changer de Template",
                    iconCls: "pimcore_icon_printpage",
                    menu: subMenu
                }));

            });

            menu.add(new Ext.menu.Item({
                text: t('empty'),
                iconCls: "pimcore_icon_delete",
                handler: function () {
                    var height = this.options.height;
                    if (!height) {
                        height = this.defaultHeight;
                    }
                    this.data = {};
                    this.getBody().update('');
                    this.getBody().insertHtml("beforeEnd",'<div class="pimcore_tag_droptarget"></div>');
                    this.getBody().addCls("pimcore_tag_snippet_empty");
                    this.getBody().setHeight(height + "px");

                    if (this.options.reload) {
                        this.reloadDocument();
                    }

                }.bind(this)
            }));

            menu.add(new Ext.menu.Item({
                text: t('open'),
                iconCls: "pimcore_icon_open",
                handler: function () {
                    if(this.data.id) {
                        pimcore.helpers.openElement(this.data.id, this.data.type, this.data.subtype);
                    }
                }.bind(this)
            }));

            if (pimcore.elementservice.showLocateInTreeButton("document")) {
                menu.add(new Ext.menu.Item({
                    text: t('show_in_tree'),
                    iconCls: "pimcore_icon_show_in_tree",
                    handler: function (item) {
                        item.parentMenu.destroy();
                        pimcore.treenodelocator.showInTree(this.data.id, this.data.type);
                    }.bind(this)
                }));
            }
        }

        menu.add(new Ext.menu.Item({
            text: t('search'),
            iconCls: "pimcore_icon_search",
            handler: function (item) {
                item.parentMenu.destroy();

                this.openSearchEditor();
            }.bind(this)
        }));

        menu.showAt(e.getXY());

        e.stopEvent();
    },

    /**
     * Change Area renderlet
     *
     * @param objectId
     * @param documentId
     * @param oldArea
     * @param newArea
     */
    updateArea: function (objectId, documentId, oldArea, newArea ) {
        var self = this;

        // get allowed areas
        var url = '/plugin/ActiveWireframe/pages/set-area?oId=' + objectId + '&dId=' + documentId + '&oldArea='
            + oldArea + '&newArea=' + newArea;

        this.sendAjax(url, null, 'GET', false, function (response) {
            if (response.success) {
                pimcore.helpers.closeDocument(documentId);
                pimcore.helpers.openDocument(documentId, 'printpage')
            } else {
                Ext.Msg.alert(t('error'), response.msg);
            }
        });
    },

    /**
     * Send Ajax request
     *
     * @param url
     * @param params
     * @param method
     * @param async
     * @param callback
     */
    sendAjax: function (url, params, method, async, callback) {

        if (!Ext.isFunction(callback)) {
            callback = function() {}
        }

        Ext.Ajax.request({
            url: url,
            method: method,
            params: params,
            async: async,
            success: function(response, opts) {
                callback(Ext.decode(response.responseText));
            },
            failure: function(response, opts) {
                console.log('server-side failure with status code ' + response.status);
            }
        });

    }

});
