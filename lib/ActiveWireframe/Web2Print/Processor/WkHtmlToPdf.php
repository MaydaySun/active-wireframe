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
namespace ActiveWireframe\Web2Print\Processor;

use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Web2Print\Processor;
use mikehaertl\wkhtmlto\Pdf as MKH_Pdf;
use mikehaertl\pdftk\Pdf as MKH_Pdftk;
use Pimcore\Config;
use Pimcore\Logger;
use Pimcore\Helper\Mail;
use Pimcore\Model\Document;

/**
 * Class WkHtmlToPdf
 *
 * @package ActiveWireframe\Pimcore\Web2Print\Processor
 */
class WkHtmlToPdf extends Processor
{
    /**
     * @var string
     */
    private $wkhtmltopdfBin;

    /**
     * WkHtmlToPdf constructor.
     *
     * @param null $wkhtmltopdfBin
     * @param null $options
     */
    public function __construct($wkhtmltopdfBin = null, $options = null)
    {
        $web2printConfig = Config::getWeb2PrintConfig();

        if (empty($wkhtmltopdfBin)) {
            $this->wkhtmltopdfBin = $web2printConfig->wkhtmltopdfBin;
        } else {
            $this->wkhtmltopdfBin = $wkhtmltopdfBin;
        }
    }

    /**
     * @return array
     */
    public function getProcessingOptions()
    {
        return [];
    }

    /**
     * @param Document\PrintAbstract $document
     * @param $config
     * @return bool
     */
    protected function buildPdf(Document\PrintAbstract $document, $config)
    {
        $document->setLastGenerateMessage("");

        if ($document instanceof Document\Printpage) {
            $this->buildPdfPrintpage($document);
        } elseif ($document instanceof Document\Printcontainer) {
            $this->buildPdfPrintcontainer($document);
        }

        return true;
    }

    /**
     * @param Document\Printpage $document
     */
    private function buildPdfPrintpage(Document\Printpage $document)
    {
        $web2printConfig = Config::getWeb2PrintConfig();

        $params = [];
        $this->updateStatus($document->getId(), 10, "start_html_rendering");

        $placeholder = new \Pimcore\Placeholder();
        $html = $document->renderDocument($params);
        $html = $placeholder->replacePlaceholders($html);
        $html = Mail::setAbsolutePaths($html, $document, $web2printConfig->wkhtml2pdfHostname);

        $this->updateStatus($document->getId(), 40, "finished_html_rendering");

        file_put_contents(PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltorpdf-input.html", $html);

        $this->updateStatus($document->getId(), 45, "saved_html_file");

        try {

            $this->updateStatus($document->getId(), 50, "pdf_conversion");
            $this->createPdf($document, $html);
            $this->updateStatus($document->getId(), 100, "saving_pdf_document");

        } catch (\Exception $e) {
            Logger::error($e->getMessage());
            $document->setLastGenerateMessage($e->getMessage());
        }
    }

    /**
     * @param Document\Printpage $document
     * @param $html
     * @throws \Exception
     */
    private function createPdf(Document\Printpage $document, $html)
    {
        $pdf = new MKH_Pdf($this->getOptionsCatalog($document->getId(), "printpage"));
        $pdf->ignoreWarnings = true;
        $pdf->addPage($html);

        if (!$pdf->saveAs($document->getPdfFileName())) {
            throw new \Exception('Could not create PDF: ' . $pdf->getError());
        }

        // Check Pdf
        $pdftk = new MKH_Pdftk($document->getPdfFileName());
        $pdftk->cat(1);
        if (!$pdftk->saveAs($document->getPdfFileName())) {
            throw new \Exception('Could not create PDF: ' . $pdftk->getError());
        }
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
            'dpi' => 96,
            'image-quality' => 100,
            'image-dpi' => 96,
            'zoom' => 1
        ];

    }

    /**
     * @param Document\Printcontainer $document
     */
    private function buildPdfPrintcontainer(Document\Printcontainer $document)
    {
        $params = [];
        $arrayPdf = $this->getPdfFromContainer($document, $params);

        if (!empty($arrayPdf)) {

            $pdf = new MKH_Pdftk($arrayPdf);
            $pdf->ignoreWarnings = true;

            if (!$pdf->saveAs($document->getPdfFileName())) {
                $document->setLastGenerateMessage('Could not create PDF: ' . $pdf->getError());
            }

            $this->updateStatus($document->getId(), 100, "saving_pdf_document");

        } else {
            $document->setLastGenerateMessage('Could not create PDF: No pdf to assemble');
        }
    }

    /**
     * Récupère tous les PDF d'un containeur
     *
     * @param Document\Printcontainer $document
     * @param $params
     * @param array $arrayHtml
     * @return array
     */
    private function getPdfFromContainer(Document\Printcontainer $document, $params, $arrayHtml = [])
    {
        if ($document->hasChilds()) {

            foreach ($document->getChilds() as $child) {

                // Chapter case
                if ($child instanceof Document\Printcontainer and $child->hasChilds()) {

                    $arrayHtml = $this->getPdfFromContainer($child, $params, $arrayHtml);

                } elseif ($child instanceof Document\Printpage) { // Page case

                    if ($result = $this->buildPdfPrintpageForPrintcontainer($document, $child)) {
                        $arrayHtml[] = $result;
                    }

                }
            }

        }

        return $arrayHtml;
    }

    /**
     * Création des pages pdf pour les containeurs
     *
     * @param Document\Printcontainer $container
     * @param Document\Printpage $document
     * @return bool|string
     */
    private function buildPdfPrintpageForPrintcontainer(Document\Printcontainer $container, Document\Printpage $document)
    {
        $web2printConfig = Config::getWeb2PrintConfig();

        $params = [];
        $this->updateStatus($container->getId(), 25, "start_html_rendering");

        $placeholder = new \Pimcore\Placeholder();
        $html = $document->renderDocument($params);
        $html = $placeholder->replacePlaceholders($html);
        $html = \Pimcore\Helper\Mail::setAbsolutePaths($html, $document, $web2printConfig->wkhtml2pdfHostname);

        $this->updateStatus($container->getId(), 50, "finished_html_rendering");

        $this->updateStatus($container->getId(), 75, "pdf_conversion");
        try {
            $this->createPdf($document, $html);
            return $document->getPdfFileName();
        } catch (\Exception $ex) {}

        return false;
    }

}
