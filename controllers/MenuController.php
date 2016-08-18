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
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_MenuController
 */
class ActiveWireframe_MenuController extends Action
{

    public function init()
    {
        parent::init();
        $this->disableLayout();
        $this->disableViewAutoRender();
        $this->disableBrowserCache();

        if (!Plugin::composerExists()) {
            echo 'ERROR: Active Publishing - Composer does not exist.';
            exit();
        }

        if (!Plugin::isInstalled()) {
            echo 'ERROR: Active Publishing - Plugin does not installed.';
            exit();
        }

    }

    /**
     * @return int
     */
    public function generatePaginationAction()
    {

        // Point de départ pour la nouvelle pagination
        $document = Printcontainer::getById($this->getParam('documentId'));
        if ($document instanceof Printcontainer) {

            // Index de départ
            $index = $this->hasParam('index') ? $this->getParam('index') : 1;

            // Pas non inclus dans la numérotation
            $noRename = [];

            // instance
            $dbcatalog = new Catalogs();

            // Recherche si le document est un chapitre
            if (!$dbcatalog->getCatalog($document->getId())) {

                // Définit les pages à ne pas renomer
                foreach ($document->getParent()->getChilds() as $child) {
                    if (($child instanceof Printpage || $child instanceof Printcontainer)
                        && ($child->getIndex() < $document->getIndex())
                    ) {
                        $noRename[] = $child->getKey();
                    }
                }

                // Récupère le catalogue
                $document = $document->getParent();

            }

            // Nouvelle pagination
            if ($document->hasChilds()) {

                // index temporaire
                $indexTmp = time();

                // Numerotation temporaire, pour éviter les doublons dans un meme niveau d'arborescence
                Helpers::generateNewPagination($document, $indexTmp, $noRename);
                Helpers::generateNewPagination($document, $index, $noRename);

            }

        }

        return Response::setResponseJson(array(
            'success' => TRUE,
            'msg' => null
        ));
    }

    /**
     * @return int
     */
    public function reloadCatalogAction()
    {
        // Récupère le document racine
        $document = Printcontainer::getById($this->getParam('documentId'));

        // Document de type page, et la page est un chapitre ou catalogue
        if ($document instanceof Printcontainer && $document->getAction() == "tree" && $document->hasChilds()) {

            // Récupère le catalogue
            $dbCatalog = new Catalogs();

            // Printcontainer catalogue
            $catalog = $dbCatalog->getCatalog($document->getId());
            if (!$catalog) {
                // Printconainter chapitre
                $catalog = $dbCatalog->getCatalog($document->getParentId());
            }

            Helpers::reloadThumbnailForTree($document, $catalog['format_width'], $catalog['format_height']);

        }

        return Response::setResponseJson(array(
            'success' => TRUE,
            'msg' => null
        ));
    }

}