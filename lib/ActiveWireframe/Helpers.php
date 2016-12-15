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

use ActivePublishing\Service\Tool;
use ActiveWireframe\Db\Elements;
use ActiveWireframe\Db\Pages;
use Pimcore\File;
use Pimcore\Image\HtmlToImage;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Pimcore\Model\Object\AbstractObject;
use Pimcore\Model\User;

/**
 * Class Helpers
 * @package ActiveWireframe
 */
class Helpers
{
    /**
     * Reload thumbnail of catalog and chapter
     *
     * @static
     * @param Printcontainer $document
     * @param $width
     * @return int
     */
    public static function reloadThumbnailForTree(Printcontainer $document, $width)
    {
        foreach ($document->getChilds() as $child) {
            $widthPX = Helpers::convertMmToPx($width);
            if ($child instanceof Printpage) {
                self::getPageThumbnailForTree($child, $widthPX);
            } elseif ($child instanceof Printcontainer) {
                self::reloadThumbnailForTree($child, $widthPX);
            }
        }
        return true;
    }

    /**
     * Convert mm to px
     *
     * @static
     * @param $mm
     * @param int $dpi
     * @return float
     */
    public static function convertMmToPx($mm, $dpi = 96)
    {
        return ($dpi * $mm) / 25.4;
    }

    /**
     * Create a thumbnail for each  page
     *
     * @static
     * @param Document $document
     * @param $widthPX
     * @return bool
     */
    public static function getPageThumbnailForTree(Document $document, $widthPX)
    {
        $dirTmp = Plugin::PLUGIN_PATH_DATA . DIRECTORY_SEPARATOR . $document->getId();
        $jpeg = $dirTmp . DIRECTORY_SEPARATOR . $document->getId() . '.jpeg';
        $url = Tool::getHostUrl() . $document->getFullPath() . '?createThumbnail=true&forcearea=true';
        $widthPX = intval(intval($widthPX) / 5);

        // path thumbnail
        if (!file_exists($dirTmp)) {
            File::mkdir($dirTmp, 0775, true);
        }

        return HtmlToImage::convert($url, $jpeg, $widthPX, "jpeg");
    }

    /**
     * Create a new pagination
     *
     * @static
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
     *
     * @static
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
     *
     * @static
     * @param $pixels
     * @param int $dpi
     * @return float
     */
    public static function convertPxToMm($pixels, $dpi = 96)
    {
        return ($pixels * 25.4) / $dpi;
    }

    /**
     * @static
     * @param $key
     * @param $parentId
     * @return Document
     * @throws \Exception
     */
    public static function createChapter($key, $parentId)
    {
        $catalog = Printcontainer::getById($parentId);
        if ($catalog) {

            // get valid key
            $key = File::getValidFilename($key);
            if ($key == "") {
                $key = "undefined-chapter-key";
            }

            $i = 1;
            while (Document\Service::pathExists($catalog->getFullPath() . DIRECTORY_SEPARATOR . $key)) {
                $key = $key . "-" . $i;
                $i++;
            }

            $dataDocument = [
                "key" => $key,
                "published" => 1,
                "module" => "ActiveWireframe",
                "controller" => "catalogs",
                "action" => "tree"
            ];

            // Chapter creation
            $chapter = Printcontainer::create($parentId, $dataDocument);

            // Insert BD
            Pages::getInstance()->insertChapter($chapter);

            return $chapter;
        }

        throw new \Exception("Printconatiner CATALOG is not found");
    }

    /**
     * Get areas for the current user
     * @static
     * @return string
     */
    public static function getAreaByRole()
    {
        // Get user and role
        $user = Tool::getCurrentUser();
        $roles = Tool::getRolesFromCurrentUser();

        // Default path
        $areaPath = '/website/views/areas';
        $areaPathAbs = PIMCORE_WEBSITE_PATH . '/views/areas';

        // User isn't admin and belong to a role
        if ($user instanceof User and !$user->isAdmin() and !empty($roles)) {
            foreach ($roles as $rid) {
                $role = User\Role::getById($rid);
                // Role and directory exists
                if ($role instanceof User\Role
                    AND file_exists($areaPathAbs . DIRECTORY_SEPARATOR . mb_strtolower($role->getName()))
                ) {
                    return $areaPath . DIRECTORY_SEPARATOR . mb_strtolower($role->getName());
                }
            }
        }

        // Default area path
        return $areaPath . '/active-wireframe';
    }

    /**
     * @static
     * @param Document $page
     * @param $cinfo
     * @param $thumbnail
     * @return bool|mixed|string
     */
    public static function getBackgroundTemplate(Document $page, $cinfo, $thumbnail)
    {
        $assetTemplate = ($page->getKey() % 2)
            ? Asset::getById(intval($cinfo['template_odd']))
            : Asset::getById(intval($cinfo['template_even']));

        if ($assetTemplate) {

            if ($assetTemplate instanceof Asset\Document) {
                return $assetTemplate->getImageThumbnail($thumbnail)->getPath();

            } else if ($assetTemplate instanceof Asset\Image) {
                return $assetTemplate->getThumbnail($thumbnail)->getPath();
            }

        }

        return false;
    }

