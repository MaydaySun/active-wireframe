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
use ActivePublishing\Tool;
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

        if (!Plugin::isInstalled()) {
            exit();
        }
    }

    public function saveBoxW2pAction()
    {
        // Retrieve elements for saving
        if ($this->hasParam('id') and $this->hasParam('box') and !empty($this->getParam('box'))) {

            $id_document = $this->getParam("id");
            $document = Printpage::getById($id_document);

            // Delete informations
            $dbElement = Elements::getInstance();
            $dbElement->deleteByKey('document_id', $document->getId());

            // Retrieve catalog
            $cinfo = Pages::getInstance()->getCatalogByDocumentId($document->getId());

            // Elements to encode
            $elements = \json_decode($this->getParam('box'));
            foreach ($elements as $boxW2p) {

                $dbElement->insert([
                    'o_id' => $boxW2p->oId,
                    'document_id' => $id_document,
                    'document_parent_id' => $document->getParentId(),
                    'document_root_id' => $cinfo['document_id'],
                    'page_key' => $document->getKey(),
                    'e_id' => 0,
                    'e_key' => $boxW2p->key,
                    'e_top' => Helpers::convertPxToMm($boxW2p->top),
                    'e_left' => Helpers::convertPxToMm($boxW2p->left),
                    'e_index' => $boxW2p->index,
                    'e_width' => Helpers::convertPxToMm($boxW2p->width),
                    'e_height' => Helpers::convertPxToMm($boxW2p->height),
                    'e_transform' => $boxW2p->mat
                ]);

                if (!empty($boxW2p->elements)) {
                    $this->saveElementW2p($document, $boxW2p->elements, $boxW2p->oId);
                }

            }

        }

        Tool::sendJson(["success" => true]);
    }

    /**
     * @param Printpage $document
     * @param $elementW2p
     * @param $oId
     */
    public function saveElementW2p(Printpage $document, $elementW2p, $oId)
    {
        $dirDocument = Plugin::PLUGIN_WEBSITE_PATH . DIRECTORY_SEPARATOR . $document->getId();
        if (!file_exists($dirDocument)) {
            Pimcore\File::mkdir($dirDocument, 0775, true);
        }

        if (!empty($elementW2p)) {

            foreach ($elementW2p as $element) {

                if (ctype_digit($oId)) {

                    $dirElement = $dirDocument . DIRECTORY_SEPARATOR . $oId . DIRECTORY_SEPARATOR
                        . 'element-w2p-' . $element->key . '.json';

                    if (file_exists($dirElement)) {
                        @unlink($dirElement);
                    }

                    $data = array(
                        'o_id' => $oId,
                        'e_key' => $element->key,
                        'e_top' => Helpers::convertPxToMm($element->top),
                        'e_left' => Helpers::convertPxToMm($element->left),
                        'e_index' => $element->index,
                        'e_width' => Helpers::convertPxToMm($element->width),
                        'e_height' => Helpers::convertPxToMm($element->height),
                        'e_transform' => $element->mat
                    );

                    Pimcore\File::put($dirElement, \Zend_Json::encode($data));
                }

            }
        }

    }
}
