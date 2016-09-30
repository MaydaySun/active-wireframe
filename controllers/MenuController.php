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

use ActivePublishing\Service\Tool;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Helpers;
use ActiveWireframe\Plugin;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Website\Controller\Action;

/**
 * Class ActiveWireframe_MenuController
 */
class ActiveWireframe_MenuController extends Action
{
    /**
     * Init
     */
    public function init()
    {
        parent::init();
        $this->disableLayout();
        $this->disableViewAutoRender();
        $this->disableBrowserCache();

        if (!Plugin::composerExists()
            or !Plugin::isInstalled()
        ) {
            exit();
        }
    }

    /**
     * Generate a new pagination
     *
     * @return int
     */
    public function generatePaginationAction()
    {
        $document = Printcontainer::getById($this->getParam('documentId'));
        if ($document instanceof Printcontainer) {

            $index = $this->hasParam('index') ? $this->getParam('index') : 1;
            $noRename = [];

            // Document is a chapter
            if (!Catalogs::getInstance()->getCatalogByDocumentId($document->getId())) {

                foreach ($document->getParent()->getChilds() as $child) {
                    if (($child instanceof Printpage or $child instanceof Printcontainer)
                        and ($child->getIndex() < $document->getIndex())
                    ) {
                        $noRename[] = $child->getKey();
                    }
                }

                $document = $document->getParent();
            }

            // New pagination
            if ($document->hasChilds()) {
                $indexTmp = time();
                Helpers::generateNewPagination($document, $indexTmp, $noRename);
                Helpers::generateNewPagination($document, $index, $noRename);
            }

        }

        return Tool::sendJson(['success' => true]);
    }

    /**
     * Refresh thumbnails
     *
     * @return int
     */
    public function reloadCatalogAction()
    {
        $document = Printcontainer::getById($this->getParam('documentId'));
        if ($document instanceof Printcontainer and $document->getAction() == "tree" and $document->hasChilds()) {

            $dbCatalog = Catalogs::getInstance();
            $catalog = $dbCatalog->getCatalogByDocumentId($document->getId());

            if (!$catalog) {
                $catalog = $dbCatalog->getCatalogByDocumentId($document->getParentId());
            }

            Helpers::reloadThumbnailForTree($document, $catalog['format_width']);
        }

        return Tool::sendJson(['success' => true]);
    }

}