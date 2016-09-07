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
namespace ActiveWireframe;

use ActiveWireframe\Db\Pages;
use ActiveWireframe\Pimcore\Image\HtmlToImage;
use Pimcore\File;
use Pimcore\Logger;
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
     * Reload thumbnail of catalog and chapter
     * @param Printcontainer $document
     * @param $width
     * @return int
     */
    public static function reloadThumbnailForTree(Printcontainer $document, $width)
    {
        foreach ($document->getChilds() as $child) {

            if ($child instanceof Printpage) {
                self::getPageThumbnailForTree($child, $width);
            } elseif ($child instanceof Printcontainer) {
                self::reloadThumbnailForTree($child, $width);
            }

        }

        return true;
    }

    /**
     * Create a thumbnail for each  page
     * @param Document $document
     * @param $width
     * @return bool
     */
    public static function getPageThumbnailForTree(Document $document, $width)
    {
        $widthWk = number_format($width * 0.25);

        // Dir tmp
        $dirTmp = PIMCORE_DOCUMENT_ROOT . DIRECTORY_SEPARATOR
            . "activetmp" . DIRECTORY_SEPARATOR
            . Plugin::PLUGIN_NAME . DIRECTORY_SEPARATOR
            . $document->getId();

        // path thumbnail
        if (!file_exists($dirTmp)) {
            File::mkdir($dirTmp);
        }

        $url = Tool::getHostUrl() . $document->getFullPath() . '?nowkhtmltoimage=true';
        $dst = $dirTmp . DIRECTORY_SEPARATOR . $document->getId() . '.jpeg';
        $options = [
            'width' => $widthWk,
            'quality' => 75,
            'zoom' => 0.25
        ];
        return HtmlToImage::convert($url, $dst, 'jpeg', $options);
    }

    /**
     * Create a new pagination
     * @param Printcontainer $document
     * @param int $index
     * @param array $noRename
     * @return int
     */
    public static function generateNewPagination(Printcontainer $document, $index = 1, $noRename = array())
    {
        // No child
        if (!$document->hasChilds()) {
            return 0;
        }

        foreach ($document->getChilds() as $page) {

            if (($page instanceof Printpage) and (!in_array($page->getKey(), $noRename))) {

                if (ctype_digit($page->getKey())) {

                    try {

                        $page->setKey($index);
                        $page->save();

                    } catch (\Exception $ex) {
                        Logger::err($ex->getMessage());
                        return 0;
                    }

                }

                $index++;

            } elseif ($page instanceof Printcontainer
                and $page->hasChilds()
                and (!in_array($page->getKey(), $noRename))
            ) {

                $index = self::generateNewPagination($page, $index, $noRename);

            }

        }

        return $index;
    }

    /**
     * Reduction for thumb
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

    /**
     * Create catalog page
     * @param $key
     * @param $parentId
     * @param array $configs
     * @return bool|Printpage
     */
    public static function createPage($key, $parentId, $configs = array())
    {
        // Validate key
        if (!Tool::isValidKey($key)) {
            $key = File::getValidFilename($key);
        }

        $dataDocument = array(
            "key" => $key,
            "published" => 1,
            "module" => "ActiveWireframe",
            "controller" => "pages",
            "action" => "pages"
        );

        try {
            $page = Printpage::create($parentId, $dataDocument);

            // Insert BD
            $dbPages = new Pages();
            $where = $dbPages->getAdapter()->quoteInto('document_id = ?', $page->getId());
            $dbPages->update($configs, $where);

            $page->setModificationDate(time());
            $page->save();

        } catch (\Exception $ex) {
            Logger::err($ex->getMessage());
            return false;
        }

        return $page;
    }

    /**
     * Create a chapter
     * @param $key
     * @param $parentId
     * @return bool|Printcontainer
     */
    public static function createChapter($key, $parentId)
    {
        if (!Tool::isValidKey($key)) {
            $key = File::getValidFilename($key);
        }

        $dataDocument = array(
            "key" => $key,
            "published" => 1,
            "module" => "ActiveWireframe",
            "controller" => "catalogs",
            "action" => "tree"
        );

        try {
            $chapter = Printcontainer::create($parentId, $dataDocument);

        } catch (\Exception $ex) {
            Logger::err($ex->getMessage());
            return false;
        }

        return $chapter;
    }

}