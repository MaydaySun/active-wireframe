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

use ActivePublishing\Service\Translation;
use ActivePublishing\Service\User;
use ActiveWireframe\Db\Elements;
use ActiveWireframe\Db\Pages;
use mikehaertl\wkhtmlto\Image;
use Pimcore\Config;
use Pimcore\File;
use Pimcore\Logger;
use Pimcore\Model\Asset;
use Pimcore\Model\Document;
use Pimcore\Model\Document\Printcontainer;
use Pimcore\Model\Document\Printpage;
use Pimcore\Model\Object\AbstractObject;
use Pimcore\Model\User as PimcoreUser;
use Pimcore\Helper\Mail;

/**
 * Class Helpers
 *
 * @package ActiveWireframe
 */
class Helpers
{
    /**
     * @param Printcontainer $document
     * @return bool
     */
    public static function reloadDocumentThumbnail(Printcontainer $document)
    {
        foreach ($document->getChilds() as $child) {
            if ($child instanceof Printpage) {
                self::createDocumentThumbnail($child);
            } elseif ($child instanceof Printcontainer) {
                self::reloadDocumentThumbnail($child);
            }
        }
        return true;
    }

    /**
     * @param Document $document
     * @return bool
     */
    public static function createDocumentThumbnail(Document $document)
    {
        $dirTmp = Plugin::PLUGIN_WEBSITE_PATH . DIRECTORY_SEPARATOR . $document->getId();
        $outputFile = $dirTmp . DIRECTORY_SEPARATOR . $document->getId() . '.jpeg';

        // dossier ou seras ajouté la vignette
        if (!file_exists($dirTmp)) {
            File::mkdir($dirTmp);
        }

        $web2printConfig = Config::getWeb2PrintConfig();
        $url = $web2printConfig->wkhtml2pdfHostname . $document->getFullPath() . '?createThumbnail=true';
        $url .= (strpos($url, "?") ? "&" : "?") . "pimcore_preview=true";
        try {
            $html = file_get_contents($url);
            $html = Mail::setAbsolutePaths($html, null, $web2printConfig->wkhtml2pdfHostname);
        } catch (\Exception $ex) {
            $html = "";
        }

        file_put_contents(PIMCORE_TEMPORARY_DIRECTORY . DIRECTORY_SEPARATOR . "wkhtmltoimage-input.html", $html);
        $image = new Image([
            'width' => 300,
            'format' => 'jpeg'
        ]);
        $image->setPage($html);
        $image->ignoreWarnings = true;

        if ($image->saveAs($outputFile) and file_exists($outputFile) and (filesize($outputFile) > 1000)) {
            return true;
        } else {
            $logfile = PIMCORE_LOG_DIRECTORY . "/wkhtmltoimage.log";
            File::put($logfile, $image->getError());
        }
        return false;
    }

    /**
     * @param $mm
     * @param int $dpi
     * @return float
     */
    public static function convertMmToPx($mm, $dpi = 96)
    {
        return ($dpi * $mm) / 25.4;
    }

    /**
     * @param $pixels
     * @param int $dpi
     * @return float|int
     */
    public static function convertPxToMm($pixels, $dpi = 96)
    {
        return ($pixels * 25.4) / $dpi;
    }

    /**
     * @param Printcontainer $document
     * @param int $index
     * @param array $noRename
     * @return int
     */
    public static function generateNewPagination(Printcontainer $document, $index = 1, $noRename = array())
    {
        if (!$document->hasChilds()) {
            return 0;
        }

        foreach ($document->getChilds() as $page) {
            if (($page instanceof Printpage) and (!in_array($page->getKey(), $noRename))) {
                try {
                    $page->setKey(File::getValidFilename($index));
                    $page->save();
                } catch (\Exception $ex) {
                    echo $ex->getMessage();
                    Logger::err($ex->getMessage());
                }
                $index++;
            } elseif ($page instanceof Printcontainer and $page->hasChilds()
                and (!in_array($page->getKey(), $noRename))
            ) {
                $index = self::generateNewPagination($page, $index, $noRename);
            }
        }
        return $index;
    }

    /**
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
     * @param $key
     * @param $parentId
     * @return Document
     * @throws \Exception
     */
    public static function createChapter($key, $parentId)
    {
        $catalog = Printcontainer::getById($parentId);
        if ($catalog) {
            $key = File::getValidFilename($key);
            if ($key == "") {
                $key = Translation::get("active_wireframe_chapter");
            }

            // $key unique
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
            $chapter = Printcontainer::create($parentId, $dataDocument);
            Pages::getInstance()->insertChapter($chapter);
            return $chapter;
        }
        throw new \Exception("Printcontainer CATALOG is not found");
    }

    /**
     * @return string
     */
    public static function getAreaByRole()
    {
        $user = User::getCurrentUser();
        $roles = User::getRolesFromCurrentUser();
        $areaPath = '/website/views/areas';
        $areaPathAbs = PIMCORE_WEBSITE_PATH . '/views/areas';

        if ($user instanceof User and !$user->isAdmin() and !empty($roles)) {
            foreach ($roles as $rid) {
                $role = PimcoreUser\Role::getById($rid);
                if ($role instanceof PimcoreUser\Role
                    and file_exists($areaPathAbs . DIRECTORY_SEPARATOR . mb_strtolower($role->getName()))
                ) {
                    return $areaPath . DIRECTORY_SEPARATOR . mb_strtolower($role->getName());
                }
            }
        }
        return $areaPath . '/active-wireframe';
    }

    /**
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
        return false;
    }

    /**
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
            $key = File::getValidFilename($key);
            if ($key == "") {
                $key = Translation::get('active_wireframe_page');
            }

            // $key unique
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
            $page = Printpage::create($parentId, $dataDocument);
            Pages::getInstance()->insertPage($page, $catalogId, $configs);
            return $page;
        }
        throw new \Exception("Printconatiner CATALOG is not found");
    }

    /**
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

            // creation du nouveau tag
            $renderlet = new Document\Tag\Renderlet();
            $renderlet->setName($areaId . 'pages-editable' . $index);
            $renderlet->setDataFromEditmode(['id' => $objectId, 'type' => 'object', 'subtype' => 'object']);

            // ajoute le renderlet à la page
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
            return Elements::getInstance()->insert($dataElements);
        }
        return false;
    }

    /**
     * @param AbstractObject $node
     * @return bool
     */
    public static function hasChildsRecursives(AbstractObject $node)
    {
        if ($node->hasChilds()) {
            foreach ($node->getChilds() as $child) {
                if ($child instanceof AbstractObject and $child->hasChilds()) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
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
        return false;
    }

    /**
     * @param $documentId
     * @return array
     */
    public static function getElements($documentId)
    {
        $elements = Elements::getInstance()->getElementsByDocumentId(intval($documentId));
        $collection = [];
        foreach ($elements as $element) {
            $collection[$element['e_key']] = $element;
        }
        return $collection;
    }

}