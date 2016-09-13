<?php
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
use ActivePublishing\Services\Response;
use ActiveWireframe\Db\Elements;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Model\Document\Printpage;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_ElementsController
 */
class ActiveWireframe_ElementsController extends Action
{

    public function init()
    {
        parent::init();
        $this->disableLayout();
        $this->disableViewAutoRender();
        $this->disableBrowserCache();

        if (!Plugin::composerExists()
            or !Plugin::isInstalled()
        ) {
            exit();
        }
    }

    /**
     * Save box-w2p
     * @return int
     */
    public function saveBoxW2pAction()
    {
        // Retrieve elements for saving
        if ($this->hasParam('id') and $this->hasParam('elements') and !empty($this->getParam('elements'))) {

            // Instance
            $dbPages = new Pages();
            $dbElement = new Elements();

            $id_document = $this->getParam("id");
            $document = Printpage::getById($id_document);

            // Delete informations
            $dbElement->deleteByKey('document_id', $document->getId());

            // Retrieve catalog
            $cinfo = $dbPages->getCatalogByDocumentId($document->getId());

            // Elements to encode
            $elements = \json_decode($this->getParam('elements'));
            foreach ($elements as $element) {

                $dbElement->insert([
                    'o_id' => $element->oId,
                    'document_id' => $id_document,
                    'document_parent_id' => $document->getParentId(),
                    'document_root_id' => $cinfo['document_id'],
                    'page_key' => $document->getKey(),
                    'e_id' => 0,
                    'e_key' => $element->key,
                    'e_top' => Helpers::convertPxToMm($element->top),
                    'e_left' => Helpers::convertPxToMm($element->left),
                    'e_index' => $element->index,
                    'e_width' => Helpers::convertPxToMm($element->width),
                    'e_height' => Helpers::convertPxToMm($element->height),
                    'e_transform' => $element->mat
                ]);
            }

        }

        return Response::setResponseJson([
            "success" => true
        ]);
    }

    /**
     * Save element-w2p
     */
    public function saveElementW2pAction()
    {
        if ($this->hasParam('id') and $this->hasParam('elements') and !empty($this->getParam('elements'))) {

            $documentId = intval($this->getParam("id"));

            // Dir
            $dir = PIMCORE_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'activetmp/' . Plugin::PLUGIN_NAME;
            if (!file_exists($dir)) {
                Pimcore\File::mkdir($dir, 0775, true);
            }

            // Document ID
            $document = Printpage::getById($documentId);
            if ($document instanceof Printpage) {

                // Dir document
                $dirDocument = $dir . DIRECTORY_SEPARATOR . $documentId;
                if (!file_exists($dirDocument)) {
                    Pimcore\File::mkdir($dirDocument, 0775, true);
                }

                $elements = \json_decode($this->getParam('elements'));

                foreach ($elements as $element) {

                    $oId = $element->oId;
                    $key = $element->key;

                    // Object ID OK
                    if (ctype_digit($oId)) {

                        // Dir element
                        $dirElement = $dirDocument . DIRECTORY_SEPARATOR . $oId . DIRECTORY_SEPARATOR
                            . 'element-w2p-' . $key . '.json';

                        // Delete json file
                        if (file_exists($dirElement)) {
                            @unlink($dirElement);
                        }

                        // Retrieve data
                        $data = array(
                            'o_id' => $oId,
                            'e_key' => $key,
                            'e_top' => Helpers::convertPxToMm($element->top),
                            'e_left' => Helpers::convertPxToMm($element->left),
                            'e_index' => $element->index,
                            'e_width' => Helpers::convertPxToMm($element->width),
                            'e_height' => Helpers::convertPxToMm($element->height),
                            'e_transform' => $element->mat
                        );

                        Pimcore\File::put($dirElement, \Zend_Json_Encoder::encode($data));
                    }

                }

            }

        }

        return Response::setResponseJson([
            "success" => true
        ]);
    }
}
