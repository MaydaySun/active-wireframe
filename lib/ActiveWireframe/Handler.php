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

use ActivePublishing\Services\File;
use ActivePublishing\Services\Response;
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
     * @param \Zend_EventManager_Event $e
     * @return bool
     */
    public function postAdd(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();
        if (($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and ($document->getModule() == Plugin::PLUGIN_NAME)
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
                $catalog = $dbcatalogs->getCatalogByDocumentId($sourceId);

                // copy catalog
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
                    $pageCatalogue = $dbpages->getPageByDocumentId($sourceId);
                    if ($pageCatalogue) {

                        $pageInChapter = true;
                        if (($pageCatalogue['document_parent_id'] == $pageCatalogue['document_root_id'])
                            and ($pageCatalogue['document_type'] == 'page')
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
                // Document added manually

                $dbpage = new Pages();
                $pageParent = $dbpage->getPageByDocumentId($document->getParentId());

                $selectPage = [];
                $selectPage['document_id'] = $document->getId();
                $selectPage['document_type'] = "page";

                // Parent is a chapter
                if ($pageParent and $pageParent['document_type'] == "chapter") {

                    $selectPage['document_parent_id'] = $document->getParentId();
                    $selectPage['document_root_id'] = $document->getParent()->getParentId();

                } else {

                    $dbcatalog = new Catalogs();
                    $resultCatalog = $dbcatalog->getCatalogByDocumentId($document->getParentId());

                    // Parent is a catalog
                    if ($resultCatalog) {
                        $selectPage['document_parent_id'] = $document->getParentId();
                        $selectPage['document_root_id'] = $document->getParentId();
                    }

                }

                $selectPage['page_key'] = $document->getKey();
                $selectPage['locked'] = $document->getLocked();
                $dbpage->insert($selectPage);

            } elseif ($document instanceof Document\Printcontainer and $document->getAction() == 'tree') {

                $selectPage = [];
                $selectPage['document_id'] = $document->getId();
                $selectPage['document_type'] = "chapter";

                $dbcatalog = new Catalogs();
                $resultCatalog = $dbcatalog->getCatalogByDocumentId($document->getParentId());

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
     * @param \Zend_EventManager_Event $e
     * @return bool
     */
    public function postUpdate(\Zend_EventManager_Event $e)
    {
        $document = $e->getTarget();

        if (($document instanceof Document\Printcontainer or $document instanceof Document\Printpage)
            and ($document->getModule() == Plugin::PLUGIN_NAME)
        ) {

            if ($document instanceof Document\Printpage) {

                $dbpage = new Pages();
                $pinfo = $dbpage->getPageByDocumentId($document->getId());
                if ($pinfo) {

                    $parentInfo = $dbpage->getPageByDocumentId($document->getParentId());
                    $dbcatalog = new Catalogs();

                    // Parent is a chapter
                    if ($parentInfo and $parentInfo['document_type'] == "chapter") {

                        $pinfo['document_parent_id'] = $document->getParentId();
                        $pinfo['document_root_id'] = $document->getParent()->getParentId();
                        $cinfo = $dbcatalog->getCatalogByDocumentId($document->getParent()->getParentId());

                    } else {
                        $cinfo = $dbcatalog->getCatalogByDocumentId($document->getParentId());

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
                    Helpers::getPageThumbnailForTree($document, $cinfo['format_width']);

                }

            } elseif ($document instanceof Document\Printcontainer) {

                $dbpage = new Pages();
                $pinfo = $dbpage->getPageByDocumentId($document->getId());

                if (is_array($pinfo) and $pinfo['document_type'] == "chapter") {

                    $dbcatalog = new Catalogs();
                    $cinfo = $dbcatalog->getCatalogByDocumentId($document->getParentId());

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
                $selectPage = $dbpage->getPageByDocumentId($document->getId());

                if ($selectPage) {
                    $dbpage->deletePageByDocumentId($document->getId());

                } else {
                    $dbcatalogs = new Catalogs();
                    $selectCat = $dbcatalogs->getCatalogByDocumentId($document->getId());

                    if (is_array($selectCat)) {
                        // Delete in DB
                        $where = $dbcatalogs->getAdapter()->quoteInto('document_id = ?', $document->getId());
                        $dbcatalogs->delete($where);
                    }

                }

            }

        }

    }

}