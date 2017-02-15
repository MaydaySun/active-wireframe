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
use ActivePublishing\Service\Translation;
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

        if (!Plugin::isInstalled()) {
            $this->disableLayout();
            $this->disableViewAutoRender();
            exit('The ActiveWireframe plugin must be installed in order to use this page');
        }
    }

    public function formAction()
    {
        $this->enableLayout();
        $this->setLayout("index");

        $this->view->documentId = $this->document->getId();
        $this->view->version = Tool::getPluginVersion(Plugin::PLUGIN_NAME);

        // Paginate Form
        $this->view->templateFormPaginate = false;
        if (Tool::pluginIsInstalled('ActivePaginate')) {
            $this->view->templateFormPaginateJS = "";
            //@Todo
        }
    }

    public function catalogAction()
    {
        $this->disableViewAutoRender();
        $this->disableLayout();

        $cover1 = Translation::get('active_wireframe_first_cover');
        $cover2 = Translation::get('active_wireframe_second_cover');
        $cover3 = Translation::get('active_wireframe_third_cover');
        $cover4 = Translation::get('active_wireframe_fourth_cover');

        $indexPage = 1;

        $catalogId = intval($this->getParam('documentId'));
        $catalog = Printcontainer::getById($catalogId);

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

        try {

            // Insert catalog informations in DB
            Catalogs::getInstance()->insertCatalog($catalog, [
                "document_id" => $catalogId,
                "format_width" => number_format($formatPageWidth, 2),
                "format_height" => number_format($formatPageHeight, 2),
                "orientation" => $orientation,
                "margin_little" => $margin_little,
                "margin_great" => $margin_great,
                "margin_top" => $margin_top,
                "margin_bottom" => $margin_bottom,
                "template_odd" => $template_odd,
                "template_even" => $template_even
            ]);

            // Create cover 1 and 2
            if ($this->getParam("coverPage") === "1") {
                Helpers::createPage($cover1, $catalogId, $catalogId);
                Helpers::createPage($cover2, $catalogId, $catalogId);
                $indexPage = 3;
            }

            // Active Paginate
            if (Tool::pluginIsInstalled('ActivePaginate')) {
                $lastPage = $this->createWithPaginate($indexPage, $catalogId);
                $indexPage = $lastPage + 1;
            }

            // Crate chapter
            if ($this->hasParam("chapters") and !empty($this->getParam("chapters"))) {

                foreach ($this->getParam("chapters") as $keyChap => $nameChap) {

                    // Create and insert options in DB
                    $chapter = Helpers::createChapter($nameChap, $catalogId);
                    if ($chapter instanceof Printcontainer) {

                        // Create page of chapter
                        $keyPageMax = $this->getParam("numbPages");

                        for ($keyPageinChapter = 1; $keyPageinChapter <= $keyPageMax[$keyChap]; $keyPageinChapter++) {
                            Helpers::createPage($indexPage, $chapter->getId(), $catalogId);
                            $indexPage++;
                        }

                    }
                }

            }

            // Create static page
            if ($this->hasParam("pageStatic") and !empty($this->getParam("pageStatic"))) {
                foreach ($this->getParam("pageStatic") as $keyPageStatic => $pageStatic) {
                    Helpers::createPage($pageStatic, $catalogId, $catalogId);
                }
            }

            // Create Cover 3 and 4
            if ($this->getParam("coverPage") === "1") {
                Helpers::createPage($cover3, $catalogId, $catalogId);
                Helpers::createPage($cover4, $catalogId, $catalogId);
            }

            $catalog->setValues(["controller" => "catalogs", "action" => "tree"]);
            $catalog->setPublished(1);
            $catalog->save();

            Tool::sendJson(['success' => true, 'msg' => Translation::get('active_wireframe_form_success')]);

        } catch (\Exception $ex) {
            Tool::sendJson(['success' => false, 'msg' => $ex->getMessage()]);
        }
    }

    /**
     * @param $indexPage
     * @param $catalogId
     * @return mixed
     */
    public function createWithPaginate($indexPage, $catalogId)
    {
        $conf = [
            'catalogId' => $catalogId,
            'index' => $indexPage
        ];

        //Import by files // File is priority
        if (array_key_exists('paginatefile', $_FILES) and !empty($_FILES['paginatefile'])) {

            $conf['file'] = $_FILES['paginatefile'];
            $conf['col-chapter'] = $this->getParam('file-col-chap');
            $conf['col-page'] = $this->getParam('file-col-page');
            $conf['col-object'] = $this->getParam('file-col-object');
            $conf['col-area'] = $this->getParam('file-col-area');
            $conf['gridrow'] = $this->getParam('file-gridRow');
            $conf['gridcol'] = $this->getParam('file-gridCol');

            $classPaginate = "\\ActivePaginate\\Paginate";
            return call_user_func_array([$classPaginate, "getInstance"], ['file', $conf]);

        // Import by tree
        } elseif ($this->hasParam('targetClassFamily') and $this->getParam('targetClassFamily') != '-1'
            and $this->hasParam('targetFamily') and $this->getParam('targetFamily') != '0'
            and $this->hasParam('targetClassObject') and $this->getParam('targetClassObject') != '-1'
        ) {

            // @Todo
//            $conf['index'] = $indexPage;
//            $conf['templateId'] = $this->hasParam('targetTemplate') ?
//                trim($this->getParam('targetTemplate'))
//                : NULL;
//            $conf['grid'] = [
//                'row' => $this->hasParam('gridRow') ? intval($this->getParam('gridRow')) : 0,
//                'col' => $this->hasParam('gridCol') ? intval($this->getParam('gridCol')) : 0
//            ];
//
//            // flyleaf
//            if ($this->hasParam('frontPage') and intval($this->getParam('frontPage')) == 1) {
//                $conf['frontPage'] = [
//                    'type' => $this->getParam('frontPageTypeOption'),
//                    'template' => $this->hasParam('selectOTemplate')
//                        ? trim($this->getParam('selectOTemplate'))
//                        : '',
//                    'templateLvl' => $this->hasParam('selectOTemplateLvl')
//                        ? intval($this->getParam('selectOTemplateLvl'))
//                        : 1,
//                    'qteProduct' => $this->hasParam('selectQteProduct')
//                        ? intval($this->getParam('selectQteProduct'))
//                        : 0
//                ];
//            }
//
//            // Import by tree
//            $conf['tree'] = [
//                'familyClassId' => $this->getParam('targetClassFamily'),
//                'familyId' => $this->getParam('targetFamily'),
//                'objectClassId' => $this->getParam('targetClassObject')
//            ];
        }

        return $indexPage;
    }


    public function getTemplatesAction()
    {
        $this->disableViewAutoRender();
        $this->disableLayout();

        // Get format and orientation
        $format = $this->hasParam('format') ? $this->getParam('format') : 'a4';

        // exotic format
        if ($format == "other") {
            $templates = $this->getAssetThumbnailByPath(Plugin::ASSET_FOLDER_TEMPLATES . DIRECTORY_SEPARATOR);
        } else {
            $templates = $this->getAssetThumbnailByPath(Plugin::ASSET_FOLDER_TEMPLATES . DIRECTORY_SEPARATOR . $format);
        }

        Tool::sendJson(['success' => !empty($templates), 'templates' => $templates]);
    }

    /**
     * @param $pathToFolderAsset
     * @param array $files
     * @return array
     */
    public function getAssetThumbnailByPath($pathToFolderAsset, $files = [])
    {
        $assetFolder = Asset\Folder::getByPath($pathToFolderAsset);
        if ($assetFolder instanceof Asset\Folder and $assetFolder->hasChilds()) {

            // Config
            $thumbnailConf = [
                'format' => 'PNG',
                'width' => null,
                'height' => 150,
                'quality' => 90,
                'aspectratio' => true
            ];

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

}