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
     * AFfiche un page du PDF éditable en mode édition, et affiche le PDF généré en mode prévisualisation
     */
    public function pagesAction()
    {
        // définit le template
        $this->enableLayout();
        $this->setLayout("index");

        $this->view->baseUrl = Tool::getHostUrl();
        $this->view->documentId = $this->document->getId();
        $this->view->areadir = self::getAreaDir();
        $this->view->version = $this->editmode ? Util::getPluginVersion(Plugin::PLUGIN_NAME) : time();

        // Intégration d'Active Paginate
        $this->view->activepaginate = Util::pluginIsInstalled('ActivePaginate');

        // Récupère les informations de la page en BD
        $dbPage = new Pages();
        $page = $dbPage->getPage($this->document->getId());

        // Récupère les données du catalogue
        $dbCatalog = new Catalogs();
        $catalog = $dbCatalog->searchCatalogue($this->document->getId());

        // Information catalogue et page
        $this->view->catalog = $catalog;
        $this->view->page = $page;
        $this->view->pageLock = $this->document->isLocked();

        // Taille
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

        // Récupère les marges
        $marginLittle = ($catalog['margin_little'] == "") || (!array_key_exists('margin_little', $catalog)) ?
            0 :
            $catalog['margin_little'];

        $marginGreat = ($catalog['margin_great'] == "") || (!array_key_exists('margin_great', $catalog)) ?
            0 :
            $catalog['margin_great'];

        $marginTop = ($catalog['margin_top'] == "") || (!array_key_exists('margin_top', $catalog)) ?
            0 :
            $catalog['margin_top'];

        $marginBottom = ($catalog['margin_bottom'] == "") || (!array_key_exists('margin_bottom', $catalog)) ?
            0 :
            $catalog['margin_bottom'];

        // Applique les marges
        $this->view->paddingLeft = ($this->document->getKey() % 2)
            ? $marginLittle . "mm"
            : $marginGreat . "mm";

        $this->view->paddingRight = ($this->document->getKey() % 2)
            ? $marginGreat . "mm"
            : $marginLittle . "mm";

        $this->view->paddingTop = $marginTop . "mm";
        $this->view->paddingBottom = $marginBottom . "mm";

        // Information Areas
        $this->view->elementsData = $this->getElements();

        // Numero de page
        $this->view->numPage = ctype_digit($this->document->getKey()) ? $this->document->getKey() : null;

        // Active Paginate -> Grille
        $this->view->gridCol = ($page['grid_col'] != 0) ? $page['grid_col'] : 3;
        $this->view->gridRow = ($page['grid_row'] != 0) ? $page['grid_row'] : 4;

        // Thumbnail Editmode
        $thumbnail = ($this->editmode || $this->hasParam('nowkhtmltoimage'))
            ? ["format" => "PNG", "quality" => 90, "highResolution" => 1]
            : ["format" => "PNG", "quality" => 100, "highResolution" => 4];
        $this->view->thumbnail = $thumbnail;

        // Fond de page
        $this->view->template = $this->getTemplate($catalog, $this->document, $thumbnail);

        // Création d'une vignette
        if (!$this->editmode && !$this->hasParam('nowkhtmltoimage')) {
            Helpers::getPageThumbnailForTree($this->document, $width, $height);
        }
    }

    /**
     * Récupère les areas de l'utilisateur connecté
     * @return string
     */
    public static function getAreaDir()
    {
        // Récupère l'utilisateur
        $user = Util::getCurrentUser();

        // Role de l'utilisateur
        $roles = Util::getRolesFromCurrentUser();

        // Chemin des areas
        $areaPath = '/website/views/areas';
        $areaPathAbs = PIMCORE_WEBSITE_PATH . '/views/areas';

        // Pour la création du PDF
        if (!$user instanceof User) {
            $user = User::getById(0);
        }

        if ($user instanceof User && !$user->isAdmin() && !empty($roles)) {


            foreach ($roles as $roleId) {

                $pimcoreRole = UserRole::getById($roleId);
                if ($pimcoreRole instanceof UserRole
                    && file_exists($areaPathAbs . '/' . $pimcoreRole->getName())
                ) {
                    return $areaPath . '/' . $pimcoreRole->getName();
                }

            }

        } elseif ($user->isAdmin()) {

            // Area pour les admin
            return $areaPath . '/admin';

        }

        // Areas par defaut
        return $areaPath . '/active-wireframe';
    }

    /**
     * Récupères les données des areas de la page
     * @return array
     */
    public function getElements()
    {
        $dbElement = new Elements();
        $els = $dbElement->getElementsByDocumentId($this->document->getId());

        // Initialise le tableau de retour
        $retEls = array();

        foreach ($els as $el) {
            $retEls[$el['e_key']] = $el;
        }

        return $retEls;
    }

    /**
     * Récupère le template de page
     * @param $catalog
     * @param Document $page
     * @param $thumbnail
     * @return mixed|null|string
     */
    public function getTemplate($catalog, Document $page, $thumbnail)
    {
        $templatePage = null;

        // Vérifie que la page ne soit pas une page statique
        if ($catalog['document_id'] != $page->getParentId()) {

            // Récupère le numero de la page afin de déterminé le template
            $assetTemplate = ($page->getKey() % 2)
                ? Asset::getById($catalog['template_odd'])
                : Asset::getById($catalog['template_even']);

            // Création de l'image de l'asset
            if (is_object($assetTemplate)) {

                if ($assetTemplate instanceof Asset\Document) {
                    $templatePage = $assetTemplate->getImageThumbnail($thumbnail)->getPath();
                } else if ($assetTemplate instanceof Asset\Image) {
                    $templatePage = $assetTemplate->getThumbnail($thumbnail)->getPath();
                }

            }

        }

        return $templatePage;
    }

}
