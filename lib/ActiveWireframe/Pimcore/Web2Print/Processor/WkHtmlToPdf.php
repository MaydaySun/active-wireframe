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

namespace ActiveWireframe\Pimcore\Web2Print\Processor;

use ActivePublishing\Service\Pdf as APS_Pdf;
use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Pages;
use mikehaertl\wkhtmlto\Pdf;
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

        $cmd = Tool\Console::getPhpCli() . " " .
            realpath(PIMCORE_PATH . DIRECTORY_SEPARATOR . "cli" . DIRECTORY_SEPARATOR . "console.php") .
            " web2printActivePublishing:pdf-creation " . implode(" ", $args);
        Logger::info($cmd);

        Tool\Console::execInBackground($cmd, PIMCORE_LOG_DIRECTORY . DIRECTORY_SEPARATOR . "web2print-output.log");
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
            $this->buildPdf($document, $jobConfigFile->config);

//            \Pimcore::getEventManager()->trigger("document.print.postPdfGeneration", $document, [
//                "filename" => $document->getPdfFileName(),
//                "pdf" => $pdf
//            ]);

            Model\Tool\Lock::release($document->getLockKey());
            Model\Tool\TmpStore::delete($document->getLockKey());
            @unlink($this->getJobConfigFile($documentId));

            $creationDate = \Zend_Date::now();
            $document->setLastGenerated($creationDate->get() + 1);
            $document->save();

        } catch (\Exception $e) {
            $document->save();
            Model\Tool\Lock::release($document->getLockKey());
            Model\Tool\TmpStore::delete($document->getLockKey());
            @unlink($this->getJobConfigFile($documentId));
            Logger::err($e);
        }

    }

    /**
     *
     *
     * @param Document\PrintAbstract $document
     * @param $config
     * @return bool
     */
    protected function buildPdf(Document\PrintAbstract $document, $config)
    {
        if ($document instanceof Document\Printpage) {
            $this->buildPdfPrintpage($document, $config);
        } elseif ($document instanceof Document\Printcontainer) {
            $this->buildPdfPrintcontainer($document, $config);
        }
        return true;
    }

    /**
     * Built page PDF
     *
     * @param Document\Printpage $document
     * @param $config
     * @return bool|string
     * @throws \Exception
     */
    private function buildPdfPrintpage(Document\Printpage $document, $config)
    {
        try {
            $params = [];

            $this->updateStatus($document->getId(), 10, "start_html_rendering");
            $html = $this->getHtml($document, $params);

            $this->updateStatus($document->getId(), 40, "finished_html_rendering");
            $fileHTML = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltorpdf-input.html";
            file_put_contents($fileHTML, $html);
            $this->updateStatus($document->getId(), 45, "saved_html_file");

            $this->updateStatus($document->getId(), 50, "pdf_conversion");
            $filePDF = $this->createStringPdf($document, $fileHTML);

            $this->updateStatus($document->getId(), 100, "saving_pdf_document");

        } catch (\Exception $e) {
            Logger::error($e);
            $document->setLastGenerateMessage($e->getMessage());
            throw new \Exception("Error during REST-Request:" . $e->getMessage());
        }

        $document->setLastGenerateMessage("");
        return $filePDF;
    }

    /**
     * render document
     *
     * @param Document\Printpage $document
     * @param $params
     * @return mixed|string
     */
    private function getHtml(Document\Printpage $document, $params)
    {
        $web2printConfig = Config::getWeb2PrintConfig();
        $html = $document->renderDocument($params);
        $placeholder = new Placeholder();
        $html = $placeholder->replacePlaceholders($html);
        $html = Helper\Mail::setAbsolutePaths($html, $document, $web2printConfig->wkhtml2pdfHostname);
        return $html;
    }

    /**
     * create pdf and convert to string
     *
     * @param Document\Printpage $document
     * @param $fileHTML
     * @return bool|string
     * @throws \Exception
     */
    private function createStringPdf(Document\Printpage $document, $fileHTML)
    {
        $pdf = new Pdf($this->getOptionsCatalog($document->getId(), "printpage"));
        $pdf->addPage($fileHTML);

        if (!$filePDF = $pdf->toString()) {
            throw new \Exception('Could not create PDF: ' . $pdf->getError());
        }

        $pdfTmp = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR .
            "web2print-document-tmp" . $document->getId() . ".pdf";

        // single page
        if (file_put_contents($pdfTmp, $pdf)) {
            APS_Pdf::cut($pdfTmp, $document->getPdfFileName(), 1);
        }

        @unlink($fileHTML);
        return $filePDF;
    }

    /**
     * @param $documentId
     * @return array
     */
    private function getOptionsCatalog($documentId, $type)
    {
        if ($type == "printpage") {
            $catalog = Pages::getInstance()->getCatalogByDocumentId($documentId);
        } elseif ($type == "printcontainer") {
            $catalog = Catalogs::getInstance()->getCatalogByDocumentId($documentId);
        } else {
            $catalog = [
                'format_width' => 210,
                'format_height' => 297,
                'orientation' => "portrait"
            ];
        }

        return [
            'enable-smart-shrinking' => null,
            'encoding' => 'UTF-8',
            'margin-top' => 0,
            'margin-right' => 0,
            'margin-bottom' => 0,
            'margin-left' => 0,
            'page-width' => $catalog['format_width'] . 'mm',
            'page-height' => $catalog['format_height'] . 'mm',
            'orientation' => $catalog['orientation'] != "auto" ? $catalog['orientation'] : "portrait",
            'dpi' => 300,
            'image-quality' => 100,
            'image-dpi' => 300,
            'zoom' => 1
        ];

    }

    /**
     * Built catalog or chapter pdf
     *
     * @param Document\Printcontainer $document
     * @param $config
     * @return bool|string
     * @throws \Exception
     */
    private function buildPdfPrintcontainer(Document\Printcontainer $document, $config)
    {
        try {

            $this->updateStatus($document->getId(), 10, "start_html_rendering");

            // Retrieve all file html of children
            $arrayFileHtml = $this->getFileHtmlFromContainer($document, $config);

            $this->updateStatus($document->getId(), 50, "pdf_conversion");
            // Create one pdf foreach children
            $arrayFilePdf = [];
            if (!empty($arrayFileHtml)) {
                foreach ($arrayFileHtml as $fileHtml) {
                    $arrayFilePdf[] = $this->convert($fileHtml);
                }
            }

            $this->updateStatus($document->getId(), 100, "saving_pdf_document");
            // assemble all pdf
            APS_Pdf::concat($arrayFilePdf, $document->getPdfFileName());

            // Clear page pdf
            if (!empty($arrayFilePdf)) {
                foreach ($arrayFilePdf as $filePdf) {
                    @unlink($filePdf);
                }
            }

        } catch (\Exception $e) {
            Logger::error($e);
            $document->setLastGenerateMessage($e->getMessage());
            throw new \Exception("Error during REST-Request:" . $e->getMessage());
        }

        $document->setLastGenerateMessage("");
        return true;
    }

    /**
     * Get all file html of document container
     *
     * @param Document\Printcontainer $document
     * @param $config
     * @param array $arrayFileHtml
     * @return array
     */
    private function getFileHtmlFromContainer(Document\Printcontainer $document, $config, $arrayFileHtml = [])
    {
        if ($document->hasChilds()) {

            foreach ($document->getChilds() as $child) {
                // Chapter case
                if ($child instanceof Document\Printcontainer and $child->hasChilds()) {
                    $this->getFileHtmlFromContainer($child, $config, $arrayFileHtml);

                } elseif ($child instanceof Document\Printpage) { // Page case
                    $filename = uniqid("webtoprint-page");
                    $fileHtml = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . $filename . ".html";
                    $html = $this->getHtml($child, $config);
                    file_put_contents($fileHtml, $html);
                    if (file_exists($fileHtml)){
                        $arrayFileHtml[] = $fileHtml;
                    }
                }
            }

        }

        return $arrayFileHtml;
    }

}
