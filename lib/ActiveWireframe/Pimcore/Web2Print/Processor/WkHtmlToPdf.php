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

use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Db\Pages;
use ActiveWireframe\Pimcore\Web2Print\Processor;
use mikehaertl\pdftk\Pdf as MKH_Pdftk;
use mikehaertl\wkhtmlto\Pdf as MKH_Pdf;
use Pimcore\Config;
use Pimcore\Helper;
use Pimcore\Logger;
use Pimcore\Model\Document;
use Pimcore\Placeholder;

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
     * @param string $wkhtmltopdfBin
     * @param array $options key => value
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
     * create a page pdf or chapter/catalog pdf
     *
     * @param Document\PrintAbstract $document
     * @param $config
     * @return bool
     */
    protected function buildPdf(Document\PrintAbstract $document, $config)
    {
        if ($document instanceof Document\Printpage) {
            $this->buildPdfPrintpage($document);
        } elseif ($document instanceof Document\Printcontainer) {
            $this->buildPdfPrintcontainer($document);
        }

        $document->setLastGenerateMessage("");

        return true;
    }

    /**
     * Built page PDF
     *
     * @param Document\Printpage $document
     * @return bool|string
     * @throws \Exception
     */
    private function buildPdfPrintpage(Document\Printpage $document)
    {
        $web2printConfig = Config::getWeb2PrintConfig();

        $params = [];
        $this->updateStatus($document->getId(), 10, "start_html_rendering");
        $html = $document->renderDocument($params);
        $placeholder = new \Pimcore\Placeholder();
        $html = $placeholder->replacePlaceholders($html);
        $html = \Pimcore\Helper\Mail::setAbsolutePaths($html, $document, $web2printConfig->wkhtml2pdfHostname);

        $this->updateStatus($document->getId(), 40, "finished_html_rendering");

        file_put_contents(PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltorpdf-input.html", $html);

        $this->updateStatus($document->getId(), 45, "saved_html_file");

        try {

            $this->updateStatus($document->getId(), 50, "pdf_conversion");
            $pdf = $this->createPdf($document, $html);
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
     * create pdf
     *
     * @param Document\Printpage $document
     * @param $html
     * @return bool
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

        $pdftk = new MKH_Pdftk($document->getPdfFileName());
        $pdftk->cat(1)->saveAs($document->getPdfFileName()); // single page

        @unlink($html);

        return $document->getPdfFileName();
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
     * Built catalog or chapter pdf
     *
     * @param Document\Printcontainer $document
     * @return bool|string
     * @throws \Exception
     */
    private function buildPdfPrintcontainer(Document\Printcontainer $document)
    {
        try {
            $params = [];

            // get all pdf of container
            $arrayPDF = $this->getPdfFromContainer($document, $params);

            // assemble all pdf
            if (!empty($arrayPDF)) {

                $pdf = new MKH_Pdftk($arrayPDF);
                if (!$pdf->saveAs($document->getPdfFileName())) {
                    throw new \Exception('Could not create PDF: ' . $pdf->getError());
                }

            } else {
                throw new \Exception('Could not create PDF: No pdf to assemble');
            }

            $this->updateStatus($document->getId(), 100, "saving_pdf_document");

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
     * @param $params
     * @param array $arrayPDF
     * @return array
     */
    private function getPdfFromContainer(Document\Printcontainer $document, $params, $arrayPDF = [])
    {
        if ($document->hasChilds()) {

            foreach ($document->getChilds() as $child) {

                // Chapter case
                if ($child instanceof Document\Printcontainer and $child->hasChilds()) {

                    $arrayPDF = $this->getPdfFromContainer($child, $params, $arrayPDF);

                } elseif ($child instanceof Document\Printpage) { // Page case

                    try {
                        $pdf = $this->buildPdfPrintpage($child);
                        $arrayPDF[] = $pdf;
                    } catch (\Exception $ex) {}

                }
            }

        }

        return $arrayPDF;
    }

}
