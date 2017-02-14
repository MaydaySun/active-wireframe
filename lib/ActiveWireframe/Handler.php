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

use ActivePublishing\Service\File;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Elements;
use ActiveWireframe\Db\Pages;
use Pimcore\Logger;
use Pimcore\Model\Document;

/**
 * Class Handler
 * @package ActiveWireframe
 */
class Handler
{
    /**
     * @var Handler
     */
    protected static $_instance;

    /**
     * @return Handler
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * @param \Zend_EventManager_Event $e
     */
    public function postAdd(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();

        // Document faisant partie du module ActiveWireframe
        if ($document->getModule() == Plugin::PLUGIN_NAME) {

            if (($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)) {

                // Intance des tables active_wireframe_pages et active_catalogs
                $dbPages = Pages::getInstance();
                $dbCatalogs = Catalogs::getInstance();

                // Recherche si le document provient d'une copie
                $redirectUrl = isset($_SERVER['REDIRECT_URL']) ? explode('/', $_SERVER['REDIRECT_URL']) : false;
                array_shift($redirectUrl);
                $copy = (($redirectUrl[1] == 'document') and ($redirectUrl[2] == 'copy'));

                if ($copy and isset($_GET['sourceId'])) {

                    $this->addByCopy($_GET['sourceId'], $document, $dbPages, $dbCatalogs);

                } elseif ($document instanceof Document\Printpage and $document->getAction() == 'pages') {

                    $this->addManuallyPage($document, $dbPages, $dbCatalogs);

                } elseif ($document instanceof Document\Printcontainer and $document->getAction() == 'tree') {

                    $this->addManuallyChapter($document, $dbPages, $dbCatalogs);

                }

            }

        }
    }

    /**
     * Copie un document active-wireframe
     *
     * @param $sourceId
     * @param Document $document
     * @param Pages $dbPages
     * @param Catalogs $dbCatalogs
     */
    private function addByCopy($sourceId, Document $document, Pages $dbPages, Catalogs $dbCatalogs)
    {
        if ($catalog = $dbCatalogs->getCatalogByDocumentId($sourceId)) {

            unset($catalog['id']);
            $catalog['document_id'] = $document->getId();

            try {

                $dbCatalogs->insert($catalog);

            } catch (\Exception $ex) {

                $document->delete();
                Logger::err($ex->getMessage());

            };

        } else { // Copie d'une page ou d'un chapitre

            $pageCatalogue = $dbPages->getPageByDocumentId($sourceId);

            if ($pageCatalogue) {

                $pageInChapter = (($pageCatalogue['document_parent_id'] != $pageCatalogue['document_root_id'])
                    and ($pageCatalogue['document_type'] == 'page'));

                unset($pageCatalogue['id']);
                $pageCatalogue['document_id'] = $document->getId();
                $pageCatalogue['document_parent_id'] = $document->getParentId();
                $pageCatalogue['document_root_id'] = $document->getParentId();

                // Page
                if ($pageCatalogue['document_type'] == 'page') {

                    if ($pageInChapter) {
                        $pageCatalogue['document_root_id'] = $document->getParent()->getParentId();
                    }

                    // Récupère les positions xy des éléments
                    $dbElements = Elements::getInstance();
                    $elements = $dbElements->getElementsByDocumentId($sourceId);

                    if (!empty($elements)) {

                        foreach ($elements as $element) {

                            unset($element['id']);
                            $element['document_id'] = $document->getId();
                            $element['document_parent_id'] = $document->getParentId();
                            $element['document_root_id'] = $document->getParent()->getParentId();

                            try {
                                $dbElements->insert($element);
                            } catch (\Exception $ex) {
                                Logger::err($ex);
                            }

                        }

                    }

                }

                try {

                    $dbPages->insert($pageCatalogue);

                    // Copie les données w2p-element
                    if (file_exists(Plugin::PLUGIN_WEBSITE_PATH . DIRECTORY_SEPARATOR . $sourceId)) {
                        File::cp(Plugin::PLUGIN_WEBSITE_PATH . DIRECTORY_SEPARATOR . $sourceId,
                            Plugin::PLUGIN_WEBSITE_PATH . DIRECTORY_SEPARATOR . $document->getId());
                    }

                } catch (\Exception $ex) {

                    $document->delete();
                    Logger::err($ex->getMessage());

                };

            }

        }
    }

    /**
     * Initialise un document active-wireframe "page" ajouté manuellement
     *
     * @param Document $document
     * @param Pages $dbPages
     * @param Catalogs $dbCatalogs
     */
    private function addManuallyPage(Document $document, Pages $dbPages, Catalogs $dbCatalogs)
    {
        $pageParent = $dbPages->getPageByDocumentId($document->getParentId());

        $selectPage = [];
        $selectPage['document_id'] = $document->getId();
        $selectPage['document_type'] = "page";

        if ($pageParent and $pageParent['document_type'] == "chapter") {

            $selectPage['document_parent_id'] = $document->getParentId();
            $selectPage['document_root_id'] = $document->getParent()->getParentId();

        } else {

            $resultCatalog = $dbCatalogs->getCatalogByDocumentId($document->getParentId());

            if ($resultCatalog) {

                $selectPage['document_parent_id'] = $document->getParentId();
                $selectPage['document_root_id'] = $document->getParentId();

            }
        }

        $selectPage['page_key'] = $document->getKey();
        $selectPage['locked'] = $document->getLocked();
        $dbPages->insert($selectPage);
    }

