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
use ActivePublishing\Services\Util;
use ActiveWireframe\Db\Elements;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Tool;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_PagesController
 */
class ActiveWireframe_PagesController extends Action
{
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
        $this->view->version = Util::getPluginVersion(Plugin::PLUGIN_NAME);

        // instance ActiveWireframe\Db\Pages
        $dbPage = new Pages();

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
            ? $cinfo['margin_little'] . "mm"
            : $cinfo['margin_great'] . "mm";

        $this->view->paddingRight = ($this->document->getKey() % 2)
            ? $cinfo['margin_great'] . "mm"
            : $cinfo['margin_little'] . "mm";

        $this->view->paddingTop = $cinfo['margin_top'] . "mm";
        $this->view->paddingBottom = $cinfo['margin_bottom'] . "mm";

        // Element informations
        $this->view->elementsData = $this->getElements($this->document->getId());

        // Thumbnail
        $configThumbnail = [
            'format' => 'PNG',
            'width' => 1024,
            'height' => null,
            'aspectratio' => true,
            'quality' => 90
        ];
        if (!$this->editmode or !$this->hasParam('nowkhtmltoimage')) {

            $configThumbnail = [
                'format' => 'PNG',
                'width' => 1024,
                'height' => null,
                'aspectratio' => true,
                'quality' => 100,
                'highResolution' => 3.2
            ];

        }
        $this->view->thumbnail = serialize($configThumbnail);

        // Get background template for only page in chapter
        if ($this->document->getParentId() != $cinfo['document_id']) {
            $this->view->template = Helpers::getBackgroundTemplate($this->document, $cinfo, $configThumbnail);
        }

        // ActivePaginate Plugin integration
        if (Util::pluginIsInstalled('ActivePaginate')) {
            $this->view->activepaginate = true;
            $this->view->gridCol = ($pinfo['grid_col'] != 0) ? $pinfo['grid_col'] : 3;
            $this->view->gridRow = ($pinfo['grid_row'] != 0) ? $pinfo['grid_row'] : 4;
        }
    }

    /**
     * @param $documentId
     * @return array
     */
    public function getElements($documentId)
    {
        $dbElement = new Elements();
        $elements = $dbElement->getElementsByDocumentId(intval($documentId));

        $collection = [];
        foreach ($elements as $element) {
            $collection[$element['e_key']] = $element;
        }

        return $collection;
    }

}
