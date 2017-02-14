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
 * @package ActiveWireframe\Controller
 */
class RenderletAction extends Action
{
    /**
     * @var string
     */
    public $_language = "fr";

    /**
     * @var null
     */
    public $_document = null;

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

    /**
     * Overwritte
     */
    public function init()
    {
        parent::init();

        if (!$this->hasParam('id')) {
            exit("Object ID failed.");
        }

        if (!$object = Object\Concrete::getById($this->getParam('id'))) {
            exit("Object failed.");
        }

        if(!$object->isPublished()) {
            exit();
        }

        $this->_language = $this->language;
        $this->view->baseHost = $this->getHostUrl();
        $this->view->baseAssets = PIMCORE_ASSET_DIRECTORY;

        // Config Thumbnail
        $this->view->thumbnail = $this->_thumbnail = $this->hasParam('thumbnail')
            ? $this->getParam('thumbnail')
            : "active-wireframe-preview";

        if ($this->hasParam('documentId')) {
            $this->_document = Document::getById(intval($this->getParam('documentId')));
        } elseif ($this->hasParam('pimcore_parentDocument')) {
            $this->_document = Document::getById(intval($this->getParam('pimcore_parentDocument')));
        }

        $this->view->object = $this->_object = $object;
        $this->view->objectId = $this->_objectId = intval($object->getId());
        $this->view->htmlId = $object->getKey() . "-" . $object->getId();

        // Uniquement pour les document printpage (données xy des blocs w2p-element)
        if (!is_null($this->_document)) {
            $this->_pluginsDataDocument = Plugin::PLUGIN_WEBSITE_PATH . DIRECTORY_SEPARATOR . $this->_document->getId();
            $this->_pathElementW2pObject = $this->_pluginsDataDocument . DIRECTORY_SEPARATOR . $this->_objectId;
            $this->getDataElementW2p();
        }
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

    /**
     * Récupère les positions xy des w2p-element
     */
    private function getDataElementW2p()
    {
        if (is_dir($this->_pathElementW2pObject)) {

            $dataElementsW2p = [];
            $ls = File::ls($this->_pathElementW2pObject);
            if (!empty($ls)) {

                foreach ($ls as $file) {

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