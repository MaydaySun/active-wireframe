<?php
/*
 * Active Publishing
 *
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
namespace ActiveWireframe\Db;

use Pimcore\Db;
use Pimcore\Model\Document;

\Zend_Db_Table::setDefaultAdapter(Db::get()->getResource());

/**
 * Class Pages
 * @package ActiveWireframe\Db
 */
class Pages extends \Zend_Db_Table
{
    protected $_name = "_active_wireframe_pages";

    /**
     * @param Document $document
     * @param $catalogId
     * @param array $conf
     * @return bool|mixed
     */
    public function insertPage(Document $document, $catalogId, $conf = [])
    {
        $products = array_key_exists('products', $conf)
            ? \Zend_Json_Encoder::encode($conf['products'])
            : \Zend_Json_Encoder::encode([]);

        $grid = array_key_exists('grid', $conf)
            ? $conf['grid']
            : ['row' => null, 'col' => null];

        $data = [
            'document_id' => $document->getId(),
            'document_parent_id' => $document->getParentId(),
            'document_root_id' => $catalogId,
            'document_type' => "page",
            'page_key' => $document->getKey(),
            'products' => $products,
            'locked' => 0,
            'grid_row' => $grid['row'],
            'grid_col' => $grid['col']
        ];

        return $this->insert($data);
    }

    /**
     * @param Document $chapter
     * @return mixed
     */
    public function insertChapter(Document $chapter)
    {
        $data = [
            "document_id" => $chapter->getId(),
            "document_parent_id" => $chapter->getParentId(),
            "document_root_id" => $chapter->getParentId(),
            "document_type" => "chapter",
            "page_key" => $chapter->getKey(),
            "locked" => 0
        ];

        return $this->insert($data);
    }

    /**
     * @param $documentId
     * @return int
     */
    public function deletePageByDocumentId($documentId)
    {
        $wherePage = $this->getAdapter()->quoteInto('document_id = ?', $documentId);
        return $this->delete($wherePage);
    }

    public function getCatalogByDocumentId($documentId)
    {
        $pinfo = $this->getPageByDocumentId($documentId);
        if ($pinfo and is_array($pinfo)) {

            $dbCatalog = new Catalogs();
            $cinfo = $dbCatalog->getCatalogByDocumentId(intval($pinfo['document_root_id']));

            if ($cinfo and is_array($cinfo)) {
                return $cinfo;
            }

        }

        return false;
    }

    /**
     * @param $documentId
     * @return array|bool
     */
    public function getPageByDocumentId($documentId)
    {
        $select = $this->fetchRow($this->select()->where("document_id = ?", $documentId));
        if ($select) {
            return $select->toArray();
        }

        return false;
    }

}