    /**
     * Create page with areablock
     *
     * @static
     * @param $documentKey
     * @param $parentId
     * @param $catalogId
     * @param int $row
     * @param int $col
     * @return bool|Document
     */
    public static function createPageWithAreablock($documentKey, $parentId, $catalogId, $row = 0, $col = 0)
    {
        $conf = [
            'grid' => [
                'row' => $row,
                'col' => $col
            ]
        ];

        if ($page = self::createPage($documentKey, $parentId, $catalogId, $conf) AND $page instanceof Printpage) {
            try {
                $areablock = new Document\Tag\Areablock();
                $areablock->setName('pages-editable');
                $areablock->setDocumentId($page->getId());
                $areablock->setDataFromEditmode([]);
                $page->setElement('pages-editable', $areablock);
                $page->save();
                return $page;

            } catch (\Exception $ex) {
                Logger::err($ex->getMessage());
            }
        }

        return FALSE;
    }

    /**
     * @static
     * @param $key
     * @param $parentId
     * @param $catalogId
     * @param array $configs
     * @return Document
     * @throws \Exception
     */
    public static function createPage($key, $parentId, $catalogId, $configs = [])
    {
        $parent = Printcontainer::getById($parentId);
        if ($parent) {

            // get valid key
            $key = File::getValidFilename($key);
            if ($key == "") {
                $key = "undefined-page-key";
            }

            $i = 1;
            while (Document\Service::pathExists($parent->getFullPath() . DIRECTORY_SEPARATOR . $key)) {
                $key = $key . "-" . $i;
                $i++;
            }

            $dataDocument = [
                "key" => $key,
                "published" => 1,
                "module" => "ActiveWireframe",
                "controller" => "pages",
                "action" => "pages"
            ];

            // Page creation
            $page = Printpage::create($parentId, $dataDocument);

            // Insert BD
            Pages::getInstance()->insertPage($page, $catalogId, $configs);

            return $page;
        }

        throw new \Exception("Printconatiner CATALOG is not found");
    }

    /**
     * Create a tag renderlet with object
     *
     * @static
     * @param Printpage $document
     * @param $areaId
     * @param $objectId
     * @param $catalogId
     * @param $conf
     * @return bool|mixed
     */
    public static function CreateTagRenderlet(Printpage $document, $areaId, $objectId, $catalogId, $conf = [])
    {
        if ($areaId !== '') {

            $blocArea = $document->getElement('pages-editable');

            // init tag
            $indices = $blocArea->getValue();
            $index = (string)(count($indices) + 1);
            $indices[] = ['key' => $index, 'type' => $areaId];
            $blocArea->setDataFromEditmode($indices);

            // create new tag
            $renderlet = new Document\Tag\Renderlet();
            $renderlet->setName($areaId . 'pages-editable' . $index);
            $renderlet->setDataFromEditmode(['id' => $objectId, 'type' => 'object', 'subtype' => 'object']);

            // Add renderlet to page
            $document->setElement($areaId . 'pages-editable' . $index, $renderlet);

            $dataElements = array_merge([
                'document_id' => $document->getId(),
                'document_parent_id' => $document->getParentId(),
                'document_root_id' => $catalogId,
                'page_key' => $document->getKey(),
                'e_id' => 1,
                'e_key' => $index,
                'e_top' => null,
                'e_left' => null,
                'e_index' => 10
            ], $conf);

            // save
            return Elements::getInstance()->insert($dataElements);
        }

        return FALSE;
    }

    /**
     * Check children has child
     *
     * @static
     * @param AbstractObject $node
     * @return bool
     */
    public static function hasChildsRecursives(AbstractObject $node)
    {
        if ($node->hasChilds()) {
            foreach ($node->getChilds() as $child) {
                if ($child instanceof AbstractObject AND $child->hasChilds()) {
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    /**
     * Create a tag renderlet for an asset
     *
     * @static
     * @param Printpage $document
     * @param string $areaId
     * @return bool|mixed
     */
    public static function createTagRenderletForAsset(Printpage $document, $catalogId, $areaId = '')
    {
        if ($areaId != '') {

            $blocArea = $document->getElement('pages-editable');
            $indices = $blocArea->getValue();
            $index = (string)(count($indices) + 1);
            $indices[] = ['key' => $index, 'type' => $areaId];
            $blocArea->setDataFromEditmode($indices);

            try {

                $renderlet = new Document\Tag\Image();
                $renderlet->setName($areaId . "pages-editable" . $index);
                $document->setElement($areaId . "pages-editable" . $index, $renderlet);
                $document->save();

                return Elements::getInstance()->insert(array(
                    'document_id' => $document->getId(),
                    'document_parent_id' => $document->getParentId(),
                    'document_root_id' => $catalogId,
                    'page_key' => $document->getKey(),
                    'e_id' => 1,
                    'e_key' => $index,
                    'e_top' => null,
                    'e_left' => null,
                    'e_index' => 10
                ));

            } catch (\Exception $ex) {
                Logger::err($ex->getMessage());
            }

        }

        return FALSE;
    }

}