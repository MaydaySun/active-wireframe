<?php
/**
 * Active Publishing
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2014-2016 Active Publishing http://www.activepublishing.fr
 * @license https://www.gnu.org/licenses/gpl-3.0.en.html GNU General Public License version 3 (GPLv3)
 */

namespace ActiveWireframe\Controllers;

use ActivePublishing\Service\Extension;
use ActivePublishing\Service\File;
use ActivePublishing\Service\Tool;
use ActiveWireframe\Plugin;
use Pimcore\Model\Object;
use Website\Controller\Action;

/**
 * Class WireframeAction
 *
 * @package ActiveWireframe\Controllers
 */
class WireframeAction extends Action
{
    /**
     * @var string
     */
    public $_language;

    /**
     * @var int
     */
    public $_documentId;

    /**
     * @var int
     */
    public $_objectId;

    /**
     * @var string
     */
    public $_pluginsDataDocument;

    /**
     * @var string
     */
    public $_pathElementW2pObject;

    /**
     * @var array
     */
    public $_dataElementW2p;

    /**
     * Init overwritte
     */
    public function init()
    {
        parent::init();
        $this->disableBrowserCache();

        $this->_documentId = intval($this->getDocument()->getId());
        $this->_pluginsDataDocument = Plugin::PLUGIN_PATH_DATA . DIRECTORY_SEPARATOR . $this->_documentId;

        // language
        $this->_language = $this->language;

        // send var in view
        $this->view->baseHost = Tool::getHostUrl();
        $this->view->baseAssets = PIMCORE_ASSET_DIRECTORY;

        // Config Thumbnail
        if ($this->hasParam('thumbnail')) {
            $this->view->thumbnail = unserialize($this->getParam('thumbnail'));
        }

        // For object renderlet
        if ($this->hasParam('id')) {
            if (Object\Concrete::getById($this->getParam('id'))) {
                $this->_objectId = intval($this->getParam('id'));
                $this->_pathElementW2pObject = $this->_pluginsDataDocument . DIRECTORY_SEPARATOR . $this->_objectId;

                if (Extension::getInstance(Plugin::PLUGIN_NAME)->check()) {
                    $this->getDataElementW2p();
                }

            }
        }
    }

    /**
     * Create an array that contains the data element-w2p
     */
    private function getDataElementW2p()
    {
        if (is_dir($this->_pathElementW2pObject)) {

            $dataElementsW2p = [];
            $ls = File::ls($this->_pathElementW2pObject);
            if (!empty($ls)) {

                foreach ($ls as $file) {

                    // path file json
                    $pathFileJson = $this->_pathElementW2pObject . DIRECTORY_SEPARATOR . $file;
                    $content = \Zend_Json::decode(file_get_contents($pathFileJson));

                    $dataElementsW2p[$content['e_key']] = [
                        'position' => "absolute",
                        'top' => number_format($content['e_top'], 2) . 'mm',
                        'left' => number_format($content['e_left'], 2) . 'mm',
                        'z-index' => $content['e_index'],
                        'width' => number_format($content['e_width'], 2) . 'mm',
                        'height' => number_format($content['e_height'], 2) . 'mm',
                        'transform' => $content['e_transform']
                    ];

                }

                $this->_dataElementsW2p = $dataElementsW2p;
            }
        }
    }

}