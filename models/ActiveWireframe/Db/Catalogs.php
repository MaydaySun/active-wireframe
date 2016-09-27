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

namespace ActiveWireframe\Db;

use Pimcore\Db;
use Pimcore\Model\Document\Printcontainer;

\Zend_Db_Table::setDefaultAdapter(Db::get()->getResource());

/**
 * Class Catalogs
 * @package ActiveWireframe\Db
 */
class Catalogs extends \Zend_Db_Table
{
    /**
     * @static
     * @var Catalogs
     */
    protected static $_instance;

    /**
     * @var string
     */
    protected $_name = "_active_catalogs";

    /**
     * Retrieve singleton instance
     *
     * @return Catalogs
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * @param Printcontainer $document
     * @param array $data
     */
    public function insertCatalog(Printcontainer $document, array $data)
    {
        $catalog = $this->getCatalogByDocumentId($document->getId());

        // update
        if ($catalog) {
            $where = $this->getAdapter()->quoteInto("document_id = ?", $document->getId());
            $this->update($data, $where);

        } else {
            // insert
            $this->insert($data);
        }
    }

    /**
     * @param $document_id
     * @return array|bool
     */
    public function getCatalogByDocumentId($document_id)
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
