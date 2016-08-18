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
 * @copyright   Copyright (c) 2016 Active Publishing (http://www.active-publishing.fr)
 * @license     http://creativecommons.org/licenses/by-nc-nd/4.0/
 */
namespace ActiveWireframe;

use ActivePublishing\Plugin\Service;
use ActivePublishing\Services\DocType;
use ActivePublishing\Services\File;
use ActivePublishing\Services\Response;
use ActivePublishing\Services\Table;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Elements;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Pimcore\Console\Command\Web2PrintPdfCreationCommand;
use Pimcore\API\Plugin as PluginLib;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;

/**
 * Class Plugin
 * @package ActiveWireframe
 */
class Plugin extends PluginLib\AbstractPlugin implements PluginLib\PluginInterface
{
    const PLUGIN_NAME = "ActiveWireframe";

    const PLUGIN_PATH = PIMCORE_PLUGINS_PATH . '/' . self::PLUGIN_NAME;

    const PLUGIN_PATH_TMP = PIMCORE_DOCUMENT_ROOT . "/activetmp/" . self::PLUGIN_NAME;

    const PLUGIN_PATH_INSTALL = self::PLUGIN_PATH . '/static/install';

    const PLUGIN_PATH_TRANSLATION = self::PLUGIN_PATH . '/static/texts';

    const PLUGIN_PATH_INSTALL_TABLES = self::PLUGIN_PATH_INSTALL . '/tables.json';

    const PLUGIN_PATH_INSTALL_DOCTYPES = self::PLUGIN_PATH_INSTALL . '/doctypes.json';

    const PLUGIN_PATH_INSTALL_AREAS = self::PLUGIN_PATH_INSTALL . '/areas';

    const PLUGIN_VAR_PATH = PIMCORE_WEBSITE_VAR . "/plugins/" . self::PLUGIN_NAME;

    const PLUGIN_VAR_PATH_INSTALL = self::PLUGIN_VAR_PATH . '/install.txt';

    const PLUGIN_VAR_PATH_UNINSTALL = self::PLUGIN_VAR_PATH . '/uninstall.txt';

    public static $_needsReloadAfterInstall = false;

    /**
     * @return string
     */
    public static function needsReloadAfterInstall()
    {
        return self::$_needsReloadAfterInstall;
    }

    /**
     * @return mixed
     */
    public static function install()
    {
        // Librairies activepublishing composer introuvable
        if (!self::composerExists()) {
            return 'ERROR: Active Publishing Composer does not exist.';
        }

        try {

            // Instance Plugin Service
            $plugin = new Service();

            // Install la traduction pour le plugin
            $plugin->createTranslation(self::PLUGIN_PATH_TRANSLATION, self::getPrefixTranslate());

            // Ajout le dossier d'area
            if (file_exists(self::PLUGIN_PATH_INSTALL_AREAS)) {
                $plugin->createAreas(self::PLUGIN_PATH_INSTALL_AREAS);
            }

            // Installations des tables
            if (file_exists(self::PLUGIN_PATH_INSTALL_TABLES)) {
                Table::createFromJson(self::PLUGIN_PATH_INSTALL_TABLES);
            }

            // Installations des doctypes
            if (file_exists(self::PLUGIN_PATH_INSTALL_DOCTYPES)) {
                DocType::createFromJson(self::PLUGIN_PATH_INSTALL_DOCTYPES);
            }

            // Création de la vignettes active-wireframe-preview
            if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-preview")) {
                $pipe = new Asset\Image\Thumbnail\Config();
                $pipe->setName("active-wireframe-preview");
                $pipe->setQuality(80);
                $pipe->setFormat("PNG");
                $pipe->save();
            }

            // Création de la vignettes active-wireframe-preview
            if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-print")) {
                $pipe = new Asset\Image\Thumbnail\Config();
                $pipe->setName("active-wireframe-print");
                $pipe->setQuality(100);
                $pipe->setFormat("PNG");
                $pipe->setHighResolution(3);
                $pipe->save();
            }

            // Création du dossier gabarit de pages dans les fichiers
            self::createDirectoryTemplates();

            // Tous est OK, création du fichier d'installation
            $plugin->createFileInstall(true, self::PLUGIN_VAR_PATH_INSTALL, self::PLUGIN_VAR_PATH_UNINSTALL);

        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        // Install OK
        self::$_needsReloadAfterInstall = true;
        return 'Install success.';
    }

    /**
     * @return bool
     */
    public static function composerExists()
    {
        return file_exists(PIMCORE_DOCUMENT_ROOT . "/vendor/activepublishing");
    }

    /**
     * @return string
     */
    private static function getPrefixTranslate()
    {
        return mb_strtolower(preg_replace('/(?=(?<!^)[[:upper:]])/', '_', self::PLUGIN_NAME) . "_");
    }

    /**
     * Créer le dossier de template pour les fichiers
     * @throws \Exception
     */
    public static function createDirectoryTemplates()
    {
        // création du dossier asset Template
        $assetRoot = Asset\Folder::getByPath("/gabarits-de-pages");

        if (!$assetRoot instanceof Asset\Folder) {
            $fichierRacine = Asset::getById(1);
            Asset\Service::createFolderByPath($fichierRacine->getFullPath() . '/gabarits-de-pages');
        }
    }

    /**
     * @return string
     */
    public static function uninstall()
    {
        if (!self::composerExists()) {
            return 'ERROR: Active Publishing Composer does not exist.';
        }

        try {
            // Instance
            $plugin = new Service();

            // Désinstalle la traduction pour le plugin
            $plugin->rmTranslation(self::PLUGIN_PATH_TRANSLATION, self::getPrefixTranslate());

            // Tous est OK, création du fichier de désinstallation
            $plugin->createFileInstall(false, self::PLUGIN_VAR_PATH_INSTALL, self::PLUGIN_VAR_PATH_UNINSTALL);

        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

        // Uninstall OK
        self::$_needsReloadAfterInstall = true;
        return 'Uninstall success !';
    }

    /**
     * @return bool
     */
    public static function isInstalled()
    {
        return file_exists(self::PLUGIN_VAR_PATH_INSTALL);
    }

    /**
     * @throws \Zend_EventManager_Exception_InvalidArgumentException
     */
    public function init()
    {
        parent::init();

        // Ajoute les fonctions d'évènement sur les documents
        \Pimcore::getEventManager()->attach("document.postAdd", array($this, "addEventDocumentPostAdd"));
        \Pimcore::getEventManager()->attach("document.postDelete", array($this, "addEventDocumentPostDelete"));
        \Pimcore::getEventManager()->attach("document.postUpdate", array($this, "addEventDocumentPostUpdate"));

        // Console
        \Pimcore::getEventManager()->attach('system.console.init', function (\Zend_EventManager_Event $e) {
            /** @var \Pimcore\Console\Application $application */
            $application = $e->getTarget();

            // add a single command
            $application->add(new Web2PrintPdfCreationCommand());
        });
    }

    /**
     * @param $event
     * @return int
     * @throws \Exception
     */
    public function addEventDocumentPostAdd($event)
    {
        $document = $event->getTarget();

        if (($document instanceof Document\Printcontainer || $document instanceof Document\Printpage)
            && $document->getModule() == Plugin::PLUGIN_NAME
        ) {

            $document->setPublished(1);
            $document->save();

            // Cas de la copie
            $server = $_SERVER;
            $redirectUrl = isset($server['REDIRECT_URL']) ? explode('/', $server['REDIRECT_URL']) : false;
            array_shift($redirectUrl);
            $copy = (($redirectUrl[1] == 'document') && ($redirectUrl[2] == 'copy'));

            if ($copy && isset($_GET['sourceId'])) {

                $sourceId = $_GET['sourceId'];

                // Test si le document copié est un catalogue, un chapitre ou une page d'un chemin de fer
                $dbcatalogs = new Catalogs();
                $catalog = $dbcatalogs->getCatalog($sourceId);

                // C'est un catalogue
                if ($catalog) {

                    // Clone les données du catalogue
                    // + Suppréssion de l'id de la bd
                    // + modification de l'id du document
                    unset($catalog['id']);
                    $catalog['document_id'] = $document->getId();

                    try {

                        $dbcatalogs->insert($catalog);

                    } catch (\Exception $ex) {
                        $document->delete();
                        return Response::setResponseJson(array('success' => false));
                    };

                } else {
                    // Recherche si c'est une page ou un chapitre

                    $dbpages = new Pages();
                    $pageCatalogue = $dbpages->getPage($sourceId);

                    if (is_array($pageCatalogue)) {

                        // recherche le niveau de la page : 1er niveau ou page dans un chapitre
                        $pageInChapter = true;
                        if (($pageCatalogue['document_parent_id'] == $pageCatalogue['document_root_id'])
                            && $pageCatalogue['document_type'] == 'page'
                        ) {
                            $pageInChapter = false;
                        }

                        // On modifie les informations d'identifications des données
                        unset($pageCatalogue['id']);
                        $pageCatalogue['document_id'] = $document->getId();
                        $pageCatalogue['document_parent_id'] = $document->getParentId();
                        $pageCatalogue['document_root_id'] = $document->getParentId();

                        // Page
                        if ($pageCatalogue['document_type'] == 'page') {

                            if ($pageInChapter) {
                                $pageCatalogue['document_root_id'] = $document->getParent()->getParentId();
                            }

                            // Récupère les éléments de la page source
                            $dbelements = new Elements();
                            $elements = $dbelements->getElementsByDocumentId($sourceId);

                            if (!empty($elements)) {

                                foreach ($elements as $element) {

                                    unset($element['id']);
                                    $element['document_id'] = $document->getId();
                                    $element['document_parent_id'] = $document->getParentId();
                                    $element['document_root_id'] = $document->getParent()->getParentId();

                                    try {

                                        $dbelements->insert($element);

                                    } catch (\Exception $ex) {
                                        \Logger::err($ex);
                                    }

                                }

                            }

                        }

                        try {

                            // Insert les données du document cloné
                            $dbpages->insert($pageCatalogue);

                        } catch (\Exception $ex) {

                            $document->delete();
                            return Response::setResponseJson(array('success' => false));

                        };
                    }

                }

            } elseif ($document instanceof Document\Printpage && $document->getAction() == 'pages') {
                // Ajout d'une page

                // Recherche si le parent est un chapitre
                $dbpage = new Pages();
                $pageParentId = $dbpage->getPage($document->getParentId());

                $selectPage = [];
                $selectPage['document_id'] = $document->getId();
                $selectPage['document_type'] = "page";

                // Le parent est un chapitre
                if (is_array($pageParentId) && $pageParentId['document_type'] == "chapter") {

                    // Modification des données de la page
                    $selectPage['document_parent_id'] = $document->getParentId();
                    $selectPage['document_root_id'] = $document->getParent()->getParentId();

                } elseif (!$pageParentId) {

                    // Le parent n'est pas un chapitre, on test si c'est un catalogue
                    $dbcatalog = new Catalogs();
                    $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                    // Le parent est le catalogue
                    if ($resultCatalog) {

                        // Modification des données de la page
                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParentId();
                    }

                } else {

                    // Modification des données de la page
                    $selectPage['document_parent_id'] = 0;
                    $selectPage['document_root_id'] = 0;

                }

                // Informations liées au document
                $selectPage['page_key'] = $document->getKey();
                $selectPage['locked'] = $document->getLocked();

                // Insert
                $dbpage->insert($selectPage);

            } elseif ($document instanceof Document\Printcontainer && $document->getAction() == 'tree') {
                // Ajout d'un chapitre

                $selectPage = [];
                $selectPage['document_id'] = $document->getId();
                $selectPage['document_type'] = "chapter";

                // Le parent n'est pas un chapitre, on test si c'est un catalogue
                $dbcatalog = new Catalogs();
                $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                // Le parent est le catalogue
                if ($resultCatalog) {

                    // Modification des données de la page
                    $selectPage['document_parent_id'] = $document->getParentId();
                    $selectPage['document_root_id'] = $document->getParentId();

                } else {

                    // Modification des données de la page
                    $selectPage['document_parent_id'] = 0;
                    $selectPage['document_root_id'] = 0;

                }

                // Informations liées au document
                $selectPage['page_key'] = $document->getKey();
                $selectPage['locked'] = $document->getLocked();

                // Insert
                $dbpage = new Pages();
                $dbpage->insert($selectPage);

            }

        }

        return 1;
    }

    /**
     * @param $event
     */
    public function addEventDocumentPostDelete($event)
    {
        $document = $event->getTarget();

        if (self::composerExists()
            && ($document instanceof Document\Printcontainer || $document instanceof Document\Printpage)
            && $document->getModule() == Plugin::PLUGIN_NAME
        ) {

            if ($document instanceof Document\Printpage && $document->getAction() == 'pages') {
                // Ajout d'une page

                // Recherche la page
                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                if (is_array($selectPage)) {

                    // Supprime l'entrée en BD
                    $count = $dbpage->deletePageByDocumentId($document->getId());

                    // Supprime les enregistrements des areas + dossier tmp
                    if ($count > 0) {

                        // On récupère l'entrée du document dans la table ActiveWireframe_Db_Elements
                        $dbElements = new Elements();
                        $dbElements->deleteByKey('document_id', $document->getId());

                        // Supprime le dossier physique
                        $dirTmp = PIMCORE_DOCUMENT_ROOT . DIRECTORY_SEPARATOR
                            . "activetmp" . DIRECTORY_SEPARATOR
                            . Plugin::PLUGIN_NAME . DIRECTORY_SEPARATOR
                            . $document->getId();

                        if (file_exists($dirTmp)) {
                            File::rm($dirTmp);
                        }

                    }

                }

            } elseif ($document instanceof Document\Printcontainer && ($document->getAction() == 'tree')) {

                // Recherche la chapitre
                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                // Chapitre trouvé
                if (is_array($selectPage)) {

                    $dbpage->deletePageByDocumentId($document->getId());

                } else {
                    // Recherche si le document est un catalogue

                    // On récupère l'entrée du document
                    $dbcatalogs = new Catalogs();
                    $selectCat = $dbcatalogs->getCatalog($document->getId());

                    if (is_array($selectCat)) {

                        // Supprime l'entrée en BD
                        $wherePage = $dbcatalogs->getAdapter()->quoteInto('document_id = ?', $document->getId());
                        $dbcatalogs->delete($wherePage);

                    }

                }

            }

        }

    }

    /**
     * @param $event
     * @return int
     * @throws \Exception
     */
    public function addEventDocumentPostUpdate($event)
    {
        $document = $event->getTarget();

        if (($document instanceof Document\Printcontainer || $document instanceof Document\Printpage)
            && ($document->getModule() == Plugin::PLUGIN_NAME)
        ) {

            if ($document instanceof Document\Printpage) {

                // Récupère la ligne du document active_wireframe_pages en BD
                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                if (is_array($selectPage)) {

                    // Recherche si le parent est un chapitre
                    $pageParentId = $dbpage->getPage($document->getParentId());

                    // Le parent est un chapitre
                    if (is_array($pageParentId) && $pageParentId['document_type'] == "chapter") {

                        // Modification des données de la page
                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParent()->getParentId();

                    } elseif (!$pageParentId) {

                        // Le parent n'est pas un chapitre, on test si c'est un catalogue
                        $dbcatalog = new Catalogs();
                        $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                        // Le parent est le catalogue
                        if ($resultCatalog) {

                            // Modification des données de la page
                            $selectPage['document_parent_id'] = $document->getParentId();
                            $selectPage['document_root_id'] = $document->getParentId();
                        }

                    } else {

                        // Modification des données de la page
                        $selectPage['document_parent_id'] = 0;
                        $selectPage['document_root_id'] = 0;

                    }

                    // Informations liées au document
                    $selectPage['page_key'] = $document->getKey();
                    $selectPage['locked'] = $document->getLocked();

                    // MAJ
                    $where = $dbpage->getAdapter()->quoteInto('document_id = ?', $document->getId());
                    $dbpage->update($selectPage, $where);

                }

            } elseif ($document instanceof Document\Printcontainer) {

                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                // On recherche si le document est un chapitre
                if (is_array($selectPage) && $selectPage['document_type'] == "chapter") {

                    // On recherche s'il est un enfant d'un catalogue
                    $dbcatalog = new Catalogs();
                    $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                    // Le document Parent est bien un catalogue
                    if ($resultCatalog) {

                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParentId();

                    } else {

                        $selectPage['document_parent_id'] = 0;
                        $selectPage['document_root_id'] = 0;

                    }

                    // Informations du document
                    $selectPage['page_key'] = $document->getKey();
                    $selectPage['locked'] = $document->getLocked();


                    // MAJ
                    $where = $dbpage->getAdapter()->quoteInto('document_id = ?', $document->getId());
                    $dbpage->update($selectPage, $where);

                    // Maj des pages enfantes
                    foreach ($document->getChilds() as $child) {

                        if ($child instanceof Document) {
                            $child->save();
                        }
                    }

                }

            }

        }

        return 1;

    }

}