    /**
     * Initialise un document active-wireframe "chapter" ajouté manuellement
     *
     * @param Document $document
     * @param Pages $dbPages
     * @param Catalogs $dbCatalogs
     */
    private function addManuallyChapter(Document $document, Pages $dbPages, Catalogs $dbCatalogs)
    {
        $selectPage = [];
        $selectPage['document_id'] = $document->getId();
        $selectPage['document_type'] = "chapter";
        $resultCatalog = $dbCatalogs->getCatalogByDocumentId($document->getParentId());

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

        $dbPages->insert($selectPage);
    }

    /**
     * @param \Zend_EventManager_Event $e
     */
    public function postUpdate(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();

        // Document faisant partie du module ActiveWireframe
        if ($document->getModule() == Plugin::PLUGIN_NAME) {

            if ($document instanceof Document\Printcontainer or $document instanceof Document\Printpage) {

                $dbCatalogs = Catalogs::getInstance();

                if ($document instanceof Document\Printpage) {

                    $dbpage = new Pages();
                    $pinfo = $dbpage->getPageByDocumentId($document->getId());

                    if ($pinfo) {

                        $parentInfo = $dbpage->getPageByDocumentId($document->getParentId());

                        // Le parent est un chapitre
                        if ($parentInfo and $parentInfo['document_type'] == "chapter") {

                            $pinfo['document_parent_id'] = $document->getParentId();
                            $pinfo['document_root_id'] = $document->getParent()->getParentId();

                        } else {

                            $cinfo = $dbCatalogs->getCatalogByDocumentId($document->getParentId());

                            // Le parent est un catalogue
                            if ($cinfo) {

                                $pinfo['document_parent_id'] = $document->getParentId();
                                $pinfo['document_root_id'] = $document->getParentId();

                            }

                        }

                        $pinfo['page_key'] = $document->getKey();
                        $pinfo['locked'] = $document->getLocked();

                        $where = $dbpage->getAdapter()->quoteInto('id = ?', $pinfo['id']);
                        $dbpage->update($pinfo, $where);
                    }

                } elseif ($document instanceof Document\Printcontainer) {

                    $dbpage = new Pages();
                    $pinfo = $dbpage->getPageByDocumentId($document->getId());

                    if (is_array($pinfo) and $pinfo['document_type'] == "chapter") {
                        $cinfo = $dbCatalogs->getCatalogByDocumentId($document->getParentId());

                        // Le parent est un catalogue
                        if ($cinfo) {
                            $pinfo['document_parent_id'] = $document->getParentId();
                            $pinfo['document_root_id'] = $document->getParentId();
                        } else {
                            $pinfo['document_parent_id'] = 0;
                            $pinfo['document_root_id'] = 0;
                        }

                        $pinfo['page_key'] = $document->getKey();
                        $pinfo['locked'] = $document->getLocked();

                        $where = $dbpage->getAdapter()->quoteInto('id = ?', $pinfo['id']);
                        $dbpage->update($pinfo, $where);

                        // Mise à jour des enfants
                        foreach ($document->getChilds() as $child) {

                            if ($child instanceof Document) {
                                $child->save();
                            }

                        }

                    }

                }

            }

        }
    }

    /**
     * @param \Zend_EventManager_Event $e
     */
    public function postDelete(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();

        // Document ne faisant pas partie du module ActiveWireframe
        if ($document->getModule() == Plugin::PLUGIN_NAME) {

            if ($document instanceof Document\Printcontainer or $document instanceof Document\Printpage) {

                if ($document instanceof Document\Printpage and $document->getAction() == 'pages') {

                    $dbpage = new Pages();
                    $selectPage = $dbpage->getPageByDocumentId($document->getId());

                    if (is_array($selectPage)) {

                        $count = $dbpage->deletePageByDocumentId($document->getId());
                        if ($count > 0) {

                            $dbElements = Elements::getInstance();
                            $dbElements->deleteByKey('document_id', $document->getId());

                            // Supprime le dossier
                            $dirTmp = Plugin::PLUGIN_WEBSITE_PATH . DIRECTORY_SEPARATOR . $document->getId();
                            if (file_exists($dirTmp)) {
                                File::rm($dirTmp);
                            }

                        }

                    }

                } elseif ($document instanceof Document\Printcontainer and ($document->getAction() == 'tree')) {

                    $dbpage = new Pages();
                    $selectPage = $dbpage->getPageByDocumentId($document->getId());

                    if ($selectPage) {
                        $dbpage->deletePageByDocumentId($document->getId());

                    } else {

                        $dbCatalogs = Catalogs::getInstance();
                        $selectCat = $dbCatalogs->getCatalogByDocumentId($document->getId());

                        if (is_array($selectCat)) {

                            $where = $dbCatalogs->getAdapter()->quoteInto('document_id = ?', $document->getId());
                            $dbCatalogs->delete($where);

                        }

                    }

                }

            }

        }

    }

}