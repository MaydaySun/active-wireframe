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
use ActivePublishing\Services\Document;
use ActivePublishing\Services\Util;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use ActiveWorkflow\Db\Pages as ActiveWorkflowDbPages;
use ActiveWorkflow\Db\States as ActiveWorkflowDbStates;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Pimcore\Tool;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_CatalogsController
 */
class ActiveWireframe_CatalogsController extends Action
{

    public function init()
    {
        parent::init();

        if (!Plugin::composerExists()) {
            $this->disableLayout();
            $this->disableViewAutoRender();
            echo 'ERROR: Active Publishing - Composer does not exist.';
            exit();
        }

        if (!Plugin::isInstalled()) {
            $this->disableLayout();
            $this->disableViewAutoRender();
            echo 'ERROR: Active Publishing - Plugin does not installed.';
            exit();
        }

        $this->view->baseUrl = Tool::getHostUrl();
        $this->view->documentId = $this->document->getId();
        $this->view->version = Util::getPluginVersion(Plugin::PLUGIN_NAME);
    }

    /**
     * Affiche l'arborescence du catalogue ou du chapitre
     */
    public function treeAction()
    {
        // Récupère le document
        $document = $this->getParam("document");

        // Publication du document
        if (!$document->isPublished()) {
            $document->setPublished(1);
            $document->save();
        }


        if ($this->editmode) {

            // Layout
            $this->enableLayout();
            $this->setLayout("index");

            // Pas de pages dans le catalogue
            if (!$document->hasChilds()) {

                $this->view->noChilds = true;

            } else {

                // Donnée catalog + pages
                $allPages = $this->getPages($document);
                $this->view->pages = $allPages['pages'];

                // Récupère
                $currentPage = $allPages['pages'][0];
                $this->view->indiceFirstPage = $currentPage['indice'];

                // Récupère les informations du catalogue
                $dbCatalog = new Catalogs();
                $catalog = $dbCatalog->getCatalog($document->getId());

                // Cas du chapitre
                if (!$catalog) {
                    $catalog = $dbCatalog->getCatalog($document->getParentId());
                }

                $this->view->optionsCat = $catalog;

                // Informations sur la page
                if ($catalog['orientation'] == 'landscape') {
                    $heightPage = $catalog['format_width'];
                    $widthPage = $catalog['format_height'];
                } else {
                    $widthPage = $catalog['format_width'];
                    $heightPage = $catalog['format_height'];
                }

                $this->view->widthPage = $widthPage;
                $this->view->heightPage = $heightPage;
                $this->view->reduction = Helpers::getReduction($widthPage);

                // Génération des prévisualisation des pages
                foreach ($allPages['pages'] as $page) {

                    $fileThumb = PIMCORE_TEMPORARY_DIRECTORY . '/' . Plugin::PLUGIN_NAME
                        . '/' . $page['documentId'] . '.png';
                    if (!file_exists($fileThumb)) {
                        $printpage = Printpage::getById($page['documentId']);
                        Helpers::getPageThumbnailForTree($printpage, $widthPage, $heightPage);
                    }
                }
            }

            $this->renderScript('catalogs/tree.php');

        } else {

            $document = $this->getParam("document");
            $allChildren = $document->getAllChildren();
            $this->view->allChildren = $allChildren;
            $this->renderScript('catalogs/webtoprint.php');

        }
    }

    /**
     * Récupère les informations des pages dans un catalogue ou un chapitre
     * @param Printcontainer $document
     * @param int $indice
     * @return array
     */
    public function getPages(Printcontainer $document, $indice = 0)
    {
        // tableau contenant les informations des différentes pages du catalogue
        $pages = array();

        // Pour chaques enfants du document parent $document
        if ($document->hasChilds()) {

            foreach ($document->getChilds() as $child) {

                // Cas d'une page
                if ($child instanceof Printpage && !$child->hasChilds()) {

                    // Plugin workflow installé
                    $pageStateWorkflow = false;
                    if (Util::pluginIsInstalled('ActiveWorkflow')) {

                        // Récupère les informations de la page
                        $dbAWPages = new ActiveWorkflowDbPages();
                        $dataAWPage = $dbAWPages->getData($child->getId());

                        if (is_array($dataAWPage)) {
                            $dbAWState = new ActiveWorkflowDbStates();
                            $pageStateWorkflow = $dbAWState->getState($dataAWPage['state_id']);
                        }
                    }

                    $indice = ctype_digit($child->getKey())
                        ? $child->getKey()
                        : $indice + 1;

                    // Instance
                    $pages[] = array(
                        'documentId' => $child->getId(),
                        'key' => $child->getKey(),
                        'indice' => $indice,
                        'notes' => Document::getNotes($child),
                        'workflow' => (is_array($pageStateWorkflow)) ? $pageStateWorkflow : array()
                    );

                    // cas d'un chapitre
                } else if ($child instanceof Printcontainer && $child->hasChilds()) {

                    $pageOfChapter = $this->getPages($child, $indice);
                    $pages = array_merge($pages, $pageOfChapter['pages']);
                    $indice = $pageOfChapter['indice'];
                }
            }
        }

        return array(
            'pages' => $pages,
            'indice' => $indice
        );
    }

}
