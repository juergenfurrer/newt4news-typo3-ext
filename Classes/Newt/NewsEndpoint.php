<?php

declare(strict_types=1);

namespace Infonique\Newt4News\Newt;

use DateInterval;
use DateTime;
use GeorgRinger\News\Domain\Model\Category;
use GeorgRinger\News\Domain\Model\News;
use GeorgRinger\News\Domain\Repository\CategoryRepository;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use Infonique\Newt\NewtApi\EndpointInterface;
use Infonique\Newt\NewtApi\Field;
use Infonique\Newt\NewtApi\FieldItem;
use Infonique\Newt\NewtApi\FieldType;
use Infonique\Newt\NewtApi\FieldValidation;
use Infonique\Newt\NewtApi\Item;
use Infonique\Newt\NewtApi\ItemValue;
use Infonique\Newt\NewtApi\MethodCreateModel;
use Infonique\Newt\NewtApi\MethodDeleteModel;
use Infonique\Newt\NewtApi\MethodListModel;
use Infonique\Newt\NewtApi\MethodReadModel;
use Infonique\Newt\NewtApi\MethodType;
use Infonique\Newt\NewtApi\MethodUpdateModel;
use Infonique\Newt\Utility\SlugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class NewsEndpoint implements EndpointInterface
{
    private array $settings;

    private NewsRepository $newsRepository;
    private PersistenceManager $persistenceManager;
    private CategoryRepository $categoryRepository;

    public function __construct(NewsRepository $newsRepository, ConfigurationManager $configurationManager, PersistenceManager $persistenceManager, CategoryRepository $categoryRepository)
    {
        $this->newsRepository = $newsRepository;
        $this->persistenceManager = $persistenceManager;
        $this->categoryRepository = $categoryRepository;

        $conf = $configurationManager->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT);
        $this->settings = $conf['plugin.']['tx_newt4news.']['settings.'] ?? [];
    }

    /**
     * Return the settings of this plugin
     */
    private function getSetting(string $key, string $childKey)
    {
        if ($this->settings && isset($this->settings[$key."."]) && isset($this->settings[$key."."][$childKey])) {
            return $this->settings[$key."."][$childKey];
        }
        return '';
    }

    /**
     * Implement of create
     *
     * @param array $params
     * @return Item
     */
    public function methodCreate(MethodCreateModel $model): Item
    {
        $item = new Item();
        if (! $model || count($model->getParams()) == 0) {
            return $item;
        }

        $params = $model->getParams();

        $news = new News();

        if (boolval($this->getSetting('istopnews', 'active')) && isset($params["istopnews"])) {
            $news->setIstopnews($params["istopnews"]);
        }

        if (boolval($this->getSetting('title', 'active')) && isset($params["title"])) {
            $news->setTitle($params["title"]);
        }

        if (boolval($this->getSetting('teaser', 'active')) && isset($params["teaser"])) {
            $news->setTeaser($params["teaser"]);
        }

        if (boolval($this->getSetting('bodytext', 'active')) && isset($params["bodytext"])) {
            $news->setBodytext($params["bodytext"]);
        }

        if (boolval($this->getSetting('datetime', 'active')) && isset($params["datetime"])) {
            /** @var DateTime */
            $dateTime = $params["datetime"];
            $now = new DateTime();
            if ($dateTime) {
                /** @var DateInterval */
                $age = $dateTime->diff($now);
                if ($age->y > 100) {
                    $dateTime = $now;
                }
                $news->setDatetime($params["datetime"]);
            }
        }

        if (boolval($this->getSetting('archive', 'active')) && isset($params["archive"])) {
            /** @var DateTime */
            $dateTime = $params["archive"];
            $now = new DateTime();
            if ($dateTime) {
                /** @var DateInterval */
                $age = $dateTime->diff($now);
                if ($age->y > 100) {
                    $dateTime = $now;
                }
                $news->setArchive($params["archive"]);
            }
        }

        if (boolval($this->getSetting('image', 'active')) && isset($params["image"])) {
            if ($params["image"] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                /** @var \Infonique\Newt\Domain\Model\FileReference */
                $imageRef = $params["image"];
                /** @var \GeorgRinger\News\Domain\Model\FileReference */
                $fileReference = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                $fileReference->setFileUid($imageRef->getUidLocal());
                if (boolval($this->getSetting('showinpreview', 'active')) && isset($params["showinpreview"])) {
                    $fileReference->setShowinpreview(intval($params["showinpreview"]));
                }
                if (boolval($this->getSetting('imagealt', 'active')) && isset($params["imagealt"])) {
                    $fileReference->setAlternative($params["imagealt"]);
                }
                if (boolval($this->getSetting('imagedesc', 'active')) && isset($params["imagedesc"])) {
                    $fileReference->setDescription($params["imagedesc"]);
                }

                $news->addFalMedia($fileReference);
            }
        }

        if (boolval($this->getSetting('relatedfile', 'active')) && isset($params["relatedfile"])) {
            if ($params["relatedfile"] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                /** @var \Infonique\Newt\Domain\Model\FileReference */
                $imageRef = $params["relatedfile"];
                /** @var \GeorgRinger\News\Domain\Model\FileReference */
                $fileReference = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                $fileReference->setFileUid($imageRef->getUidLocal());
                $news->addFalRelatedFile($fileReference);
            }
        }

        if (boolval($this->getSetting('categories', 'active')) && isset($params["categories"])) {
            $categories = json_decode($params["categories"]);
            if (is_countable($categories)) {
                foreach ($categories as $catId) {
                    $cat = $this->categoryRepository->findByUid($catId);
                    if ($cat) {
                        $news->addCategory($cat);
                    }
                }
            }
        }

        if ($model->getPageUid() > 0) {
            $news->setPid($model->getPageUid());
        }

        // Set News-Type
        $news->setType("0");

        if ($model->getBackendUserUid() > 0) {
            $news->setCruserId($model->getBackendUserUid());
        }

        $this->newsRepository->add($news);

        // persist the item
        $this->persistenceManager->persistAll();

        // Update the Slug
        SlugUtility::populateEmptySlugsInCustomTable('tx_news_domain_model_news', 'path_segment');

        $item->setTitle($news->getTitle());
        $item->setDescription($news->getTeaser());
        foreach ($params as $key => $val) {
            $item->addValue(new ItemValue($key, $val));
        }

        return $item;
    }

    public function methodRead(MethodReadModel $model): Item
    {
        throw new \Exception("Not implemented");
    }

    public function methodUpdate(MethodUpdateModel $model): Item
    {
        throw new \Exception("Not implemented");
    }

    public function methodDelete(MethodDeleteModel $model): bool
    {
        throw new \Exception("Not implemented");
    }

    public function methodList(MethodListModel $model): array
    {
        throw new \Exception("Not implemented");
    }

    /**
     * Returns the available methods
     *
     * @return array
     */
    public function getAvailableMethodTypes(): array
    {
        return [
            MethodType::CREATE
        ];
    }

    /**
     * Returns the available fields
     *
     * @return array
     */
    public function getAvailableFields(): array
    {
        $required = new FieldValidation();
        $required->setRequired(true);

        $ret = [];

        if (boolval($this->getSetting('istopnews', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.istopnews');
            $istopnews = new Field();
            $istopnews->setName("istopnews");
            $istopnews->setLabel($label);
            $istopnews->setType(FieldType::CHECKBOX);
            $ret[] = $istopnews;
        }

        if (boolval($this->getSetting('title', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:header_formlabel');
            $title = new Field();
            $title->setName("title");
            $title->setLabel($label);
            $title->setType(FieldType::TEXT);
            if (boolval($this->getSetting('title', 'required'))) {
                $title->setValidation($required);
            }
            $ret[] = $title;
        }

        if (boolval($this->getSetting('teaser', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.teaser');
            $teaser = new Field();
            $teaser->setName("teaser");
            $teaser->setLabel($label);
            $teaser->setType(FieldType::TEXTAREA);
            if (boolval($this->getSetting('teaser', 'required'))) {
                $teaser->setValidation($required);
            }
            $ret[] = $teaser;
        }

        if (boolval($this->getSetting('bodytext', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext_formlabel');
            $bodytext = new Field();
            $bodytext->setName("bodytext");
            $bodytext->setLabel($label);
            $bodytext->setType(FieldType::TEXTAREA);
            if (boolval($this->getSetting('bodytext', 'required'))) {
                $bodytext->setValidation($required);
            }
            $ret[] = $bodytext;
        }

        if (boolval($this->getSetting('datetime', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.datetime');
            $datetime = new Field();
            $datetime->setName("datetime");
            $datetime->setLabel($label);
            $datetime->setType(FieldType::DATETIME);
            if (boolval($this->getSetting('datetime', 'required'))) {
                $datetime->setValidation($required);
            }
            $ret[] = $datetime;
        }

        if (boolval($this->getSetting('archive', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.archive');
            $archive = new Field();
            $archive->setName("archive");
            $archive->setLabel($label);
            $archive->setType(FieldType::DATETIME);
            if (boolval($this->getSetting('archive', 'required'))) {
                $archive->setValidation($required);
            }
            $ret[] = $archive;
        }

        if (boolval($this->getSetting('image', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.fal_media');
            $image = new Field();
            $image->setName("image");
            $image->setLabel($label);
            $image->setType(FieldType::IMAGE);
            if (boolval($this->getSetting('image', 'required'))) {
                $image->setValidation($required);
            }
            $ret[] = $image;
        }

        if (boolval($this->getSetting('showinpreview', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews');
            $showinpreview = new Field();
            $showinpreview->setName("showinpreview");
            $showinpreview->setLabel($label);
            $showinpreview->setType(FieldType::SELECT);
            $showinpreview->addItem(new FieldItem(0, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.0')));
            $showinpreview->addItem(new FieldItem(1, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.1')));
            $showinpreview->addItem(new FieldItem(2, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.2')));
            $showinpreview->setValue("1");
            $ret[] = $showinpreview;
        }

        if (boolval($this->getSetting('imagealt', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file.alternative');
            $imagealt = new Field();
            $imagealt->setName("imagealt");
            $imagealt->setLabel($label);
            $imagealt->setType(FieldType::TEXT);
            if (boolval($this->getSetting('imagealt', 'required'))) {
                $imagealt->setValidation($required);
            }
            $ret[] = $imagealt;
        }

        if (boolval($this->getSetting('imagedesc', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file.description');
            $imagedesc = new Field();
            $imagedesc->setName("imagedesc");
            $imagedesc->setLabel($label);
            $imagedesc->setType(FieldType::TEXTAREA);
            if (boolval($this->getSetting('imagedesc', 'required'))) {
                $imagedesc->setValidation($required);
            }
            $ret[] = $imagedesc;
        }

        if (boolval($this->getSetting('relatedfile', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.fal_related_files');
            $relatedfile = new Field();
            $relatedfile->setName("relatedfile");
            $relatedfile->setLabel($label);
            $relatedfile->setType(FieldType::FILE);
            if (boolval($this->getSetting('relatedfile', 'required'))) {
                $relatedfile->setValidation($required);
            }
            $ret[] = $relatedfile;
        }

        if (boolval($this->getSetting('categories', 'active'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.categories');
            $categories = new Field();
            $categories->setName("categories");
            $categories->setLabel($label);
            $categories->setType(FieldType::SELECT);
            /** @var Category $category */
            foreach ($this->categoryRepository->findAll() as $category) {
                $categories->addItem(new FieldItem($category->getUid(), $category->getTitle()));
            }
            $categories->setCount(10);
            if (boolval($this->getSetting('categories', 'required'))) {
                $categories->setValidation($required);
            }
            $ret[] = $categories;
        }

        return $ret;
    }
}
