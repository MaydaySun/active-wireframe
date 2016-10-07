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

namespace ActiveWireframe\Pimcore\Web2Print\Processor;

use ActiveWireframe\Db\Pages;
use Pimcore\Config;
use Pimcore\Helper;
use Pimcore\Logger;
use Pimcore\Model;
use Pimcore\Model\Document;
use Pimcore\Placeholder;
use Pimcore\Tool;

class WkHtmlToPdf extends \Pimcore\Web2Print\Processor\WkHtmlToPdf
{
    /**
     * @var string
     */
    private $wkhtmltopdfBin;

    /**
     * @var
     */
    private $options;

    /**
     * @param string $wkhtmltopdfBin
     * @param array $options key => value
     */
    public function __construct($wkhtmltopdfBin = null, $options = null)
    {
        parent::__construct();
        $web2printConfig = Config::getWeb2PrintConfig();

        if (empty($wkhtmltopdfBin)) {
            $this->wkhtmltopdfBin = $web2printConfig->wkhtmltopdfBin;
        } else {
            $this->wkhtmltopdfBin = $wkhtmltopdfBin;
        }

        if (empty($options)) {
            if ($web2printConfig->wkhtml2pdfOptions) {
                $options = $web2printConfig->wkhtml2pdfOptions->toArray();
            }
        }

        if ($options) {
            foreach ($options as $key => $value) {
                $this->options .= " --" . (string)$key;
                if ($value !== null && $value !== "") {
                    $this->options .= " " . (string)$value;
                }
            }
        } else {
            $this->options = "";
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

        Tool\Console::execInBackground($cmd, PIMCORE_LOG_DIRECTORY . DIRECTORY_SEPARATOR . "web2print-output.log");

//        if (!$config['disableBackgroundExecution']) {
//            Tool\Console::execInBackground($cmd, PIMCORE_LOG_DIRECTORY . DIRECTORY_SEPARATOR . "web2print-output.log");
//        } else {
//            Processor::getInstance()->startPdfGeneration($jobConfig->documentId);
//        }
    }

    /**
     * @param $documentId
     */
    public function setOptionsCatalogs($documentId)
    {
        if ($catalog = Pages::getInstance()->getCatalogByDocumentId($documentId)) {

            $options = [
//                'enable-smart-shrinking' => null,
                'encoding' => 'UTF-8',
                'margin-top' => 0,
                'margin-right' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'page-width' => $catalog['format_width'] . 'mm',
                'page-height' => $catalog['format_height'] . 'mm',
                'orientation' => $catalog['orientation'] != "auto" ? $catalog['orientation'] : "portrait",
                'dpi' => 96,
                'image-quality' => 90,
                'image-dpi' => 96,
                'zoom' => 1
            ];

            foreach ($options as $key => $value) {
                $this->options .= " --" . (string)$key;
                if ($value !== null and $value !== "") {
                    $this->options .= " " . (string)$value;
                }
            }

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

    /**
     *
     *
     * @param Document\PrintAbstract $document
     * @param $config
     * @return string
     * @throws \Exception
     */
    protected function buildPdf(Document\PrintAbstract $document, $config)
    {
        $web2printConfig = Config::getWeb2PrintConfig();

        $params = [];
        $this->updateStatus($document->getId(), 10, "start_html_rendering");
        $html = $document->renderDocument($params);

        $placeholder = new Placeholder();
        $html = $placeholder->replacePlaceholders($html);
        $html = Helper\Mail::setAbsolutePaths($html, $document, $web2printConfig->wkhtml2pdfHostname);

        $this->updateStatus($document->getId(), 40, "finished_html_rendering");

        file_put_contents(PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltorpdf-input.html", $html);

        $this->updateStatus($document->getId(), 45, "saved_html_file");

        try {
            $this->updateStatus($document->getId(), 50, "pdf_conversion");

            $pdf = $this->fromStringToStream($html);

            $this->updateStatus($document->getId(), 100, "saving_pdf_document");
        } catch (\Exception $e) {
            Logger::error($e);
            $document->setLastGenerateMessage($e->getMessage());
            throw new \Exception("Error during REST-Request:" . $e->getMessage());
        }

        $document->setLastGenerateMessage("");

        return $pdf;
    }

    /**
     *
     *
     * @param string $htmlString
     * @return string
     */
    public function fromStringToStream($htmlString)
    {
        $tmpFile = $this->fromStringToFile($htmlString);
        $stream = file_get_contents($tmpFile);
        @unlink($tmpFile);

        return $stream;
    }

    /**
     *
     *
     * @param string $htmlString
     * @param string $dstFile
     * @return string
     */
    public function fromStringToFile($htmlString, $dstFile = null)
    {
        $id = uniqid();
        $tmpHtmlFile = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . $id . ".htm";
        file_put_contents($tmpHtmlFile, $htmlString);
        $srcUrl = $this->getTempFileUrl() . basename($tmpHtmlFile);

        $pdfFile = $this->convert($srcUrl, $dstFile);

        @unlink($tmpHtmlFile);

        return $pdfFile;
    }

    /**
     *
     *
     * @param $srcUrl
     * @param null $dstFile
     * @return null|string
     * @throws \Exception
     */
    protected function convert($srcUrl, $dstFile = null)
    {
        $outputFile = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltopdf.out";
        if (empty($dstFile)) {
            $dstFile = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . uniqid() . ".pdf";
        }

        if (empty($srcUrl) || empty($dstFile) || empty($this->wkhtmltopdfBin)) {
            throw new \Exception("srcUrl || dstFile || wkhtmltopdfBin is empty!");
        }

        $retVal = 0;
        $cmd = $this->wkhtmltopdfBin . " " . $this->options . " " . escapeshellarg($srcUrl) . " " . escapeshellarg($dstFile) . " > " . $outputFile;
        system($cmd, $retVal);
        $output = file_get_contents($outputFile);
        @unlink($outputFile);

        if ($retVal != 0 && $retVal != 1) {
            throw new \Exception("wkhtmltopdf reported error (" . $retVal . "): \n" . $output . "\ncommand was:" . $cmd);
        }

        return $dstFile;
    }

}
