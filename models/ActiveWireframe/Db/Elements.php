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

\Zend_Db_Table::setDefaultAdapter(Db::get()->getResource());

/**
 * Class Elements
 * @package ActiveWireframe\Db
 */
class Elements extends \Zend_Db_Table
{
    /**
     * @static
     * @var Elements
     */
    protected static $_instance;

    /**
     * @var string
     */
    protected $_name = "_active_wireframe_elements";

    /**
     * Retrieve singleton instance
     *
     * @static
     * @return Elements
     */
    public static function getInstance()
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

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
