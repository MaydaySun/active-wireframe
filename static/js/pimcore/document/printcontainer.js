/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

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
pimcore.registerNS("pimcore.document.printcontainer");
pimcore.document.printcontainer = Class.create(pimcore.document.printabstract, {

    urlprefix: "/admin/",
    type: "printcontainer",

    init: function () {

        // Add edit mode
        this.edit = new pimcore.document.edit(this);

        if (this.isAllowed("settings")) {
            this.settings = new pimcore.document.snippets.settings(this, "printpage");
            this.notes = new pimcore.element.notes(this, "document");
        }
        if (this.isAllowed("properties")) {
            this.properties = new pimcore.document.properties(this, "document");
        }
        if (this.isAllowed("versions")) {
            this.versions = new pimcore.document.versions(this);
        }

        this.pdfpreview = new pimcore.document.printpages.pdfpreview(this);
    },

    getTabPanel: function () {
        var items = [];
        items.push(this.edit.getLayout());
        items.push(this.pdfpreview.getLayout());
        if (this.isAllowed("settings")) {
            items.push(this.settings.getLayout());
        }
        if (this.isAllowed("properties")) {
            items.push(this.properties.getLayout());
        }
        if (this.isAllowed("versions")) {
            items.push(this.versions.getLayout());
        }

        if (this.isAllowed("settings")) {
            items.push(this.notes.getLayout());
        }

        this.tabbar = new Ext.TabPanel({
            tabPosition: "top",
            region: 'center',
            deferredRender: true,
            enableTabScroll: true,
            defaults: {autoScroll: true},
            border: false,
            items: items,
            activeTab: 0
        });
        return this.tabbar;
    }

});

