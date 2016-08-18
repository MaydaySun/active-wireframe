<?php
/**
 * LICENSE
 *
 * This source file is subject to the new Creative Commons license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/4.0/
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contact@active-publishing.fr so we can send you a copy immediately.
 *
 * @author      Active Publishing <contact@active-publishing.fr>
 * @copyright   Copyright (c) 2015 Active Publishing (http://www.active-publishing.fr)
 * @license     http://creativecommons.org/licenses/by-nc-nd/4.0/
 */
use ActivePublishing\Services\Response;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Elements;
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

        if (!Plugin::composerExists()) {
            $this->disableLayout();
            $this->disableViewAutoRender();
            exit();
        }

        if (!Plugin::isInstalled()) {
            $this->disableLayout();
            $this->disableViewAutoRender();
            exit();
        }

    }

    /**
     * Enregistre les donnees des blocs box-w2p
     */
    public function saveBoxW2pAction()
    {
        // Récupère les élements à enregistrer
        if ($this->hasParam('id') && $this->hasParam('elements') && !empty($this->getParam('elements'))) {

            // Instance
            $dbElement = new Elements();
            $dbCatalog = new Catalogs();

            // ID du docuent concerné
            $id_document = $this->getParam("id");
            $document = Printpage::getById($id_document);

            // Supprime les informations des éléments
            $dbElement->deleteByKey('document_id', $document->getId());

            // Récupère le Catalogue
            $catalog = $dbCatalog->searchCatalogue($document->getId());

            // Pour chaques éléments présent dans le document
            $elements = \json_decode($this->getParam('elements'));

            foreach ($elements as $element) {

                // récupérations
                $oId = $element->oId;
                $key = $element->key;
                $top = $element->top;
                $left = $element->left;
                $index = $element->index;
                $width = $element->width;
                $height = $element->height;
                $mat = $element->mat;

                // création du tableau de données
                $data = array(
                    'o_id' => $oId,
                    'document_id' => $id_document,
                    'document_parent_id' => $document->getParentId(),
                    'document_root_id' => $catalog['document_id'],
                    'page_key' => $document->getKey(),
                    'e_id' => 0,
                    'e_key' => $key,
                    'e_top' => Helpers::convertPxToMm($top),
                    'e_left' => Helpers::convertPxToMm($left),
                    'e_index' => $index,
                    'e_width' => Helpers::convertPxToMm($width),
                    'e_height' => Helpers::convertPxToMm($height),
                    'e_transform' => $mat
                );

                // Insert
                $dbElement->insert($data);
            }

        }

        return Response::setResponseJson(array(
            "success" => true
        ));
    }

    /**
     * Enregistre les donnees des element-w2p
     */
    public function saveElementW2pAction()
    {
        // Récupère les élements à enregistrer
        if ($this->hasParam('id') && $this->hasParam('elements') && !empty($this->getParam('elements'))) {

            $documentId = intval($this->getParam("id"));

            // Dir
            $dir = PIMCORE_DOCUMENT_ROOT . DIRECTORY_SEPARATOR . 'activetmp/' . Plugin::PLUGIN_NAME;
            if (!file_exists($dir)) {
                Pimcore\File::mkdir($dir, 0775, true);
            }

            // ID du document
            $document = Printpage::getById($documentId);
            if ($document instanceof Printpage) {

                // Dir document
                $dirDocument = $dir . DIRECTORY_SEPARATOR . $documentId;
                if (!file_exists($dirDocument)) {
                    Pimcore\File::mkdir($dirDocument, 0775, true);
                }

                // Pour chaques éléments présent dans le document
                $elements = \json_decode($this->getParam('elements'));

                foreach ($elements as $element) {

                    // récupérations
                    $oId = $element->oId;
                    $key = $element->key;
                    $top = $element->top;
                    $left = $element->left;
                    $index = $element->index;
                    $width = $element->width;
                    $height = $element->height;
                    $mat = $element->mat;

                    // Object ID OK
                    if (intval($oId)) {

                        // Dir element
                        $dirElement = $dirDocument . DIRECTORY_SEPARATOR . $oId . DIRECTORY_SEPARATOR
                            . 'element-w2p-' . $key . '.json';

                        // Supprime le fichier JSON existant
                        if (file_exists($dirElement)) {
                            @unlink($dirElement);
                        }

                        // création du tableau de données
                        $data = array(
                            'o_id' => $oId,
                            'e_key' => $key,
                            'e_top' => Helpers::convertPxToMm($top),
                            'e_left' => Helpers::convertPxToMm($left),
                            'e_index' => $index,
                            'e_width' => Helpers::convertPxToMm($width),
                            'e_height' => Helpers::convertPxToMm($height),
                            'e_transform' => $mat
                        );

                        // Insert
                        Pimcore\File::put($dirElement, \Zend_Json_Encoder::encode($data));
                    }

                }

            }

        }

        return Response::setResponseJson(array(
            "success" => true
        ));
    }
}
