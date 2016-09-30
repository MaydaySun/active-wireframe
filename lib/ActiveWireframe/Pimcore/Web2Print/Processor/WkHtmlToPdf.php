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

use ActiveWireframe\Db\Catalogs;
use ActiveWireframe\Pimcore\Web2Print\Processor;
use Pimcore\Config;
use Pimcore\Logger;
use Pimcore\Model\Document;

class WkHtmlToPdf extends Processor
{
    /**
     * @var string
     */
    private $wkhtmltopdfBin;

    /**
     * @var string
     */
    private $options = "";


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

        if (empty($options)) {
            if ($web2printConfig->wkhtml2pdfOptions) {
                $options = $web2printConfig->wkhtml2pdfOptions->toArray();
            }
        }

        if ($options) {
            foreach ($options as $key => $value) {
                $this->options .= " --" . (string)$key;
                if ($value !== null and $value !== "") {
                    $this->options .= " " . (string)$value;
                }
            }
        } else {
            $this->options = "";
        }

    }

    /**
     * @param $documentId
     */
    public function setOptionsCatalogs($documentId)
    {
        // Document
        $document = Document\Printpage::getById(intval($documentId));

        // Récupère les données du catalogue
        $dbcatalog = new Catalogs();

        // Cas d'une page hors chapitre
        $catalog = $dbcatalog->getCatalogByDocumentId($document->getParentId());
        if (!$catalog) {
            // Cas d'une page dans un chapitre
            $catalog = $dbcatalog->getCatalogByDocumentId($document->getParent()->getParentId());
        }

        if ($catalog) {

            $options = array(
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
            );

            foreach ($options as $key => $value) {
                $this->options .= " --" . (string)$key;
                if ($value !== null and $value !== "") {
                    $this->options .= " " . (string)$value;
                }
            }

        }

    }

    public function getProcessingOptions()
    {
        return [];
    }

    /**
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

        $placeholder = new \Pimcore\Placeholder();
        $html = $placeholder->replacePlaceholders($html);
        $html = \Pimcore\Helper\Mail::setAbsolutePaths($html, $document, $web2printConfig->wkhtml2pdfHostname);

        $this->updateStatus($document->getId(), 40, "finished_html_rendering");

        file_put_contents(PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltorpdf-input.html", $html);

        $this->updateStatus($document->getId(), 45, "saved_html_file");

        try {

            $this->updateStatus($document->getId(), 50, "pdf_conversion");
            $pdf = $this->fromStringToStream($html);

//            // Fichier PDF
//            $pdfFile = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR
//                . 'web2print-document-' . $document->getId() . '.pdf';
//
//            // Page supérieur à 1
//            if (Pdf::countPages($pdfFile) > 1) {
//                $pdfTmp = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR
//                    . 'web2print-document-' . $document->getId() . '-' . uniqid() . '.pdf';
//                $pdf = file_get_contents(Pdf::cut($pdfFile, $pdfTmp, 1));
//                @unlink($pdfTmp);
//            }

            $this->updateStatus($document->getId(), 100, "saving_pdf_document");
            $document->setLastGenerateMessage("");

            return $pdf;

        } catch (\Exception $e) {
            Logger::error($e);
            $document->setLastGenerateMessage($e->getMessage());
            throw new \Exception("Error during REST-Request:" . $e->getMessage());
        }
    }

    /**
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

    public static function getTempFileUrl()
    {
        $web2printConfig = Config::getWeb2PrintConfig();
        if ($web2printConfig->wkhtml2pdfHostname) {
            return $web2printConfig->wkhtml2pdfHostname . "/website/var/tmp/";
        } elseif (\Pimcore\Config::getSystemConfig()->general->domain) {
            $hostname = \Pimcore\Config::getSystemConfig()->general->domain;
        } else {
            $hostname = $_SERVER["HTTP_HOST"];
        }

        $protocol = $_SERVER['HTTPS'] == 'on' ? 'https' : 'http';

        return $protocol . "://" . $hostname . "/website/var/tmp/";
    }

    /**
     * @throws \Exception
     * @param string $srcFile
     * @param string $dstFile
     * @return string
     */
    protected function convert($srcUrl, $dstFile = null)
    {
        $outputFile = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltopdf.out";
        if (empty($dstFile)) {
            $dstFile = PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . uniqid() . ".pdf";
        }

        if (empty($srcUrl) or empty($dstFile) or empty($this->wkhtmltopdfBin)) {
            throw new \Exception("srcUrl or dstFile or wkhtmltopdfBin is empty!");
        }

        $retVal = 0;
        $cmd = $this->wkhtmltopdfBin . " " . $this->options . " " . escapeshellarg($srcUrl) . " " . escapeshellarg($dstFile) . " > " . $outputFile;

        system($cmd, $retVal);
        $output = file_get_contents($outputFile);
        @unlink($outputFile);

        if ($retVal != 0 and $retVal != 1) {
            throw new \Exception("wkhtmltopdf reported error (" . $retVal . "): \n" . $output . "\ncommand was:" . $cmd);
        }

        return $dstFile;
    }
}
