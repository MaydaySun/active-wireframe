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

use ActivePublishing\Service\Note;
use ActivePublishing\Service\Tool;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_CatalogsController
 */
class ActiveWireframe_CatalogsController extends Action
{
    /**
     * Init
     */
    public function init()
    {
        parent::init();

        if (!Plugin::composerExists()) {
            $this->disableLayout();
            $this->disableViewAutoRender();
            echo 'ERROR: Active Publishing - Composer librairies for this plugin is not installed.';
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
        $this->view->version = Tool::getPluginVersion(Plugin::PLUGIN_NAME);
    }

    /**
     * Displays the wireframe tree
     */
    public function treeAction()
    {
        $document = $this->getParam("document");
        if ($this->editmode) {
            $this->enableLayout();
            $this->setLayout("index");

            // the catalog is empty
            if (!$document->hasChilds()) {
                $this->view->noChilds = true;

            } else {

                // Retrieve the datas of pages
                $allPages = $this->getPages($document);
                $this->view->pages = $allPages['pages'];

                // key of the firste page
                $currentPage = $allPages['pages'][0];
                $this->view->indiceFirstPage = $currentPage['indice'];

                // Retrieve the datas of catalog
                $dbCatalogs = Catalogs::getInstance();
                $catalog = $dbCatalogs->getCatalogByDocumentId($document->getId());

                // Case of chapter
                if (!$catalog) {
                    $catalog = $dbCatalogs->getCatalogByDocumentId($document->getParentId());
                }
                $this->view->optionsCat = $catalog;

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
            }

            $this->renderScript('catalogs/tree.php');
        }
    }

    /**
     * Retrieve the information from a catalog or chapter
     *
     * @param Printcontainer $document
     * @param int $indice
     * @return array
     */
    public function getPages(Printcontainer $document, $indice = 0)
    {
        $pages = [];
        if ($document->hasChilds()) {

            foreach ($document->getChilds() as $child) {

                // Page
                if ($child instanceof Printpage and !$child->hasChilds()) {

                    // Workflow plugin is installed
                    $pageStateWorkflow = false;
                    if (Tool::pluginIsInstalled('ActiveWorkflow')) {

                        $classAWPages = "\\ActiveWorkflow\\Db\\Pages";
                        $dbAWPages = new $classAWPages();
                        $dataAWPage = call_user_func_array([$dbAWPages, "getData"], [$child->getId()]);

                        if (is_array($dataAWPage)) {
                            $classAWStates = "\\ActiveWorkflow\\Db\\States";
                            $dbAWState = new $classAWStates();
                            $pageStateWorkflow = call_user_func_array([$dbAWState, "getState"], [$dataAWPage['state_id']]);
                        }
                    }

                    $indice = ctype_digit($child->getKey())
                        ? intval($child->getKey())
                        : ++$indice;

                    // Instance
                    $pages[] = [
                        'documentId' => $child->getId(),
                        'key' => $child->getKey(),
                        'indice' => $indice,
                        'notes' => Note::getNotes($child),
                        'workflow' => (is_array($pageStateWorkflow)) ? $pageStateWorkflow : []
                    ];

                } else if ($child instanceof Printcontainer and $child->hasChilds()) {

                    // Chapter
                    $pageOfChapter = $this->getPages($child, $indice);
                    $pages = array_merge($pages, $pageOfChapter['pages']);
                    $indice = $pageOfChapter['indice'];
                }
            }
        }

        return ['pages' => $pages, 'indice' => $indice];
    }

}