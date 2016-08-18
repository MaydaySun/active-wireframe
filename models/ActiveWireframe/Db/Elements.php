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
 * @copyright   Copyright (c) 2016 Active Publishing (http://www.active-publishing.fr)
 * @license     http://creativecommons.org/licenses/by-nc-nd/4.0/
 */
namespace ActiveWireframe\Db;

use Pimcore\Db;

\Zend_Db_Table::setDefaultAdapter(Db::get()->getResource());

/**
 * Class Elements
 * @package ActiveWireframe\Db
 */
class Elements extends \Zend_Db_Table
{
    /**
     * @var string
     */
    protected $_name = "_active_wireframe_elements";

    /**
     * @param $documentId
     * @return array
     */
    public function getElementsByDocumentId($documentId)
    {
        $select = $this->fetchAll($this->select()->where("document_id = ?", $documentId));
        return $select->toArray();
    }

    /**
     * @param $key
     * @param $value
     * @return int
     */
    public function deleteByKey($key, $value)
    {
        $where = $this->getAdapter()->quoteInto($key . ' = ?', $value);
        return $this->delete($where);
    }

}
