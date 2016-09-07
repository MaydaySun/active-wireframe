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

namespace ActiveWireframe\Pimcore\Web2Print;

use ActiveWireframe\Pimcore\Web2Print\Processor\WkHtmlToPdf;
use Pimcore\Config;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Document;
use Pimcore\Tool;
use Pimcore\Web2Print\Processor\PdfReactor8;

//use Pimcore\Web2Print\Processor\WkHtmlToPdf;

abstract class Processor
{
    /**
     * @param $documentId
     * @param $config
     * @throws \Exception
     */
    public function preparePdfGeneration($documentId, $config)
    {
        $document = $this->getPrintDocument($documentId);
        if (Model\Tool\TmpStore::get($document->getLockKey())) {
            throw new \Exception("Process with given document alredy running.");
        }
        Model\Tool\TmpStore::add($document->getLockKey(), true);

        $jobConfig = new \stdClass();
        $jobConfig->documentId = $documentId;
        $jobConfig->config = $config;
        $this->saveJobConfigObjectFile($jobConfig);

        $this->updateStatus($documentId, 0, "prepare_pdf_generation");

        $cmd = Tool\Console::getPhpCli() . " " . realpath(PIMCORE_PATH . DIRECTORY_SEPARATOR . "cli" . DIRECTORY_SEPARATOR . "console.php") . " web2printActivePublishing:pdf-creation -p " . $jobConfig->documentId;

        Logger::info($cmd);

        if (!$config['disableBackgroundExecution']) {
            Tool\Console::execInBackground($cmd, PIMCORE_LOG_DIRECTORY . DIRECTORY_SEPARATOR . "web2print-output.log");
        } else {

            $processor = Processor::getInstance();

            $config = Config::getWeb2PrintConfig();
            if ($config->generalTool == "wkhtmltopdf") {
                $processor->setOptionsCatalogs($documentId);
            }

            $processor->startPdfGeneration($jobConfig->documentId);
        }

    }

    /**
     * @param $documentId
     * @return Document\Printpage
     * @throws \Exception
     */
    protected function getPrintDocument($documentId)
    {
        $document = Document\Printpage::getById($documentId);
        if (empty($document)) {
            throw new \Exception("PrintDocument with " . $documentId . " not found.");
        }

        return $document;
    }

    /**
     * @param $jobConfig
     * @return bool
     */
    protected function saveJobConfigObjectFile($jobConfig)
    {
        file_put_contents($this->getJobConfigFile($jobConfig->documentId), json_encode($jobConfig));

        return true;
    }

    /**
     * @param $processId
     * @return string
     */
    public static function getJobConfigFile($processId)
    {
        return PIMCORE_SYSTEM_TEMP_DIRECTORY . DIRECTORY_SEPARATOR . "pdf-creation-job-" . $processId . ".json";
    }

    /**
     * @param $documentId
     * @param $statusUpdate
     */
    protected function updateStatus($documentId, $status, $statusUpdate)
    {
        $jobConfig = $this->loadJobConfigObject($documentId);
        $jobConfig->status = $status;
        $jobConfig->statusUpdate = $statusUpdate;
        $this->saveJobConfigObjectFile($jobConfig);
    }

    /**
     * @param $documentId
     * @return \stdClass
     */
    protected function loadJobConfigObject($documentId)
    {
        $jobConfig = json_decode(file_get_contents($this->getJobConfigFile($documentId)));

        return $jobConfig;
    }

    /**
     * @return PdfReactor8|WkHtmlToPdf
     * @throws \Exception
     */
    public static function getInstance()
    {
        $config = Config::getWeb2PrintConfig();

        if ($config->generalTool == "pdfreactor") {
            return new PdfReactor8();
        } elseif ($config->generalTool == "wkhtmltopdf") {
            return new WkHtmlToPdf();
        } else {
            throw new \Exception("Invalid Configuation - " . $config->generalTool);
        }
    }

    /**
     * @param $documentId
     * @throws \Exception
     */
    public function startPdfGeneration($documentId)
    {
        $jobConfigFile = $this->loadJobConfigObject($documentId);

        $document = $this->getPrintDocument($documentId);

        // check if there is already a generating process running, wait if so ...
        Model\Tool\Lock::acquire($document->getLockKey(), 0);

        try {
            $pdf = $this->buildPdf($document, $jobConfigFile->config);
            file_put_contents($document->getPdfFileName(), $pdf);

            \Pimcore::getEventManager()->trigger("document.print.postPdfGeneration", $document, [
                "filename" => $document->getPdfFileName(),
                "pdf" => $pdf
            ]);

            $creationDate = \Zend_Date::now();
            $document->setLastGenerated(($creationDate->get() + 1));
            $document->save();
        } catch (\Exception $e) {
            $document->save();
            Logger::err($e);
        }

        Model\Tool\Lock::release($document->getLockKey());
        Model\Tool\TmpStore::delete($document->getLockKey());

        @unlink($this->getJobConfigFile($documentId));
    }

    /**
     * @param Document\PrintAbstract $document
     * @param $config
     * @return mixed
     */
    abstract protected function buildPdf(Document\PrintAbstract $document, $config);

    /**
     * @return array
     */
    abstract public function getProcessingOptions();

    /**
     * @param $documentId
     * @return array
     */
    public function getStatusUpdate($documentId)
    {
        $jobConfig = $this->loadJobConfigObject($documentId);
        if ($jobConfig) {
            return [
                "status" => $jobConfig->status,
                "statusUpdate" => $jobConfig->statusUpdate
            ];
        }
    }

    /**
     * @param $documentId
     * @throws \Exception
     */
    public function cancelGeneration($documentId)
    {
        $document = Document\Printpage::getById($documentId);
        if (empty($document)) {
            throw new \Exception("Document with id " . $documentId . " not found.");
        }
        Model\Tool\Lock::release($document->getLockKey());
        Model\Tool\TmpStore::delete($document->getLockKey());
    }
}
