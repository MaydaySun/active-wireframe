<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) 2009-2016 pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */
use ActiveWireframe\Pimcore\Web2Print\Processor;
use Pimcore\Model\Document\Printpage;

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
        $processor = Processor::getInstance();
        $processor->setOptionsCatalogs($documentId);
        $processor->preparePdfGeneration($documentId, $config);
    }

    /**
     * @param $documentId
     * @param $options
     */
    private function saveProcessingOptions($documentId, $options)
    {
        file_put_contents(PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "web2print-processingoptions-" . $documentId . "_" . $this->getUser()->getId() . ".psf", \Pimcore\Tool\Serialize::serialize($options));
    }

}
