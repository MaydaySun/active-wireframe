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
use Pimcore\Logger;
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
        if (!self::composerExists()) {
            return 'ERROR: Active Publishing Composer does not exist.';
        }

        try {
            $plugin = new Service();

            // Install translation plugin
            $plugin->createTranslation(self::PLUGIN_PATH_TRANSLATION, self::getPrefixTranslate());

            // Add area directory
            if (file_exists(self::PLUGIN_PATH_INSTALL_AREAS)) {
                $plugin->createAreas(self::PLUGIN_PATH_INSTALL_AREAS);
            }

            // Add Tables
            if (file_exists(self::PLUGIN_PATH_INSTALL_TABLES)) {
                Table::createFromJson(self::PLUGIN_PATH_INSTALL_TABLES);
            }

            // Add doctypes
            if (file_exists(self::PLUGIN_PATH_INSTALL_DOCTYPES)) {
                DocType::createFromJson(self::PLUGIN_PATH_INSTALL_DOCTYPES);
            }

            // Add thumbnail active-wireframe-preview
            if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-preview")) {
                $pipe = new Asset\Image\Thumbnail\Config();
                $pipe->setName("active-wireframe-preview");
                $pipe->setQuality(80);
                $pipe->setFormat("PNG");
                $pipe->save();
            }

            // Add thumbnail active-wireframe-preview
            if (!Asset\Image\Thumbnail\Config::getByAutoDetect("active-wireframe-print")) {
                $pipe = new Asset\Image\Thumbnail\Config();
                $pipe->setName("active-wireframe-print");
                $pipe->setQuality(100);
                $pipe->setFormat("PNG");
                $pipe->setHighResolution(3.2);
                $pipe->save();
            }

            // Create template directory
            self::createDirectoryTemplates();

            $plugin->createFileInstall(true, self::PLUGIN_VAR_PATH_INSTALL, self::PLUGIN_VAR_PATH_UNINSTALL);
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

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
     * Create directory template
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
            $plugin = new Service();

            // Uninstall translation
            $plugin->rmTranslation(self::PLUGIN_PATH_TRANSLATION, self::getPrefixTranslate());

            $plugin->createFileInstall(false, self::PLUGIN_VAR_PATH_INSTALL, self::PLUGIN_VAR_PATH_UNINSTALL);
        } catch (\Exception $ex) {
            return $ex->getMessage();
        }

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
     * @return bool
     */
    public function addEventDocumentPostAdd($event)
    {
        $document = $event->getTarget();

        if (($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and $document->getModule() == Plugin::PLUGIN_NAME
        ) {

            $document->setPublished(1);
            $document->save();

            // copy
            $server = $_SERVER;
            $redirectUrl = isset($server['REDIRECT_URL']) ? explode('/', $server['REDIRECT_URL']) : false;
            array_shift($redirectUrl);
            $copy = (($redirectUrl[1] == 'document') and ($redirectUrl[2] == 'copy'));

            if ($copy and isset($_GET['sourceId'])) {

                $sourceId = $_GET['sourceId'];

                // if the copied cocument is a catalog or chapter or page
                $dbcatalogs = new Catalogs();
                $catalog = $dbcatalogs->getCatalog($sourceId);

                if ($catalog) {

                    // clone
                    unset($catalog['id']);
                    $catalog['document_id'] = $document->getId();

                    try {
                        $dbcatalogs->insert($catalog);

                    } catch (\Exception $ex) {
                        $document->delete();
                        return Response::setResponseJson(array('success' => false));
                    };

                } else {

                    $dbpages = new Pages();
                    $pageCatalogue = $dbpages->getPage($sourceId);

                    if (is_array($pageCatalogue)) {

                        $pageInChapter = true;
                        if (($pageCatalogue['document_parent_id'] == $pageCatalogue['document_root_id'])
                            and $pageCatalogue['document_type'] == 'page'
                        ) {
                            $pageInChapter = false;
                        }

                        // update informations
                        unset($pageCatalogue['id']);
                        $pageCatalogue['document_id'] = $document->getId();
                        $pageCatalogue['document_parent_id'] = $document->getParentId();
                        $pageCatalogue['document_root_id'] = $document->getParentId();

                        // Page
                        if ($pageCatalogue['document_type'] == 'page') {

                            if ($pageInChapter) {
                                $pageCatalogue['document_root_id'] = $document->getParent()->getParentId();
                            }

                            // Retrieve elements
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
                                        Logger::err($ex);
                                    }

                                }

                            }

                        }

                        try {
                            // Add cloned data
                            $dbpages->insert($pageCatalogue);

                        } catch (\Exception $ex) {
                            $document->delete();
                            return Response::setResponseJson(array('success' => false));
                        };
                    }

                }

            } elseif ($document instanceof Document\Printpage and $document->getAction() == 'pages') {

                $dbpage = new Pages();
                $pageParentId = $dbpage->getPage($document->getParentId());

                $selectPage = [];
                $selectPage['document_id'] = $document->getId();
                $selectPage['document_type'] = "page";

                // Parent is a chapter
                if (is_array($pageParentId) and $pageParentId['document_type'] == "chapter") {

                    $selectPage['document_parent_id'] = $document->getParentId();
                    $selectPage['document_root_id'] = $document->getParent()->getParentId();

                } elseif (!$pageParentId) {

                    $dbcatalog = new Catalogs();
                    $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                    // Parent is a catalog
                    if ($resultCatalog) {
                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParentId();
                    }

                } else {
                    $selectPage['document_parent_id'] = 0;
                    $selectPage['document_root_id'] = 0;
                }

                $selectPage['page_key'] = $document->getKey();
                $selectPage['locked'] = $document->getLocked();

                $dbpage->insert($selectPage);

            } elseif ($document instanceof Document\Printcontainer and $document->getAction() == 'tree') {

                $selectPage = [];
                $selectPage['document_id'] = $document->getId();
                $selectPage['document_type'] = "chapter";

                $dbcatalog = new Catalogs();
                $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                // Parent is a catalog
                if ($resultCatalog) {
                    $selectPage['document_parent_id'] = $document->getParentId();
                    $selectPage['document_root_id'] = $document->getParentId();
                } else {
                    $selectPage['document_parent_id'] = 0;
                    $selectPage['document_root_id'] = 0;
                }

                $selectPage['page_key'] = $document->getKey();
                $selectPage['locked'] = $document->getLocked();

                $dbpage = new Pages();
                $dbpage->insert($selectPage);
            }

        }

        return true;
    }

    /**
     * @param $event
     */
    public function addEventDocumentPostDelete($event)
    {
        $document = $event->getTarget();

        if (self::composerExists()
            and ($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and $document->getModule() == Plugin::PLUGIN_NAME
        ) {

            if ($document instanceof Document\Printpage and $document->getAction() == 'pages') {

                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                if (is_array($selectPage)) {

                    // Delete in DB
                    $count = $dbpage->deletePageByDocumentId($document->getId());
                    if ($count > 0) {

                        $dbElements = new Elements();
                        $dbElements->deleteByKey('document_id', $document->getId());

                        // Delete directory
                        $dirTmp = PIMCORE_DOCUMENT_ROOT . DIRECTORY_SEPARATOR
                            . "activetmp" . DIRECTORY_SEPARATOR
                            . Plugin::PLUGIN_NAME . DIRECTORY_SEPARATOR
                            . $document->getId();

                        if (file_exists($dirTmp)) {
                            File::rm($dirTmp);
                        }

                    }

                }

            } elseif ($document instanceof Document\Printcontainer and ($document->getAction() == 'tree')) {

                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                if (is_array($selectPage)) {
                    $dbpage->deletePageByDocumentId($document->getId());

                } else {
                    $dbcatalogs = new Catalogs();
                    $selectCat = $dbcatalogs->getCatalog($document->getId());

                    if (is_array($selectCat)) {
                        // Delete in DB
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

        if (($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and ($document->getModule() == Plugin::PLUGIN_NAME)
        ) {

            if ($document instanceof Document\Printpage) {

                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                if (is_array($selectPage)) {
                    $pageParentId = $dbpage->getPage($document->getParentId());

                    // Parent is a chapter
                    if (is_array($pageParentId) and $pageParentId['document_type'] == "chapter") {
                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParent()->getParentId();
                    } elseif (!$pageParentId) {
                        $dbcatalog = new Catalogs();
                        $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                        // Parent is a catalog
                        if ($resultCatalog) {
                            $selectPage['document_parent_id'] = $document->getParentId();
                            $selectPage['document_root_id'] = $document->getParentId();
                        }

                    } else {
                        $selectPage['document_parent_id'] = 0;
                        $selectPage['document_root_id'] = 0;
                    }

                    $selectPage['page_key'] = $document->getKey();
                    $selectPage['locked'] = $document->getLocked();

                    // Update
                    $where = $dbpage->getAdapter()->quoteInto('document_id = ?', $document->getId());
                    $dbpage->update($selectPage, $where);

                }

            } elseif ($document instanceof Document\Printcontainer) {

                $dbpage = new Pages();
                $selectPage = $dbpage->getPage($document->getId());

                if (is_array($selectPage) and $selectPage['document_type'] == "chapter") {

                    $dbcatalog = new Catalogs();
                    $resultCatalog = $dbcatalog->getCatalog($document->getParentId());

                    // Parent is a catalog
                    if ($resultCatalog) {
                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParentId();
                    } else {
                        $selectPage['document_parent_id'] = 0;
                        $selectPage['document_root_id'] = 0;
                    }

                    $selectPage['page_key'] = $document->getKey();
                    $selectPage['locked'] = $document->getLocked();

                    // Update
                    $where = $dbpage->getAdapter()->quoteInto('document_id = ?', $document->getId());
                    $dbpage->update($selectPage, $where);

                    // Update childrens
                    foreach ($document->getChilds() as $child) {
                        if ($child instanceof Document) {
                            $child->save();
                        }
                    }

                }

            }

        }

        return true;
    }

}
