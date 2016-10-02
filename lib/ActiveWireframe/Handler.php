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
use ActivePublishing\Service\Tool;
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
     * @static
     * @var Handler
     */
    protected static $_instance;

    /**
     * Retrieve singleton instance
     *
     * @static
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
     * @return bool
     */
    public function postAdd(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();
        if (($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and ($document->getModule() == Plugin::PLUGIN_NAME)
        ) {

            $dbCatalogs = Catalogs::getInstance();
            $dbPages = Pages::getInstance();

            $redirectUrl = isset($_SERVER['REDIRECT_URL']) ? explode('/', $_SERVER['REDIRECT_URL']) : false;
            array_shift($redirectUrl);
            $copy = (($redirectUrl[1] == 'document') and ($redirectUrl[2] == 'copy'));

            // Adding document by copy
            if ($copy and isset($_GET['sourceId'])) {
                $sourceId = $_GET['sourceId'];

                // copy catalog
                if ($catalog = $dbCatalogs->getCatalogByDocumentId($sourceId)) {

                    unset($catalog['id']);
                    $catalog['document_id'] = $document->getId();

                    try {
                        $dbCatalogs->insert($catalog);

                    } catch (\Exception $ex) {
                        $document->delete();
                        return Tool::sendJson(['success' => false]);
                    };

                } else { // Copy Chapter or page

                    $pageCatalogue = $dbPages->getPageByDocumentId($sourceId);
                    if ($pageCatalogue) {

                        $pageInChapter = (($pageCatalogue['document_parent_id'] == $pageCatalogue['document_root_id'])
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

                            // Retrieve elements
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

                            // Add cloned data
                            $dbPages->insert($pageCatalogue);

                            // Add data element-w2p and thumbnails
                            if (file_exists(Plugin::PLUGIN_PATH_DATA . DIRECTORY_SEPARATOR . $sourceId)) {
                                File::cp(Plugin::PLUGIN_PATH_DATA . DIRECTORY_SEPARATOR . $sourceId,
                                    Plugin::PLUGIN_PATH_DATA . DIRECTORY_SEPARATOR . $document->getId());
                            }

                        } catch (\Exception $ex) {
                            $document->delete();
                            return Tool::sendJson(['success' => false]);
                        };
                    }

                }

            } elseif ($document instanceof Document\Printpage and $document->getAction() == 'pages') {
                // Document added manually

                $pageParent = $dbPages->getPageByDocumentId($document->getParentId());

                $selectPage = [];
                $selectPage['document_id'] = $document->getId();
                $selectPage['document_type'] = "page";

                // Parent is a chapter
                if ($pageParent and $pageParent['document_type'] == "chapter") {

                    $selectPage['document_parent_id'] = $document->getParentId();
                    $selectPage['document_root_id'] = $document->getParent()->getParentId();

                } else {
                    $resultCatalog = $dbCatalogs->getCatalogByDocumentId($document->getParentId());

                    // Parent is a catalog
                    if ($resultCatalog) {
                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParentId();
                    }

                }

                $selectPage['page_key'] = $document->getKey();
                $selectPage['locked'] = $document->getLocked();
                $dbPages->insert($selectPage);

            } elseif ($document instanceof Document\Printcontainer and $document->getAction() == 'tree') {

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

        }

        return true;
    }

    /**
     * @param \Zend_EventManager_Event $e
     * @return bool
     */
    public function postUpdate(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();

        if (($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and ($document->getModule() == Plugin::PLUGIN_NAME)
        ) {
            $dbCatalogs = Catalogs::getInstance();

            if ($document instanceof Document\Printpage) {

                $dbpage = new Pages();
                $pinfo = $dbpage->getPageByDocumentId($document->getId());
                if ($pinfo) {

                    $parentInfo = $dbpage->getPageByDocumentId($document->getParentId());

                    // Parent is a chapter
                    if ($parentInfo and $parentInfo['document_type'] == "chapter") {

                        $pinfo['document_parent_id'] = $document->getParentId();
                        $pinfo['document_root_id'] = $document->getParent()->getParentId();
                        $cinfo = $dbCatalogs->getCatalogByDocumentId($document->getParent()->getParentId());

                    } else {
                        $cinfo = $dbCatalogs->getCatalogByDocumentId($document->getParentId());

                        // Parent is a catalog
                        if ($cinfo) {
                            $pinfo['document_parent_id'] = $document->getParentId();
                            $pinfo['document_root_id'] = $document->getParentId();
                        }

                    }

                    $pinfo['page_key'] = $document->getKey();
                    $pinfo['locked'] = $document->getLocked();

                    // Update
                    $where = $dbpage->getAdapter()->quoteInto('id = ?', $pinfo['id']);
                    $dbpage->update($pinfo, $where);

                    // Created Thumbnail
                    $widthPX = Helpers::convertMmToPx($cinfo['format_width']);
                    Helpers::getPageThumbnailForTree($document, $widthPX);
                }

            } elseif ($document instanceof Document\Printcontainer) {

                $dbpage = new Pages();
                $pinfo = $dbpage->getPageByDocumentId($document->getId());

                if (is_array($pinfo) and $pinfo['document_type'] == "chapter") {
                    $cinfo = $dbCatalogs->getCatalogByDocumentId($document->getParentId());

                    // Parent is a catalog
                    if ($cinfo) {
                        $pinfo['document_parent_id'] = $document->getParentId();
                        $pinfo['document_root_id'] = $document->getParentId();
                    } else {
                        $pinfo['document_parent_id'] = 0;
                        $pinfo['document_root_id'] = 0;
                    }

                    $pinfo['page_key'] = $document->getKey();
                    $pinfo['locked'] = $document->getLocked();

                    // Update
                    $where = $dbpage->getAdapter()->quoteInto('id = ?', $pinfo['id']);
                    $dbpage->update($pinfo, $where);

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

    /**
     * @param \Zend_EventManager_Event $e
     */
    public function postDelete(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();

        if (Plugin::composerExists()
            and ($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and ($document->getModule() == Plugin::PLUGIN_NAME)
        ) {

            if ($document instanceof Document\Printpage and $document->getAction() == 'pages') {

                $dbpage = new Pages();
                $selectPage = $dbpage->getPageByDocumentId($document->getId());
                if (is_array($selectPage)) {

                    // Delete in DB
                    $count = $dbpage->deletePageByDocumentId($document->getId());
                    if ($count > 0) {

                        $dbElements = Elements::getInstance();
                        $dbElements->deleteByKey('document_id', $document->getId());

                        // Delete directory
                        $dirTmp = Plugin::PLUGIN_PATH_DATA  . DIRECTORY_SEPARATOR . $document->getId();
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

                        // Delete in DB
                        $where = $dbCatalogs->getAdapter()->quoteInto('document_id = ?', $document->getId());
                        $dbCatalogs->delete($where);
                    }

                }

            }

        }

    }

}