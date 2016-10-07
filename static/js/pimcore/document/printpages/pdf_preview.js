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

pimcore.document.printpages.pdfpreview = Ext.extend(pimcore.document.printpages.pdfpreview, {

    generatePdf: function () {

        var params = this.generateForm.getForm().getFieldValues();

        this.processingOptionsStore.each(function (rec) {
            params[rec.data.name] = rec.data.value;
        });
        params.id = this.page.id;

        Ext.Ajax.request({
            url: "/plugin/ActiveWireframe/printpage/start-pdf-generation",
            params: params,
            success: function (response) {
                result = Ext.decode(response.responseText);
                if (result.success) {
                    this.checkForActiveGenerateProcess();
                }
            }.bind(this)
        });
    }


});