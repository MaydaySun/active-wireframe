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
use ActiveWireframe\Pimcore\Web2Print\Processor;
use Pimcore\Model\Document\Printpage;

/**
 * Class ActiveWireframe_PrintpageController
 */
class ActiveWireframe_PrintpageController extends \Pimcore\Controller\Action\Admin\Printpage
{
    /**
     * @throws Exception
     */
    public function startPdfGenerationAction()
    {
        $document = Printpage::getById(intval($this->getParam("id")));
        if (empty($document)) {
            throw new \Exception("Document with id " . $this->getParam("id") . " not found.");
        }
        $this->generatePdf($document->getId(), $this->getAllParams());
        $this->saveProcessingOptions($document->getId(), $this->getAllParams());
        $this->_helper->json(["success" => true]);
    }

    /**
     * @param $config
     */
    public function generatePdf($documentId, $config)
    {
        Processor::getInstance()->preparePdfGeneration($documentId, $config);
    }

    /**
     * @param $documentId
     * @param $options
     */
    private function saveProcessingOptions($documentId, $options)
    {
        $file = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR .
            "web2print-processingoptions-" . $documentId . "_" . $this->getUser()->getId() . ".psf";
        file_put_contents($file, \Pimcore\Tool\Serialize::serialize($options));
    }
}