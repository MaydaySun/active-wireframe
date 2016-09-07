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
use ActivePublishing\Services\Translation;
use ActivePublishing\Services\Util;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Model\Asset;
use Pimcore\Model\Document\Printcontainer;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_CreateController
 */
class ActiveWireframe_CreateController extends Action
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
     * Show form
     */
    public function formAction()
    {
        $this->enableLayout();
        $this->setLayout("index");

        $this->view->documentId = $this->document->getId();
        $this->view->version = Util::getPluginVersion(Plugin::PLUGIN_NAME);

        // Paginate Form
        $this->view->templateFormPaginate = false;
        if (Util::pluginIsInstalled('ActivePaginate')) {
            $pluginName = call_user_func("\\ActivePaginate\\Plugin::PLUGIN_NAME");
            $this->view->templateFormPaginate = '/plugins/' . $pluginName . '/views/scripts/form.php';
        }
    }

    /**
     * Create catalog
     * @return int
     */
    public function catalogAction()
    {
        $this->disableViewAutoRender();
        $this->disableLayout();
        $this->disableBrowserCache();

        $success = true;
        $msg = Translation::get('active_wireframe_form_success');
        $cover1 = Translation::get('active_wireframe_first_cover');
        $cover2 = Translation::get('active_wireframe_second_cover');
        $cover3 = Translation::get('active_wireframe_third_cover');
        $cover4 = Translation::get('active_wireframe_fourth_cover');

        $documentId = $this->getParam('documentId');
        $documentRoot = Printcontainer::getById($documentId);

        // Orientation
        $orientation = $this->getParam('orientation');

        // Format
        if ($this->getParam('format') != "null") {

            $formatPageArray = explode("x", $this->getParam('format'));
            $formatPageWidth = $formatPageArray[0];
            $formatPageHeight = $formatPageArray[1];

        } else {

            $formatPageWidth = $this->hasParam("format-width") ? $this->getParam("format-width") : 210;
            $formatPageHeight = $this->hasParam("format-height") ? $this->getParam("format-height") : 297;

        }

        // Margin
        $margin_little = ($this->hasParam('margin-little') or ($this->getParam('margin-little') != ""))
            ? $this->getParam('margin-little')
            : 0;

        $margin_great = ($this->hasParam('margin-great') or ($this->getParam('margin-great') != ""))
            ? $this->getParam('margin-great')
            : 0;

        $margin_top = ($this->hasParam('margin-top') or ($this->getParam('margin-top') != ""))
            ? $this->getParam('margin-top')
            : 0;

        $margin_bottom = ($this->hasParam('margin-bottom') or ($this->getParam('margin-bottom') != ""))
            ? $this->getParam('margin-bottom')
            : 0;

        // Templates des pages
        $template_odd = ($this->hasParam('template-odd') or ($this->getParam('template-odd') != ""))
            ? $this->getParam('template-odd')
            : 0;

        $template_even = ($this->hasParam('template-even') or ($this->getParam('template-even') != ""))
            ? $this->getParam('template-even')
            : 0;

        // Save options
        $optionsCatalog = array(
            "document_id" => $documentId,
            "format_width" => number_format($formatPageWidth, 2),
            "format_height" => number_format($formatPageHeight, 2),
            "orientation" => $orientation,
            "margin_little" => $margin_little,
            "margin_great" => $margin_great,
            "margin_top" => $margin_top,
            "margin_bottom" => $margin_bottom,
            "template_odd" => $template_odd,
            "template_even" => $template_even
        );

        // Insert to DB
        $dbCatalog = new Catalogs();
        $dbCatalog->insert($optionsCatalog);

        $indexPage = 1;

        // Create cover 1 and 2
        if ($this->getParam("coverPage") === "1") {
            Helpers::createPage($cover1, $documentId);
            Helpers::createPage($cover2, $documentId);
            $indexPage = 3;
        }

        // Active Paginate
        if (Util::pluginIsInstalled('ActivePaginate')) {
            $indexPage = $this->createWithPaginate($indexPage, $documentId);
        }

        // Crate chapter
        if ($this->hasParam("chapters") and !empty($this->getParam("chapters"))) {
            $chapCreated = [];
            foreach ($this->getParam("chapters") as $keyChap => $nameChap) {

                // Formate le nom
                $nameChap = trim($nameChap);
                $nameChap = in_array($nameChap, $chapCreated) or ($nameChap == "") ?
                    'undefined-chapter-' . $keyChap :
                    $nameChap;

                // Create and insert options in DB
                $chapter = Helpers::createChapter($nameChap, $documentId);

                // Create page of chapter
                $keyPageMax = $this->getParam("numbPages");

                for ($keyPageinChapter = 1; $keyPageinChapter <= $keyPageMax[$keyChap]; $keyPageinChapter++) {
                    Helpers::createPage($indexPage, $chapter->getId());
                    $indexPage++;
                }

                // Incrémente le tableau
                $chapCreated[] = $nameChap;
            }

        }

        // Create static page
        if ($this->hasParam("pageStatic") and !empty($this->getParam("pageStatic"))) {
            $pageStaticCreated = [];
            foreach ($this->getParam("pageStatic") as $keyPageStatic => $pageStatic) {

                $pageStatic = trim($pageStatic);
                if ($pageStatic != "") {

                    $pageStatic = in_array($pageStatic, $pageStaticCreated) ?
                        "undefined-page-" . $keyPageStatic :
                        $pageStatic;

                    // Create and insert in DB
                    Helpers::createPage($pageStatic, $documentId);
                    $pageStaticCreated[] = $pageStatic;
                }
            }

        }

        // Create Cover 3 and 4
        if ($this->getParam("coverPage") === "1") {
            Helpers::createPage($cover3, $documentId);
            Helpers::createPage($cover4, $documentId);
        }

        try {

            $documentRoot->setValues(array("controller" => "catalogs", "action" => "tree"));
            $documentRoot->setPublished(1);
            $documentRoot->save();

        } catch (\Exception $ex) {

            \Pimcore\Logger::err($ex->getMessage());
            $success = false;
            $msg = $ex->getMessage();

        }

        return Response::setResponseJson(array(
            'success' => $success,
            'msg' => $msg
        ));
    }

    /**
     * Create page with Active Paginate module
     * @param $indexPage
     * @param $documentId
     * @return bool
     */
    public function createWithPaginate($indexPage, $documentId)
    {
        // check if possible
        if (($this->hasParam('filenames') and ($this->getParam('filenames') != ''))
            or ($this->hasParam('targetClassFamily') and ($this->getParam('targetClassFamily') != '-1'))
        ) {

            $import = [];
            $import['index'] = $indexPage;
            $import['templateId'] = $this->hasParam('targetTemplate') ? trim($this->getParam('targetTemplate')) : '';
            $import['grid'] = [
                'row' => $this->hasParam('gridRow') ? intval($this->getParam('gridRow')) : 0,
                'col' => $this->hasParam('gridCol') ? intval($this->getParam('gridCol')) : 0
            ];

            // flyleaf
            if ($this->hasParam('frontPage') and intval($this->getParam('frontPage')) == 1) {
                $import['frontPage'] = array(
                    'type' => $this->getParam('frontPageTypeOption'),
                    'template' => $this->hasParam('selectOTemplate')
                        ? trim($this->getParam('selectOTemplate'))
                        : '',
                    'templateLvl' => $this->hasParam('selectOTemplateLvl')
                        ? intval($this->getParam('selectOTemplateLvl'))
                        : 1,
                    'qteProduct' => $this->hasParam('selectQteProduct')
                        ? intval($this->getParam('selectQteProduct'))
                        : 0
                );
            }

            //Import by CSV files
            if ($this->hasParam('filenames') and $this->getParam('filenames') != '') {

                $import['csv'] = $this->getParam('filenames');
                $indexPage = call_user_func("\\ActivePaginate\\Form::ProductsImport", $documentId, $import, 'csv');

            } elseif ($this->hasParam('targetClassFamily') and $this->getParam('targetClassFamily') != '-1'
                and $this->hasParam('targetFamily') and $this->getParam('targetFamily') != '0'
                and $this->hasParam('targetClassObject') and $this->getParam('targetClassObject') != '-1'
            ) {

                // Import by tree
                $import['tree'] = array(
                    'familyClassId' => $this->getParam('targetClassFamily'),
                    'familyId' => $this->getParam('targetFamily'),
                    'objectClassId' => $this->getParam('targetClassObject')
                );

                $indexPage = call_user_func("\\ActivePaginate\\Form::ProductsImport", $documentId, $import, 'tree');
            }
        }

        return $indexPage;
    }

    /**
     * Retrieve templates
     * @return int
     */
    public function getTemplatesAction()
    {
        $this->disableViewAutoRender();
        $this->disableLayout();
        $this->disableBrowserCache();

        // Get format and orientation
        $format = $this->hasParam('format') ? $this->getParam('format') : 'a4';

        // exotic format
        if ($format == "other") {
            $templates = $this->getAssetThumbnailByPath('/gabarits-de-pages/');
        } else {
            $templates = $this->getAssetThumbnailByPath('/gabarits-de-pages/' . $format);
        }

        return Response::setResponseJson(array(
            'success' => !empty($templates),
            'templates' => $templates
        ));
    }

    /**
     * Retrieve thumbnail for templates
     * @param $pathToFolderAsset
     * @param array $files
     * @return array
     */
    public function getAssetThumbnailByPath($pathToFolderAsset, $files = array())
    {
        $assetFolder = Asset\Folder::getByPath($pathToFolderAsset);
        if ($assetFolder instanceof Asset\Folder and $assetFolder->hasChilds()) {

            // Config
            $thumbnailConf = array(
                'format' => 'PNG',
                'width' => null,
                'height' => 150,
                'quality' => 90,
                'aspectratio' => true
            );

            foreach ($assetFolder->getChilds() as $child) {

                // Count array
                $i = count($files);

                // Création du thumb
                if ($child instanceof Asset\Document) {

                    $files[$i]['thumb'] = $child->getImageThumbnail($thumbnailConf)->getPath();
                    $files[$i]['id'] = $child->getId();

                } else if ($child instanceof Asset\Image) {

                    $thumbnail = $child->getThumbnailConfig($thumbnailConf);
                    $files[$i]['thumb'] = $child->getThumbnail($thumbnail)->getPath();
                    $files[$i]['id'] = $child->getId();

                } else if ($child instanceof Asset\Folder) {
                    $files = $this->getAssetThumbnailByPath($child->getFullPath(), $files);
                }
            }
        }

        return $files;
    }

    /**
     * Download files
     */
    public function uploadFilesAction()
    {
        $this->disableLayout();
        $this->disableViewAutoRender();
        $this->disableBrowserCache();

        $files = [];
        $uploaddir = PIMCORE_TEMPORARY_DIRECTORY;

        if (!empty($_FILES)) {
            foreach ($_FILES as $file) {

                $filename = basename($file['name']);
                $filepath = PIMCORE_TEMPORARY_DIRECTORY . "/" . $filename;

                // Clear
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }

                // Download file
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $files[] = $uploaddir . "/" . $file['name'];
                } else {

                    return Response::setResponseJson(array(
                        'success' => false,
                        'msg' => Translation::get('active_wireframe_error_uploadfiles')
                    ));

                }
            }
        }

        return Response::setResponseJson(array(
            'success' => true,
            'msg' => '',
            'files' => $files
        ));

    }

}