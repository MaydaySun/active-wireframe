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

            $(document).ready(function () {
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

        } else {
            this.getBody().setStyle({
                height: "auto"
            });
        }
    }
});
