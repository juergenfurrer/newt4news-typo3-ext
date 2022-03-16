<?php

declare(strict_types=1);

namespace Infonique\Newt4News\Newt;

use DateInterval;
use DateTime;
use GeorgRinger\News\Domain\Model\Category;
use GeorgRinger\News\Domain\Model\Dto\NewsDemand;
use GeorgRinger\News\Domain\Model\FileReference;
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
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
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
     * Creats a new news-item
     *
     * @param MethodCreateModel $model
     * @return Item
     */
    public function methodCreate(MethodCreateModel $model): Item
    {
        $item = new Item();
        if (!$model || count($model->getParams()) == 0) {
            return $item;
        }

        $params = $model->getParams();

        $news = new News();

        if (boolval($this->getSetting('istopnews', 'field')) && isset($params["istopnews"])) {
            $news->setIstopnews($params["istopnews"]);
        }

        if (boolval($this->getSetting('title', 'field')) && isset($params["title"])) {
            $news->setTitle($params["title"]);
        }

        if (boolval($this->getSetting('teaser', 'field')) && isset($params["teaser"])) {
            $news->setTeaser($params["teaser"]);
        }

        if (boolval($this->getSetting('bodytext', 'field')) && isset($params["bodytext"])) {
            $news->setBodytext($params["bodytext"]);
        }

        if (boolval($this->getSetting('datetime', 'field')) && isset($params["datetime"])) {
            /** @var DateTime */
            $dateTime = $params["datetime"];
            if (!empty($dateTime)) {
                $now = new DateTime();
                /** @var DateInterval */
                $age = $dateTime->diff($now);
                if ($age->y > 100) {
                    $dateTime = $now;
                }
                $news->setDatetime($params["datetime"]);
            }
        }

        if (boolval($this->getSetting('archive', 'field')) && isset($params["archive"])) {
            /** @var DateTime */
            $archive = $params["archive"];
            if (!empty($archive)) {
                $now = new DateTime();
                /** @var DateInterval */
                $age = $archive->diff($now);
                if ($age->y > 100) {
                    $archive = $now;
                }
                $news->setArchive($params["archive"]);
            }
        }

        if (boolval($this->getSetting('image', 'field')) && isset($params["image"])) {
            if ($params["image"] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                /** @var \Infonique\Newt\Domain\Model\FileReference */
                $imageRef = $params["image"];
                /** @var \GeorgRinger\News\Domain\Model\FileReference */
                $fileReference = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                $fileReference->setFileUid($imageRef->getUidLocal());
                if (boolval($this->getSetting('showinpreview', 'field')) && isset($params["showinpreview"])) {
                    $fileReference->setShowinpreview(intval($params["showinpreview"]));
                }
                if (boolval($this->getSetting('imagealt', 'field')) && isset($params["imagealt"])) {
                    $fileReference->setAlternative($params["imagealt"]);
                }
                if (boolval($this->getSetting('imagedesc', 'field')) && isset($params["imagedesc"])) {
                    $fileReference->setDescription($params["imagedesc"]);
                }

                $news->addFalMedia($fileReference);
            }
        }

        if (boolval($this->getSetting('relatedfile', 'field')) && isset($params["relatedfile"])) {
            if ($params["relatedfile"] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                /** @var \Infonique\Newt\Domain\Model\FileReference */
                $imageRef = $params["relatedfile"];
                /** @var \GeorgRinger\News\Domain\Model\FileReference */
                $fileReference = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                $fileReference->setFileUid($imageRef->getUidLocal());
                $news->addFalRelatedFile($fileReference);
            }
        }

        if (boolval($this->getSetting('categories', 'field')) && isset($params["categories"])) {
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

        // Update the empty Slugs
        SlugUtility::populateEmptySlugsInCustomTable('tx_news_domain_model_news', 'path_segment');

        $item->setId(strval($news->getUid()));
        $item->setTitle($news->getTitle());
        $item->setDescription($news->getTeaser());

        // Add values if no read-method is set
        if (!in_array(MethodType::READ, $this->getAvailableMethodTypes())) {
            foreach ($params as $key => $val) {
                $item->addValue(new ItemValue($key, $val));
            }
        }

        return $item;
    }

    /**
     * Read a news-item by readId
     *
     * @param MethodReadModel $model
     * @return Item
     */
    public function methodRead(MethodReadModel $model): Item
    {
        $id = $model->getReadId();

        $item = new Item();
        $item->setId($id);

        /** @var News */
        $news = $this->newsRepository->findByUid(intval($id));
        if ($news) {

            if (boolval($this->getSetting('istopnews', 'field'))) {
                $item->addValue(new ItemValue("istopnews", $news->getIstopnews()));
            }

            if (boolval($this->getSetting('title', 'field'))) {
                $item->addValue(new ItemValue("title", $news->getTitle()));
            }

            if (boolval($this->getSetting('teaser', 'field'))) {
                $item->addValue(new ItemValue("teaser", $news->getTeaser()));
            }

            if (boolval($this->getSetting('bodytext', 'field'))) {
                $item->addValue(new ItemValue("bodytext", $news->getBodytext()));
            }

            if (boolval($this->getSetting('datetime', 'field'))) {
                $datetime = $news->getDatetime();
                if ($datetime) {
                    $item->addValue(new ItemValue("datetime", $datetime));
                }
            }

            if (boolval($this->getSetting('archive', 'field'))) {
                $archive = $news->getArchive();
                if ($archive) {
                    $item->addValue(new ItemValue("archive", $archive));
                }
            }

            if (boolval($this->getSetting('image', 'field'))) {
                $falMedia = null;
                if ($news->getFalMedia()) {
                    foreach ($news->getFalMedia() as $mediaItem) {
                        if (!$falMedia) {
                            /** @var FileReference */
                            $falMedia = $mediaItem;
                        }
                    }
                }
                if ($falMedia) {
                    $storageId = $falMedia->getOriginalResource()->getProperty('storage');
                    $identifier = $falMedia->getOriginalResource()->getProperty('identifier');
                    /** @var StorageRepository */
                    $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
                    if ($storageRepository) {
                        /** @var ResourceStorage */
                        $storage = $storageRepository->findByUid($storageId);
                        $file = $storage->getFile($identifier);
                        if ($file) {
                            $fileContent = $file->getContents();
                            $item->addValue(new ItemValue("imageUid", strval($falMedia->getUid())));
                            $item->addValue(new ItemValue("image", base64_encode($fileContent)));
                        }
                    }

                    if (boolval($this->getSetting('showinpreview', 'field'))) {
                        $showinpreview = $falMedia->getOriginalResource()->getProperty('showinpreview');
                        $item->addValue(new ItemValue("showinpreview", $showinpreview));
                    }

                    if (boolval($this->getSetting('imagealt', 'field'))) {
                        $alternative = $falMedia->getOriginalResource()->getProperty('alternative');
                        $item->addValue(new ItemValue("imagealt", $alternative));
                    }

                    if (boolval($this->getSetting('imagedesc', 'field'))) {
                        $description = $falMedia->getOriginalResource()->getProperty('description');
                        $item->addValue(new ItemValue("imagedesc", $description));
                    }
                }
            }

            if (boolval($this->getSetting('relatedfile', 'field'))) {
                $falRelatedFiles = null;
                if ($news->getFalRelatedFiles()) {
                    foreach ($news->getFalRelatedFiles() as $relatedFiles) {
                        if (!$falRelatedFiles) {
                            /** @var FileReference */
                            $falRelatedFiles = $relatedFiles;
                        }
                    }
                }
                if ($falRelatedFiles) {
                    $storageId = $falRelatedFiles->getOriginalResource()->getProperty('storage');
                    $identifier = $falRelatedFiles->getOriginalResource()->getProperty('identifier');
                    /** @var StorageRepository */
                    $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
                    if ($storageRepository) {
                        /** @var ResourceStorage */
                        $storage = $storageRepository->findByUid($storageId);
                        $file = $storage->getFile($identifier);
                        if ($file) {
                            $fileContent = $file->getContents();
                            $item->addValue(new ItemValue("relatedfileUid", strval($falRelatedFiles->getUid())));
                            $item->addValue(new ItemValue("relatedfile", base64_encode($fileContent)));
                        }
                    }
                }
            }

            if (boolval($this->getSetting('categories', 'field'))) {
                $values = [];
                $categories = $news->getCategories();
                foreach ($categories as $category) {
                    $values[] = $category->getuid();
                }
                $item->addValue(new ItemValue("categories", json_encode($values)));
            }
        }

        return $item;
    }

    /**
     * Update the news-entry with the new data
     *
     * @param MethodUpdateModel $model
     * @return Item
     */
    public function methodUpdate(MethodUpdateModel $model): Item
    {
        $item = new Item();
        if (!$model || count($model->getParams()) == 0) {
            return $item;
        }

        $params = $model->getParams();
        $id = intval($model->getUpdateId());
        $news = $this->newsRepository->findByUid($id);
        if (!$news) {
            return $item;
        }

        if (boolval($this->getSetting('istopnews', 'field')) && isset($params["istopnews"])) {
            $news->setIstopnews($params["istopnews"]);
        }

        if (boolval($this->getSetting('title', 'field')) && isset($params["title"])) {
            if ($params["title"] != $news->getTitle()) {
                $news->setPathSegment("");
            }
            $news->setTitle($params["title"]);
        }

        if (boolval($this->getSetting('teaser', 'field')) && isset($params["teaser"])) {
            $news->setTeaser($params["teaser"]);
        }

        if (boolval($this->getSetting('bodytext', 'field')) && isset($params["bodytext"])) {
            $news->setBodytext($params["bodytext"]);
        }

        if (boolval($this->getSetting('datetime', 'field')) && isset($params["datetime"])) {
            /** @var DateTime */
            $dateTime = $params["datetime"];
            if (!empty($dateTime)) {
                $now = new DateTime();
                /** @var DateInterval */
                $age = $dateTime->diff($now);
                if ($age->y > 100) {
                    $dateTime = $now;
                }
                $news->setDatetime($params["datetime"]);
            }
        }

        if (boolval($this->getSetting('archive', 'field')) && isset($params["archive"])) {
            /** @var DateTime */
            $archive = $params["archive"];
            if (!empty($archive)) {
                $now = new DateTime();
                /** @var DateInterval */
                $age = $archive->diff($now);
                if ($age->y > 100) {
                    $archive = $now;
                }
                $news->setArchive($params["archive"]);
            }
        }

        if (boolval($this->getSetting('image', 'field'))) {
            $falMedias = $news->getFalMedia();
            /** @var \GeorgRinger\News\Domain\Model\FileReference */
            $usedMedia = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
            $isNew = true;
            if (isset($params["imageUid"]) && intval($params["imageUid"]) > 0) {
                foreach ($falMedias as $falMedia) {
                    if ($falMedia->getUid() == intval($params["imageUid"])) {
                        /** @var \GeorgRinger\News\Domain\Model\FileReference */
                        $usedMedia = $falMedia;
                        $isNew = false;
                        continue;
                    }
                }
            }
            if (isset($params["image"]) && $params["image"] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                /** @var \Infonique\Newt\Domain\Model\FileReference */
                $imageRef = $params["image"];

                $usedMedia->setFileUid($imageRef->getUidLocal());
                if (boolval($this->getSetting('showinpreview', 'field')) && isset($params["showinpreview"])) {
                    $usedMedia->setShowinpreview(intval($params["showinpreview"]));
                }
                if (boolval($this->getSetting('imagealt', 'field')) && isset($params["imagealt"])) {
                    $usedMedia->setAlternative($params["imagealt"]);
                }
                if (boolval($this->getSetting('imagedesc', 'field')) && isset($params["imagedesc"])) {
                    $usedMedia->setDescription($params["imagedesc"]);
                }

                if ($isNew) {
                    $news->addFalMedia($usedMedia);
                }
            } else if (isset($params["imageUid"]) && intval($params["imageUid"]) > 0 && ! $isNew) {
                // Remove image
                $falMedias->detach($usedMedia);
            }
        }

        if (boolval($this->getSetting('relatedfile', 'field'))) {
            $falFiles = $news->getFalRelatedFiles();
            /** @var \GeorgRinger\News\Domain\Model\FileReference */
            $usedFile = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
            $isNew = true;
            if (isset($params["relatedfileUid"]) && intval($params["relatedfileUid"]) > 0) {
                foreach ($falFiles as $falFile) {
                    if ($falFile->getUid() == intval($params["relatedfileUid"])) {
                        /** @var \GeorgRinger\News\Domain\Model\FileReference */
                        $usedFile = $falFile;
                        $isNew = false;
                        continue;
                    }
                }
            }
            if (isset($params["relatedfile"]) && $params["relatedfile"] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                /** @var \Infonique\Newt\Domain\Model\FileReference */
                $fileRef = $params["relatedfile"];
                $usedFile->setFileUid($fileRef->getUidLocal());
                if ($isNew) {
                    $news->addFalRelatedFile($usedFile);
                }
            } else if (isset($params["relatedfileUid"]) && intval($params["relatedfileUid"]) > 0 && ! $isNew) {
                // Remove File
                $falFiles->detach($usedFile);
            }
        }

        if (boolval($this->getSetting('categories', 'field')) && isset($params["categories"])) {
            // Remove the categories
            $news->setCategories(new ObjectStorage());
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

        if ($model->getBackendUserUid() > 0) {
            $news->setCruserId($model->getBackendUserUid());
        }

        $this->newsRepository->update($news);

        // persist the item
        $this->persistenceManager->persistAll();

        // Update the empty Slugs
        SlugUtility::populateEmptySlugsInCustomTable('tx_news_domain_model_news', 'path_segment');

        $item->setId(strval($news->getUid()));
        $item->setTitle($news->getTitle());
        $item->setDescription($news->getTeaser());

        // Add values if no read-method is set
        if (!in_array(MethodType::READ, $this->getAvailableMethodTypes())) {
            foreach ($params as $key => $val) {
                $item->addValue(new ItemValue($key, $val));
            }
        }

        return $item;
    }

    /**
     * Deletes a news-item by deleteId
     *
     * @param MethodDeleteModel $model
     * @return boolean
     */
    public function methodDelete(MethodDeleteModel $model): bool
    {
        try {
            $id = intval($model->getDeleteId());
            $news = $this->newsRepository->findByUid($id);
            $this->newsRepository->remove($news);

            // persist the item
            $this->persistenceManager->persistAll();
        } catch (\Throwable $th) {
            return false;
        }

        return true;
    }

    /**
     * Returns a list of list-items
     *
     * @param MethodListModel $model
     * @return array
     */
    public function methodList(MethodListModel $model): array
    {
        $items = [];

        $pageSize = $model->getPageSize();

        $startUid = 0;
        if (intval($model->getLastKnownItemId()) > 0) {
            $startUid = intval($model->getLastKnownItemId());
        }

        /** @var NewsDemand */
        $demand = GeneralUtility::makeInstance(NewsDemand::class);

        $orderField = $this->getSetting('orderField', 'list');
        $orderDirection = $this->getSetting('orderDirection', 'list');
        if (!empty($orderField) && !empty($orderDirection)) {
            $demand->setOrder("{$orderField} {$orderDirection}");
            $demand->setOrderByAllowed("{$orderField},{$orderField} {$orderDirection}");
        }

        if ($model->getPageUid() > 0) {
            $demand->setStoragePage(strval($model->getPageUid()));
        }

        $news = $this->newsRepository->findDemanded($demand)->toArray();

        $useItems = $startUid < 1;
        /** @var News $newsItem */
        foreach ($news as $newsItem) {
            if ($useItems) {
                $item = new Item();
                $item->setId(strval($newsItem->getUid()));
                $item->setTitle(strval($newsItem->getTitle()));
                $item->setDescription(strval($newsItem->getTeaser()));
                $items[] = $item;
            }
            if ($startUid == 0 || $newsItem->getUid() == $startUid) {
                $useItems = true;
            }
            if ($pageSize > 0 && count($items) >= $pageSize) {
                $useItems = false;
            }
        }

        return $items;
    }

    /**
     * Returns the available methods
     *
     * @return array
     */
    public function getAvailableMethodTypes(): array
    {
        return [
            MethodType::CREATE,
            MethodType::READ,
            MethodType::UPDATE,
            MethodType::DELETE,
            MethodType::LIST,
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

        if (boolval($this->getSetting('istopnews', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.istopnews');
            $istopnews = new Field();
            $istopnews->setName("istopnews");
            $istopnews->setLabel($label);
            $istopnews->setType(FieldType::CHECKBOX);
            $ret[] = $istopnews;
        }

        if (boolval($this->getSetting('title', 'field'))) {
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

        if (boolval($this->getSetting('teaser', 'field'))) {
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

        if (boolval($this->getSetting('bodytext', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext_formlabel');
            $bodytext = new Field();
            $bodytext->setName("bodytext");
            $bodytext->setLabel($label);
            if (boolval($this->getSetting('useHtml', 'options'))) {
                $bodytext->setType(FieldType::HTML);
            } else {
                $bodytext->setType(FieldType::TEXTAREA);
            }
            if (boolval($this->getSetting('bodytext', 'required'))) {
                $bodytext->setValidation($required);
            }
            $ret[] = $bodytext;
        }

        if (boolval($this->getSetting('datetime', 'field'))) {
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

        if (boolval($this->getSetting('archive', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.archive');
            $archive = new Field();
            $archive->setName("archive");
            $archive->setLabel($label);
            $archive->setType(FieldType::DATE);
            if (boolval($this->getSetting('archive', 'required'))) {
                $archive->setValidation($required);
            }
            $ret[] = $archive;
        }

        if (boolval($this->getSetting('image', 'field'))) {
            $divider = new Field();
            $divider->setType(FieldType::DIVIDER);

            if (count($ret) > 0) {
                // Add the divider
                $ret[] = $divider;
            }

            $imageUid = new Field();
            $imageUid->setName("imageUid");
            $imageUid->setType(FieldType::HIDDEN);
            $ret[] = $imageUid;

            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.fal_media');
            $image = new Field();
            $image->setName("image");
            $image->setLabel($label);
            $image->setType(FieldType::IMAGE);
            if (boolval($this->getSetting('image', 'required'))) {
                $image->setValidation($required);
            }
            $ret[] = $image;

            if (boolval($this->getSetting('showinpreview', 'field'))) {
                $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews');
                $showinpreview = new Field();
                $showinpreview->setName("showinpreview");
                $showinpreview->setLabel($label);
                $showinpreview->setType(FieldType::SELECT);
                $showinpreview->addItem(new FieldItem(0, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.0')));
                $showinpreview->addItem(new FieldItem(1, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.1')));
                $showinpreview->addItem(new FieldItem(2, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.2')));
                $default = $this->getSetting('showinpreview', 'value');
                $showinpreview->setValue($default);
                $ret[] = $showinpreview;
            }

            if (boolval($this->getSetting('imagealt', 'field'))) {
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

            if (boolval($this->getSetting('imagedesc', 'field'))) {
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

            // Add the divider
            $ret[] = $divider;
        }


        if (boolval($this->getSetting('relatedfile', 'field'))) {
            $relatedfileUid = new Field();
            $relatedfileUid->setName("relatedfileUid");
            $relatedfileUid->setType(FieldType::HIDDEN);
            $ret[] = $relatedfileUid;

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

        if (boolval($this->getSetting('categories', 'field'))) {
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


    /**
     * Return the settings of this plugin
     */
    private function getSetting(string $key, string $type)
    {
        if ($this->settings && isset($this->settings[$type . "."]) && isset($this->settings[$type . "."][$key])) {
            return $this->settings[$type . "."][$key];
        }
        return '';
    }
}
