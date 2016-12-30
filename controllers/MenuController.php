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
use ActivePublishing\Tool;
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
    public function init()
    {
        parent::init();
        $this->disableLayout();
        $this->disableViewAutoRender();

        if (!Plugin::composerExists() or !Plugin::isInstalled()) {
            exit();
        }
    }

    public function generatePaginationAction()
    {
        $document = Printcontainer::getById($this->getParam('documentId'));
        if ($document instanceof Printcontainer) {

            $index = $this->hasParam('index') ? $this->getParam('index') : 1;
            $recursive = $this->hasParam('recursive') ? $this->getParam('recursive') : false;
            $noRename = [];

            // Document is a chapter
            if (!Catalogs::getInstance()->getCatalogByDocumentId($document->getId())) {
                if ($recursive === "true") { // recursive

                    foreach ($document->getParent()->getChilds() as $child) {
                        if (($child instanceof Printpage or $child instanceof Printcontainer)
                            and ($child->getIndex() < $document->getIndex())
                        ) {
                            $noRename[] = $child->getKey();
                        }
                    }

                    $document = $document->getParent();
                }
            }

            // New pagination
            if ($document->hasChilds()) {
                $indexTmp = time();
                Helpers::generateNewPagination($document, $indexTmp, $noRename);
                Helpers::generateNewPagination($document, $index, $noRename);
            }

        }

        Tool::sendJson(['success' => true]);
    }

    public function reloadCatalogAction()
    {
        $document = Printcontainer::getById($this->getParam('documentId'));
        if ($document instanceof Printcontainer
            and ($document->getAction() == "tree")
            and $document->hasChilds()
        ) {
            Helpers::reloadDocumentThumbnail($document);
        }

        Tool::sendJson(['success' => true]);
    }

}