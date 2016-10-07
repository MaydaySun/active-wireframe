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
namespace ActiveWireframe\Pimcore\Web2Print;

use ActiveWireframe\Pimcore\Web2Print\Processor\WkHtmlToPdf;
use Pimcore\Config;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Tool;
use Pimcore\Web2Print\Processor\PdfReactor8;

abstract class Processor extends \Pimcore\Web2Print\Processor
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

        $cmd = Tool\Console::getPhpCli() . " " . realpath(PIMCORE_PATH . DIRECTORY_SEPARATOR . "cli" . DIRECTORY_SEPARATOR . "console.php"). " web2printActivePublishing:pdf-creation " . implode(" ", $args);

        Logger::info($cmd);

        if (!$config['disableBackgroundExecution']) {
            Tool\Console::execInBackground($cmd, PIMCORE_LOG_DIRECTORY . DIRECTORY_SEPARATOR . "web2print-output.log");
        } else {
            $this->startPdfGeneration($jobConfig->documentId);
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
            $document->setLastGenerated((intval($creationDate->get()) + 1));
            $document->save();
        } catch (\Exception $e) {
            $document->save();
            Logger::err($e);
        }

        Model\Tool\Lock::release($document->getLockKey());
        Model\Tool\TmpStore::delete($document->getLockKey());

        @unlink($this->getJobConfigFile($documentId));
    }

}
