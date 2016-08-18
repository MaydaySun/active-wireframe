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
use Pimcore\Model\Document\Printpage;

\Zend_Db_Table::setDefaultAdapter(Db::get()->getResource());

/**
 * Class Pages
 * @package ActiveWireframe\Db
 */
class Pages extends \Zend_Db_Table
{
    /**
     * @var string
     */
    protected $_name = "_active_wireframe_pages";

    /**
     * @param $document
     * @param $key
     * @param $parent_id
     * @param $rootId
     * @param array $configs
     * @return bool|mixed
     */
    public function insertPage($document, $key, $parent_id, $rootId, $configs = array())
    {
        if ($document instanceof Printpage) {

            $products = array_key_exists('products', $configs)
                ? \Zend_Json_Encoder::encode($configs['products'])
                : \Zend_Json_Encoder::encode(array());

            $grid = array_key_exists('grid', $configs)
                ? $configs['grid']
                : array(
                    'row' => null,
                    'col' => null
                );

            // Enregistrement de la page en BD
            $arrayPage = array(
                'document_id' => $document->getId(),
                'document_parent_id' => $parent_id,
                'document_root_id' => $rootId,
                'document_type' => "page",
                'page_key' => $key,
                'products' => $products,
                'locked' => 0,
                'grid_row' => $grid['row'],
                'grid_col' => $grid['col']
            );

            // Enregistrement du catalogs en BD
            return $this->insert($arrayPage);
        }

        return false;
    }

    /**
     * @param $chapterId
     * @param $key
     * @param $parent_id
     * @param null $object_id
     * @return mixed
     */
    public function insertChapter($chapterId, $key, $parent_id, $object_id = null)
    {
        // ID du chapitre
        $id_array = array();
        if ($object_id != null) {
            $id_array[] = $object_id;
        }

        // Insertion du chapitre
        $arrayCatalog = array(
            "document_id" => $chapterId,
            "document_parent_id" => $parent_id,
            "document_root_id" => $parent_id,
            "document_type" => "chapter",
            "page_key" => $key,
            "products" => \Zend_Json_Encoder::encode($id_array),
            "locked" => 0
        );
        return $this->insert($arrayCatalog);
    }

    /**
     * @param $documentId
     * @return array|bool
     */
    public function getPage($documentId)
    {
        $select = $this->fetchRow($this->select()->where("document_id = ?", $documentId));
        return ($select != null) ? $select->toArray() : false;
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

}
