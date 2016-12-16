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
use ActivePublishing\Service\Tool;
use ActivePublishing\Service\File;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Model\Document;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_PagesController
 */
class ActiveWireframe_PagesController extends Action
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
    }

    /**
     * Show pdf page in edition mode
     */
    public function pagesAction()
    {
        $this->enableLayout();
        $this->setLayout("index");

        $this->view->documentId = $this->document->getId();
        $this->view->pageLock = $this->document->isLocked();
        $this->view->numPage = intval($this->document->getKey());
        $this->view->baseUrl = Tool::getHostUrl();
        $this->view->areadir = Helpers::getAreaByRole();
        $this->view->version = Tool::getPluginVersion(Plugin::PLUGIN_NAME);

        // instance ActiveWireframe\Db\Pages
        $dbPage = Pages::getInstance();

        // Retrieve page informations
        $pinfo = $dbPage->getPageByDocumentId($this->document->getId());
        $this->view->pinfo = $pinfo;

        // Retrieve catalog informations
        $cinfo = $dbPage->getCatalogByDocumentId($this->document->getId());
        $this->view->cinfo = $cinfo;

        // Get orientation
        if ($cinfo['orientation'] == 'landscape') {
            $height = $cinfo['format_width'];
            $width = $cinfo['format_height'];
        } else {
            $width = $cinfo['format_width'];
            $height = $cinfo['format_height'];
        }

        $this->view->pageWidth = $width . "mm";
        $this->view->pageHeight = $height . "mm";
        $this->view->pageWidthLandmark = $width + 10 . "mm";
        $this->view->pageHeightLandmark = $height + 10 . "mm";
        $this->view->pageTop = "5mm";
        $this->view->pageLeft = "5mm";

        // Retrieve margin
        $this->view->paddingLeft = ($this->document->getKey() % 2)
            ? floatval($cinfo['margin_little']). "mm"
            : floatval($cinfo['margin_great']) . "mm";

        $this->view->paddingRight = ($this->document->getKey() % 2)
            ? floatval($cinfo['margin_great']) . "mm"
            : floatval($cinfo['margin_little']) . "mm";

        $this->view->paddingTop = floatval($cinfo['margin_top']) . "mm";
        $this->view->paddingBottom = floatval($cinfo['margin_bottom']) . "mm";

        // Element informations
        $this->view->elementsData = Helpers::getElements($this->document->getId());

        if (!$this->editmode AND $this->hasParam('generateW2p')) {
            $thumbnail = "active-wireframe-print";
        } else {
            $thumbnail = "active-wireframe-preview";
        }
        $this->view->thumbnail = $thumbnail;

        // Get background template for only page in chapter
        if ($this->document->getParentId() != $cinfo['document_id']) {
            $this->view->template = Helpers::getBackgroundTemplate($this->document, $cinfo, $thumbnail);
        }

        // ActivePaginate Plugin integration
        if (Tool::pluginIsInstalled('ActivePaginate')) {
            $this->view->activepaginate = true;
            $this->view->gridCol = ($pinfo['grid_col'] != 0) ? $pinfo['grid_col'] : 3;
            $this->view->gridRow = ($pinfo['grid_row'] != 0) ? $pinfo['grid_row'] : 4;
        }

        if (!$this->editmode and !$this->hasParam('pimcore_preview') and !$this->hasParam('createThumbnail')) {
            $widthPX = Helpers::convertMmToPx($width);
            Helpers::getPageThumbnailForTree($this->document, $widthPX);
        }
    }

    /**
     * Retrieve all areas
     */
    public function getAreasListingAction()
    {
        $this->disableLayout();
        $this->disableViewAutoRender();
        $areas = [];

        if ($dir = Helpers::getAreaByRole() and !is_dir(PIMCORE_WEBSITE_PATH . $dir)) {

            $absolutePath = PIMCORE_DOCUMENT_ROOT . $dir;
            $listing = File::ls($absolutePath);

            if (!empty($listing)) {
                foreach ($listing as $area) {

                    $xml = $absolutePath . DIRECTORY_SEPARATOR . $area . DIRECTORY_SEPARATOR . "area.xml";
                    $view = $absolutePath . DIRECTORY_SEPARATOR . $area . DIRECTORY_SEPARATOR . "view.php";

                    if (file_exists($xml) and file_exists($view)) {
                        $contentXml = new \Zend_Config_Xml(file_get_contents($xml));
                        if ($contentXml->get('type') == 'renderlet') {
                            $areas[] = [
                                'id' => $contentXml->get('id'),
                                'name' => $contentXml->get('name')
                            ];
                        }
                    }
                }
            }

        }

        Tool::sendJson($areas);
    }

    /**
     * Update area
     */
    public function setAreaAction()
    {
        $this->disableViewAutoRender();
        $this->disableBrowserCache();
        $success = false;
        $msg = 'Area has not been modified';

        if ($this->hasParam('oId')
            and $this->hasParam('dId')
            and $this->hasParam('oldArea')
            and $this->hasParam('newArea')
        ) {
            $oId = $this->getParam('oId');
            $dId = $this->getParam('dId');
            $oldArea = $this->getParam('oldArea');
            $newArea = $this->getParam('newArea');

            $suffix = strstr($oldArea, 'pages-editable');
            $key = str_replace('pages-editable', '', $suffix);
            $newName = $newArea . $suffix;

            if ($document = Document::getById($dId) and $document instanceof Document\Printpage) {

                $document->clearDependentCache();

                if ($document->hasElement('pages-editable') and $document->hasElement($oldArea)) {

                    $pageEditable = $document->getElement('pages-editable');
                    $oldElement = $document->getElement($oldArea);

                    if ($pageEditable instanceof Document\Tag\Areablock
                        and $oldElement instanceof Document\Tag\Renderlet) {

                        // Delete old renderlet
                        $document->removeElement($oldArea);
                        $document->removeElement('pages-editable');

                        // New renderlet
                        $renderlet = new Document\Tag\Renderlet();
                        $renderlet->setId($oId);
                        $renderlet->type = 'object';
                        $renderlet->setSubtype('object');
                        $renderlet->setName($newName);
                        $renderlet->setDocumentId($document->getId());
                        $document->setElement($newName, $renderlet);

                        // Update areablock
                        if (!empty($pageEditable->indices)) {
                            foreach ($pageEditable->indices as $kIndice => $indice) {
                                if (is_array($indice) and $indice['key'] == $key) {
                                    $pageEditable->indices[$kIndice]['type'] = $newArea;
                                }
                            }
                        }
                        $document->setElement('pages-editable', $pageEditable);
                        $document->clearDependentCache();

                        try {
                            $document->save();
                            $success = true;
                            $msg = null;
                        } catch (\Exception $ex) {
                            $msg = $ex->getMessage();
                        }

                    }
                }

                Tool::sendJson(['success' => $success, 'msg' => $msg]);
            }
        }
    }

}
