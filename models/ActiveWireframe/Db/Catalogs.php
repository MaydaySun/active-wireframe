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

\Zend_Db_Table::setDefaultAdapter(Db::get()->getResource());

/**
 * Class Catalogs
 * @package ActiveWireframe\Db
 */
class Catalogs extends \Zend_Db_Table
{
    protected $_name = "_active_catalogs";

    /**
     * @param $documentId
     * @return array|bool
     */
    public function searchCatalogue($documentId)
    {
        $dbPages = new Pages();
        $page = $dbPages->getPage($documentId);

        if (is_array($page)) {
            return $this->getCatalog($page['document_root_id']);
        }

        return false;
    }

    /**
     * @param $document_id
     * @return array|bool
     */
    public function getCatalog($document_id)
    {
        $row = $this->fetchRow($this->select()->where("document_id = ?", $document_id));
        if ($row != null) {
            return $row->toArray();
        }
        return false;
    }

    /**
     * @param $documentId
     * @return int
     */
    public function deleteCatalogByDocumentId($documentId)
    {
        $wherePage = $this->getAdapter()->quoteInto('document_id = ?', $documentId);
        return $this->delete($wherePage);
    }

}
