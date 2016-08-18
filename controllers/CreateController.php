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
use ActivePaginate\Plugin as ActivePaginatePlugin;
use ActivePublishing\Services\Response;
use ActivePublishing\Services\Translation;
use ActivePublishing\Services\Util;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Plugin;
use Pimcore\File;
use Pimcore\Model\Asset;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Pimcore\Tool;
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
            echo 'ERROR: Active Publishing - Composer does not exist.';
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
     * Affiche le formulaire de création
     */
    public function formAction()
    {
        // Active le template
        $this->enableLayout();
        $this->setLayout("index");

        $this->view->documentId = $this->document->getId();
        $this->view->version = Util::getPluginVersion(Plugin::PLUGIN_NAME);

        // Formulaire paginate
        $scriptFormPaginate = (Util::pluginIsInstalled('ActivePaginate'))
            ? '/plugins/' . ActivePaginatePlugin::PLUGIN_NAME . '/views/scripts/form.php'
            : Null;

        // Intégration du Paginate
        $this->view->templateFormPaginate = $scriptFormPaginate;
    }

    /**
     * Création du catalogue
     * @return int
     */
    public function catalogAction()
    {
        // Désactive le template
        $this->disableViewAutoRender();
        $this->disableLayout();
        $this->disableBrowserCache();

        // Initialisation
        $success = true;
        $msg = Translation::get('active_wireframe_form_success');
        $cover1 = Translation::get('active_wireframe_first_cover');
        $cover2 = Translation::get('active_wireframe_second_cover');
        $cover3 = Translation::get('active_wireframe_third_cover');
        $cover4 = Translation::get('active_wireframe_fourth_cover');

        // Récupère les informations du catalogue
        $documentId = $this->getParam('documentId');
        $documentRoot = Printcontainer::getById($documentId);

        // Orientation
        $orientation = $this->getParam('orientation');

        // Format du catalogue
        if ($this->getParam('format') != "null") {

            $formatPageArray = explode("x", $this->getParam('format'));
            $formatPageWidth = $formatPageArray[0];
            $formatPageHeight = $formatPageArray[1];

        } else {

            $formatPageWidth = $this->hasParam("format-width") ? $this->getParam("format-width") : 210;
            $formatPageHeight = $this->hasParam("format-height") ? $this->getParam("format-height") : 297;

        }

        // Marges
        $margin_little = ($this->hasParam('margin-little') || ($this->getParam('margin-little') != ""))
            ? $this->getParam('margin-little')
            : 0;

        $margin_great = ($this->hasParam('margin-great') || ($this->getParam('margin-great') != ""))
            ? $this->getParam('margin-great')
            : 0;

        $margin_top = ($this->hasParam('margin-top') || ($this->getParam('margin-top') != ""))
            ? $this->getParam('margin-top')
            : 0;

        $margin_bottom = ($this->hasParam('margin-bottom') || ($this->getParam('margin-bottom') != ""))
            ? $this->getParam('margin-bottom')
            : 0;

        // Templates des pages
        $template_odd = ($this->hasParam('template-odd') || ($this->getParam('template-odd') != ""))
            ? $this->getParam('template-odd')
            : 0;

        $template_even = ($this->hasParam('template-even') || ($this->getParam('template-even') != ""))
            ? $this->getParam('template-even')
            : 0;

        // Enregistrement des options du catalogue
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

        // Instance de la DB
        $dbCatalog = new Catalogs();
        $dbCatalog->insert($optionsCatalog);

        // Création de la 1er et 2eme de couverture
        if ($this->getParam("coverPage") === "1") {
            self::createPage($cover1, $documentId);
            self::createPage($cover2, $documentId);
        }

        // Index de départ
        $indexPage = ($this->getParam("coverPage") === "1") ? 3 : 1;

        // Active Paginate
        $indexPage = $this->createWithPaginate($indexPage, $documentId);

        // Création des chapitres
        if ($this->hasParam("chapters") && !empty($this->getParam("chapters"))) {

            $chapCreated = array();
            $chapCreated[] = "";

            // Création des chapitres
            foreach ($this->getParam("chapters") as $keyChap => $nameChap) {

                // Formate le nom
                $nameChap = in_array(trim($nameChap), $chapCreated) || trim($nameChap) == "" ?
                    trim($nameChap) . '-' . $keyChap :
                    trim($nameChap);

                // Création + insertion en BD
                $chapter = self::createChapter($nameChap, $documentId);

                // Création des pages du chapitres
                $keyPageMax = $this->getParam("numbPages");

                for ($keyPageinChapter = 1; $keyPageinChapter <= $keyPageMax[$keyChap]; ++$keyPageinChapter) {

                    self::createPage($indexPage, $chapter->getId());
                    ++$indexPage;

                }

                // Incrémente le tableau
                $chapCreated[] = $nameChap;
            }

        }

        // Creation des pages statiques
        if ($this->hasParam("pageStatic") && !empty($this->getParam("pageStatic"))) {

            $pageStaticCreated = array();
            $pageStaticCreated[] = "";

            foreach ($this->getParam("pageStatic") as $keyPageStatic => $pageStatic) {

                // Le nom de la page existe
                if (trim($pageStatic) != '') {

                    // Formate le nom
                    $pageStatic = in_array(trim($pageStatic), $pageStaticCreated) ?
                        trim($pageStatic) . '-' . $keyPageStatic :
                        trim($pageStatic);

                    // Création de la page static
                    self::createPage($pageStatic, $documentId);
                    $pageStaticCreated[] = $pageStatic;

                }
            }

        }

        // Creation de la 3ème et 4ème de couverture
        if ($this->getParam("coverPage") === "1") {
            self::createPage($cover3, $documentId);
            self::createPage($cover4, $documentId);
        }

        // Change le controlleur et l'action du document
        try {

            $documentRoot->setValues(array("controller" => "catalogs", "action" => "tree"));
            $documentRoot->setPublished(1);
            $documentRoot->save();

        } catch (\Exception $ex) {
            $success = false;
            $msg = $ex->getMessage();
        }

        return Response::setResponseJson(array(
            'success' => $success,
            'msg' => $msg
        ));
    }

    /**
     * Création d'une page (Document + insertion en base de donnée)
     * @param $key
     * @param $parentId
     * @param array $configs
     * @return bool|Printpage
     */
    public static function createPage($key, $parentId, $configs = array())
    {
        // Valide la clé de la page
        if (!Tool::isValidKey($key)) {
            $key = File::getValidFilename($key);
        }

        // Attribut de la page
        $dataDocument = array(
            "key" => $key,
            "published" => 1,
            "module" => "ActiveWireframe",
            "controller" => "pages",
            "action" => "pages"
        );

        try {

            // Création du document
            $page = Printpage::create($parentId, $dataDocument);

            // Insert en BD
            $dbPages = new Pages();
            $where = $dbPages->getAdapter()->quoteInto('document_id = ?', $page->getId());
            $dbPages->update($configs, $where);

            // Fin de la modification de la page
            $page->setModificationDate(time());
            $page->save();

        } catch (\Exception $ex) {

            \Logger::err($ex->getMessage());
            return false;

        }

        return $page;
    }

    /**
     * Création des pages avec le module Active Paginate
     * @param $indexPage
     * @param $documentId
     * @return bool
     */
    public function createWithPaginate($indexPage, $documentId)
    {
        // Plugin Paginate installé ET Arborescence ou fichier CSV sélectionné
        if (Util::pluginIsInstalled('ActivePaginate')
            && (($this->hasParam('filenames') && $this->getParam('filenames') != '')
                || ($this->hasParam('targetClassFamily') && $this->getParam('targetClassFamily') != '-1'))
        ) {

            // Récupère template et grille
            $import = array();
            $import['index'] = $indexPage;
            $import['templateId'] = $this->hasParam('targetTemplate') ? trim($this->getParam('targetTemplate')) : '';
            $import['grid'] = array(
                'row' => $this->hasParam('gridRow') ? intval($this->getParam('gridRow')) : 0,
                'col' => $this->hasParam('gridCol') ? intval($this->getParam('gridCol')) : 0
            );

            // Page de garde
            if ($this->hasParam('frontPage') && intval($this->getParam('frontPage')) == 1) {
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

            // Fichier CSV envoyé
            if ($this->hasParam('filenames') && $this->getParam('filenames') != '') {
                // importation des produits depuis le(s) fichier(s)
                $import['csv'] = $this->getParam('filenames');
                $indexPage = ActivePaginate\Form::ProductsImport($documentId, $import, 'csv');

                // Importation par arboréscence
            } elseif ($this->hasParam('targetClassFamily') && $this->getParam('targetClassFamily') != '-1'
                && $this->hasParam('targetFamily') && $this->getParam('targetFamily') != '0'
                && $this->hasParam('targetClassObject') && $this->getParam('targetClassObject') != '-1'
            ) {

                $import['tree'] = array(
                    'familyClassId' => $this->getParam('targetClassFamily'),
                    'familyId' => $this->getParam('targetFamily'),
                    'objectClassId' => $this->getParam('targetClassObject')
                );

                $indexPage = ActivePaginate\Form::ProductsImport($documentId, $import, 'tree');
            }
        }

        return $indexPage;
    }

    /**
     * Création d'un chapitre (Document + insertion en base de donnée)
     * @param $key
     * @param $parentId
     * @return bool|Printcontainer
     */
    public static function createChapter($key, $parentId)
    {
        // Format la clé
        if (!Tool::isValidKey($key)) {
            $key = File::getValidFilename($key);
        }

        // Attribut du chapitre
        $dataDocument = array(
            "key" => $key,
            "published" => 1,
            "module" => "ActiveWireframe",
            "controller" => "catalogs",
            "action" => "tree"
        );

        try {

            $chapter = Printcontainer::create($parentId, $dataDocument);

        } catch (\Exception $ex) {

            \Logger::err($ex->getMessage());
            return false;

        }

        return $chapter;
    }

    /**
     * Retourne les fichiers templates
     * @return int
     */
    public function getTemplatesAction()
    {
        $this->disableViewAutoRender();
        $this->disableLayout();
        $this->disableBrowserCache();

        // Récupère le format + orientation
        $format = $this->hasParam('format') ? $this->getParam('format') : 'a4';
        $templates = [];

        // Format exotique
        if ($format == "other") {

            foreach (['other', 'others', 'autre', 'autres', 'divers'] as $folerTitle) {
                $templatesExo = $this->getAssetThumbnailByPath('/gabarits-de-pages/' . $folerTitle);
                $templates = array_merge($templates, $templatesExo);
            }

        } else {
            $templates = $this->getAssetThumbnailByPath('/gabarits-de-pages/' . $format);
        }

        // Reponse
        return Response::setResponseJson(array(
            'success' => !empty($templates),
            'templates' => $templates
        ));
    }

    /**
     * Récupère le chemin des thumbnails des templates de pages
     * @param $pathToFolderAsset
     * @param array $files
     * @return array
     */
    public function getAssetThumbnailByPath($pathToFolderAsset, $files = array())
    {
        $assetFolder = Asset\Folder::getByPath($pathToFolderAsset);
        if ($assetFolder instanceof Asset\Folder && $assetFolder->hasChilds()) {

            // thumbnails
            $thumbnail = array(
                'quality' => 90,
                'format' => 'png',
                "aspectratio" => true,
                "height" => 150
            );

            foreach ($assetFolder->getChilds() as $child) {

                // Count array
                $i = count($files);

                // Création du thumb
                if ($child instanceof Asset\Document) {

                    $files[$i]['thumb'] = $child->getImageThumbnail($thumbnail)->getPath();
                    $files[$i]['id'] = $child->getId();

                } else if ($child instanceof Asset\Image) {

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
     * Télécharge les fichiers PDF envoyé lors de la création d'un catalogue
     */
    public function uploadFilesAction()
    {
        $this->disableLayout();
        $this->disableViewAutoRender();
        $this->disableBrowserCache();

        $files = array();
        $uploaddir = PIMCORE_TEMPORARY_DIRECTORY;

        // Récupère les fichiers
        if (!empty($_FILES)) {

            foreach ($_FILES as $file) {

                $filename = basename($file['name']);

                // Chemin du fichier tmp
                $filepath = PIMCORE_TEMPORARY_DIRECTORY . "/" . $filename;

                // Clear
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }

                // Téléchargement du fichier
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