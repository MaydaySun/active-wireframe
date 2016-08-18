<?php
/**
 * LICENSE
 *
 * This source file is subject to the new Creative Commons license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://creativecommons.org/licenses/by-nc-nd/4.0/
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contact@active-publishing.fr so we can send you a copy immediately.
 *
 * @author      Active Publishing <contact@active-publishing.fr>
 * @copyright   Copyright (c) 2015 Active Publishing (http://www.active-publishing.fr)
 * @license     http://creativecommons.org/licenses/by-nc-nd/4.0/
 */
namespace ActiveWireframe;

use ActiveWireframe\Pimcore\Image\HtmlToImage;
use Pimcore\File;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Pimcore\Tool;

/**
 * Class Helpers
 * @package ActiveWireframe
 */
class Helpers
{

    /**
     * @param Printcontainer $document
     * @param $width
     * @param $height
     * @return int
     */
    public static function reloadThumbnailForTree(Printcontainer $document, $width, $height)
    {
        foreach ($document->getChilds() as $child) {

            // Cas d'une page
            if ($child instanceof Printpage) {

                Helpers::getPageThumbnailForTree($child, $width, $height);

                // Cas des chapitres
            } elseif ($child instanceof Printcontainer) {

                // Appelle de la fonction
                self::reloadThumbnailForTree($child, $width, $height);
            }

        }

        return 1;
    }

    /**
     * Création de la miniature pour l'apercut du catalogue / chapitre
     * @param Document $document
     * @param $width
     * @param $height
     */
    public static function getPageThumbnailForTree(Document $document, $width, $height)
    {
        // Récupère la reduction
        $widthWk = number_format($width * 0.25);

        // Dir tmp
        $dirTmp = PIMCORE_DOCUMENT_ROOT . DIRECTORY_SEPARATOR
            . "activetmp" . DIRECTORY_SEPARATOR
            . Plugin::PLUGIN_NAME . DIRECTORY_SEPARATOR
            . $document->getId();

        // Chemin des thumbnails des pages
        if (!file_exists($dirTmp)) {
            File::mkdir($dirTmp);
        }

        $url = Tool::getHostUrl() . $document->getFullPath() . '?nowkhtmltoimage=true';
        $dst = $dirTmp . DIRECTORY_SEPARATOR . $document->getId() . '.png';
        $options = [
            'width' => $widthWk,
            'quality' => 75,
            'zoom' => 0.25
        ];
        HtmlToImage::convert($url, $dst, 'png', $options);
    }

    /**
     * Génère une nouvelle pagination
     * @param Printcontainer $document
     * @param int $index
     * @param array $noRename
     * @return int
     */
    public static function generateNewPagination(Printcontainer $document, $index = 1, $noRename = array())
    {
        // Le catalogue n'a pas d'enfants
        if (!$document->hasChilds()) {
            return 0;
        }

        // Parcours les pages
        foreach ($document->getChilds() as $page) {

            // On numerote seulement les types pages ET celle autorisé
            if (($page instanceof Printpage) && (!in_array($page->getKey(), $noRename))) {

                // Si la clé de la page est un nombre
                if (ctype_digit($page->getKey())) {

                    try {

                        // Modifie la clé
                        $page->setKey($index);
                        $page->save();

                    } catch (\Exception $ex) {
                        \Logger::err($ex->getMessage());
                        return 0;
                    }

                }

                $index++;

            } elseif ($page instanceof Printcontainer && $page->hasChilds() && (!in_array($page->getKey(), $noRename))
            ) {

                // Cas des pages dans un chapitre
                $index = self::generateNewPagination($page, $index, $noRename);

            }

        }

        return $index;
    }

    /**
     * Récupère la réduction d'une page pour la miniature
     * @param $widthPage
     * @return int
     */
    public static function getReduction($widthPage)
    {
        switch ($widthPage) {
            case (int)$widthPage > 1000:
                $reduction = 7;
                break;

            case (int)$widthPage > 800:
                $reduction = 6;
                break;

            case (int)$widthPage > 600:
                $reduction = 5;
                break;

            case (int)$widthPage > 400:
                $reduction = 4;
                break;
            case (int)$widthPage > 200:
                $reduction = 3;
                break;

            case (int)$widthPage > 100:
                $reduction = 2;
                break;

            default:
                $reduction = 1;
                break;
        }
        return $reduction;
    }

    /**
     * Convert px to mm
     * @param $pixels
     * @param int $dpi
     * @return float
     */
    public static function convertPxToMm($pixels, $dpi = 96)
    {
        return ($pixels * 25.4) / $dpi;
    }

    /**
     * Convert mm to px
     * @param $mm
     * @param int $dpi
     * @return float
     */
    public static function convertMmToPx($mm, $dpi = 96)
    {
        return ($dpi * $mm) / 25.4;
    }

}