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
use Pimcore\Model\Document;

\Zend_Db_Table::setDefaultAdapter(Db::get()->getResource());

/**
 * Class Pages
 * @package ActiveWireframe\Db
 */
class Pages extends \Zend_Db_Table
{
    /**
     * @static
     * @var Pages
     */
    protected static $_instance;
    
    /**
     * @var string
     */
    protected $_name = "_active_wireframe_pages";

    /**
     * Retrieve singleton instance
     *
     * @static
     * @return Pages
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

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

    /**
     * @param $documentId
     * @return array|bool
     */
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
