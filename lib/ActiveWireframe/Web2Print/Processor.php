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
namespace ActiveWireframe\Web2Print;

use ActiveWireframe\Web2Print\Processor\WkHtmlToPdf;
use Pimcore\Config;
use Pimcore\Web2Print\Processor\PdfReactor8;
use Pimcore\Tool;
use Pimcore\Model;
use Pimcore\Model\Document;
use Pimcore\Logger;

/**
 * Class Processor
 *
 * @package ActiveWireframe\Pimcore\Web2Print
 */
abstract class Processor
{
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

        $args = ["-p " . $jobConfig->documentId];

        $env = Config::getEnvironment();
        if ($env !== false) {
            $args[] = "--environment=" . $env;
        }

        $pathConsole = escapeshellarg(PIMCORE_PATH . DIRECTORY_SEPARATOR . "cli" . DIRECTORY_SEPARATOR . "console.php");
        $args = implode(" ", $args);
        $cmd = Tool\Console::getPhpCli() . " " . $pathConsole . " web2printActivePublishing:pdf-creation " . $args;
        Logger::info($cmd);

        $logfile = escapeshellarg(PIMCORE_LOG_DIRECTORY . DIRECTORY_SEPARATOR . "web2print-output.log");
        Tool\Console::execInBackground($cmd, $logfile);
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
     */
    protected function saveJobConfigObjectFile($jobConfig)
    {
        file_put_contents($this->getJobConfigFile($jobConfig->documentId), json_encode($jobConfig));
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
        $jobconfigfile = $this->getJobConfigFile($documentId);
        return json_decode(file_get_contents($jobconfigfile));
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

        $this->buildPdf($document, $jobConfigFile->config);

        Model\Tool\Lock::release($document->getLockKey());
        Model\Tool\TmpStore::delete($document->getLockKey());

        @unlink($this->getJobConfigFile($documentId));

        $document->setLastGenerated(time());
        $document->save();
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
