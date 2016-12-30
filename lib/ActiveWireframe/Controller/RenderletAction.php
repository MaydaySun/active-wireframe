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
namespace ActiveWireframe\Controller;

use ActivePublishing\Service\File;
use ActivePublishing\Tool;
use ActiveWireframe\Plugin;
use Pimcore\Config;
use Pimcore\Model\Document;
use Pimcore\Model\Object;
use Website\Controller\Action;

/**
 * Class RenderletAction
 *
 * @package ActiveWireframe\Controller
 */
class RenderletAction extends Action
{
    /**
     * @var string
     */
    public $_language;

    /**
     * @var int
     */
    public $_document;

    /**
     * @var int
     */
    public $_objectId;

    /**
     * @var Object\AbstractObject
     */
    public $_object;

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
     * @var string
     */
    public $_thumbnail;

    public function init()
    {
        parent::init();

        // For object renderlet
        if (!$this->hasParam('id')) {
            echo "Object ID failed."; exit();
        }

        if (!$object = Object\Concrete::getById($this->getParam('id'))) {
            echo "Object failed."; exit();
        }

        if(!$object->isPublished()) {
            exit();
        }

        if ($this->hasParam('documentId')) {
            $this->_document = Document::getById(intval($this->getParam('documentId')));
        } elseif ($this->hasParam('pimcore_parentDocument')) {
            $this->_document = Document::getById(intval($this->getParam('pimcore_parentDocument')));
        } else {
            echo "Document ID failed."; exit();
        }

        $this->view->object = $this->_object = $object;
        $this->view->objectId = $this->_objectId = intval($object->getId());
        $this->view->htmlId = $object->getKey() . "-" . $object->getId();

        $this->_pluginsDataDocument = Plugin::PLUGIN_PATH_DATA . DIRECTORY_SEPARATOR . $this->_document->getId();

        $this->_pathElementW2pObject = $this->_pluginsDataDocument . DIRECTORY_SEPARATOR . $this->_objectId;

        // language
        $this->_language = $this->language;

        // send var in view
        $this->view->baseHost = $this->getHostUrl();
        $this->view->baseAssets = PIMCORE_ASSET_DIRECTORY;

        // Config Thumbnail
        $this->view->thumbnail = $this->_thumbnail = $this->hasParam('thumbnail') ?
            $this->getParam('thumbnail') :
            "active-wireframe-preview";

        // Data element w2p
        $this->getDataElementW2p();
    }

    /**
     * @return mixed|string
     */
    private function getHostUrl()
    {
        $web2printConfig = Config::getWeb2PrintConfig();
        if ($web2printConfig->wkhtml2pdfHostname != "") {
            return $web2printConfig->wkhtml2pdfHostname;
        }

        return Tool::getHostUrl();
    }

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

                $this->view->dataElementsW2p = $this->_dataElementsW2p = $dataElementsW2p;
            }
        }
    }

}