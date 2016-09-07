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
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Elements;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\User;
use Pimcore\Model\User\UserRole;
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

        $this->view->baseUrl = Tool::getHostUrl();
        $this->view->documentId = $this->document->getId();
        $this->view->areadir = self::getAreaDir();
        $this->view->version = Util::getPluginVersion(Plugin::PLUGIN_NAME);
        $this->view->pageLock = $this->document->isLocked();

        // Retrieve page informations
        $dbPage = new Pages();
        $page = $dbPage->getPage($this->document->getId());
        $this->view->page = $page;

        // Retrieve catalog informations
        $dbCatalog = new Catalogs();
        $catalog = $dbCatalog->searchCatalogue($this->document->getId());
        $this->view->catalog = $catalog;

        // Get orientation
        if ($catalog['orientation'] == 'landscape') {
            $height = $catalog['format_width'];
            $width = $catalog['format_height'];
        } else {
            $width = $catalog['format_width'];
            $height = $catalog['format_height'];
        }

        $this->view->pageWidth = $width . "mm";
        $this->view->pageHeight = $height . "mm";
        $this->view->pageWidthLandmark = $width + 10 . "mm";
        $this->view->pageHeightLandmark = $height + 10 . "mm";
        $this->view->pageTop = "5mm";
        $this->view->pageLeft = "5mm";

        // Retrieve margin
        $marginLittle = ($catalog['margin_little'] == "") or (!array_key_exists('margin_little', $catalog)) ?
            0 :
            $catalog['margin_little'];

        $marginGreat = ($catalog['margin_great'] == "") or (!array_key_exists('margin_great', $catalog)) ?
            0 :
            $catalog['margin_great'];

        $marginTop = ($catalog['margin_top'] == "") or (!array_key_exists('margin_top', $catalog)) ?
            0 :
            $catalog['margin_top'];

        $marginBottom = ($catalog['margin_bottom'] == "") or (!array_key_exists('margin_bottom', $catalog)) ?
            0 :
            $catalog['margin_bottom'];

        $this->view->paddingLeft = ($this->document->getKey() % 2)
            ? $marginLittle . "mm"
            : $marginGreat . "mm";

        $this->view->paddingRight = ($this->document->getKey() % 2)
            ? $marginGreat . "mm"
            : $marginLittle . "mm";

        $this->view->paddingTop = $marginTop . "mm";
        $this->view->paddingBottom = $marginBottom . "mm";

        // Element informations
        $this->view->elementsData = $this->getElements();

        // page number
        $this->view->numPage = intval($this->document->getKey());

        // Thumbnail
        $configThumbnail = [
            'format' => 'PNG',
            'width' => null,
            'height' => null,
            'aspectratio' => true
        ];
        if ($this->editmode or $this->hasParam('nowkhtmltoimage')) {
            $configThumbnail['quality'] = 90;
        } else {
            $configThumbnail['quality'] = 100;
            $configThumbnail['highResolution'] = 3.2;
        }
        $this->view->thumbnail = $configThumbnail;

        // Background template
        $this->view->template = $this->getTemplate($catalog, $this->document, $configThumbnail);

        // ActivePaginate Plugin integration
        if (Util::pluginIsInstalled('ActivePaginate')) {
            $this->view->activepaginate = true;
            $this->view->gridCol = ($page['grid_col'] != 0) ? $page['grid_col'] : 3;
            $this->view->gridRow = ($page['grid_row'] != 0) ? $page['grid_row'] : 4;
        }

        // Création d'une vignette
        if (!$this->editmode and !$this->hasParam('nowkhtmltoimage')) {
            Helpers::getPageThumbnailForTree($this->document, $width);
        }
    }

    /**
     * Get areas for the current user
     * @return string
     */
    public static function getAreaDir()
    {
        $user = Tool\Admin::getCurrentUser();
        $roles = Util::getRolesFromCurrentUser();
        $areaPath = '/website/views/areas';
        $areaPathAbs = PIMCORE_WEBSITE_PATH . '/views/areas';

        if ($user instanceof User and !$user->isAdmin() and !empty($roles)) {

            foreach ($roles as $id) {

                $pimcoreRole = UserRole::getById($id);
                if ($pimcoreRole instanceof UserRole and file_exists($areaPathAbs . '/' . $pimcoreRole->getName())) {
                    return $areaPath . DIRECTORY_SEPARATOR . $pimcoreRole->getName();
                }

            }

        } else if ($user instanceof User and $user->isAdmin()) {
            return $areaPath . '/admin';
        }

        return $areaPath . '/active-wireframe';
    }

    /**
     * Retrieve elements data
     * @return array
     */
    public function getElements()
    {
        $dbElement = new Elements();
        $els = $dbElement->getElementsByDocumentId($this->document->getId());
        $retEls = [];

        foreach ($els as $el) {
            $retEls[$el['e_key']] = $el;
        }
        return $retEls;
    }

    /**
     * Retrieve template page
     * @param $catalog
     * @param Document $page
     * @param $thumbnail
     * @return mixed|null|string
     */
    public function getTemplate($catalog, Document $page, $thumbnail)
    {
        $templatePage = null;
        if ($catalog['document_id'] != $page->getParentId()) {

            // Order
            $assetTemplate = ($page->getKey() % 2)
                ? Asset::getById($catalog['template_odd'])
                : Asset::getById($catalog['template_even']);

            if ($assetTemplate) {

                if ($assetTemplate instanceof Asset\Document) {
                    $templatePage = $assetTemplate->getImageThumbnail($thumbnail)->getPath();

                } else if ($assetTemplate instanceof Asset\Image) {
                    $thumbnail = $assetTemplate->getThumbnailConfig($thumbnail);
                    $templatePage = $assetTemplate->getThumbnail($thumbnail)->getPath();
                }

            }

        }

        return $templatePage;
    }

}
