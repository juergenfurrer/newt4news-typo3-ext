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
use Infonique\Newt\NewtApi\LabelColor;
use Infonique\Newt\NewtApi\MethodCreateModel;
use Infonique\Newt\NewtApi\MethodDeleteModel;
use Infonique\Newt\NewtApi\MethodListModel;
use Infonique\Newt\NewtApi\MethodReadModel;
use Infonique\Newt\NewtApi\MethodType;
use Infonique\Newt\NewtApi\MethodUpdateModel;
use Infonique\Newt\Utility\SlugUtility;
use Infonique\Newt\Utility\Utils;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
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
    private array $settingsNews;
    private int $maxImageCount = 6;
    private int $maxFileCount = 6;

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

        try {
            /** @var ExtensionConfiguration */
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            $this->settingsNews = $extensionConfiguration->get('news');
        } catch (\Exception $exception) {
            // do nothing
        }
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
            $date = $params["datetime"];
            if (!empty($date)) {
                $news->setDatetime($this->getSanityDate($date));
            }
        }

        if (boolval($this->getSetting('archive', 'field')) && isset($params["archive"])) {
            /** @var DateTime */
            $date = $params["archive"];
            if (!empty($date)) {
                $news->setArchive($this->getSanityDate($date));
            }
        }

        $imgCount = min($this->maxImageCount, intval($this->getSetting('image', 'field')));
        if ($imgCount > 0) {
            for ($i = 0; $i < $imgCount; $i++) {
                $imgNum = $i > 0 ? $i : "";
                $paramImage = "image{$imgNum}";
                $paramShowinpreview = "showinpreview{$imgNum}";
                $paramImagealt = "imagealt{$imgNum}";
                $paramImagedesc = "imagedesc{$imgNum}";
                if (isset($params[$paramImage]) && $params[$paramImage] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                    /** @var \Infonique\Newt\Domain\Model\FileReference */
                    $imageRef = $params[$paramImage];
                    /** @var \GeorgRinger\News\Domain\Model\FileReference */
                    $fileReference = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                    $fileReference->setFileUid($imageRef->getUidLocal());
                    if (intval($this->getSetting('showinpreview', 'field')) > $i && isset($params[$paramShowinpreview])) {
                        $fileReference->setShowinpreview(intval($params[$paramShowinpreview]));
                    }
                    if (intval($this->getSetting('imagealt', 'field')) > $i && isset($params[$paramImagealt])) {
                        $fileReference->setAlternative($params[$paramImagealt]);
                    }
                    if (intval($this->getSetting('imagedesc', 'field')) > $i && isset($params[$paramImagedesc])) {
                        $fileReference->setDescription($params[$paramImagedesc]);
                    }

                    $news->addFalMedia($fileReference);
                }
            }
        }

        $relatedfileCount = min($this->maxFileCount, intval($this->getSetting('relatedfile', 'field')));
        if ($relatedfileCount > 0) {
            for ($i = 0; $i < $relatedfileCount; $i++) {
                $fileNum = $i > 0 ? $i : "";
                $paramRelatedfile = "relatedfile{$fileNum}";
                if (isset($params[$paramRelatedfile]) && $params[$paramRelatedfile] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                    /** @var \Infonique\Newt\Domain\Model\FileReference */
                    $imageRef = $params[$paramRelatedfile];
                    /** @var \GeorgRinger\News\Domain\Model\FileReference */
                    $fileReference = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                    $fileReference->setFileUid($imageRef->getUidLocal());
                    $news->addFalRelatedFile($fileReference);
                }
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

        if (boolval($this->getSetting('hidden', 'field')) && isset($params["hidden"])) {
            $news->setHidden($params["hidden"]);
        }

        if (boolval($this->getSetting('starttime', 'field')) && isset($params["starttime"])) {
            /** @var DateTime */
            $date = $params["starttime"];
            if (!empty($date)) {
                $news->setStarttime($this->getSanityDate($date));
            }
        }

        if (boolval($this->getSetting('endtime', 'field')) && isset($params["endtime"])) {
            /** @var DateTime */
            $date = $params["endtime"];
            if (!empty($date)) {
                $news->setEndtime($this->getSanityDate($date));
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
        if ($news->getHidden()) {
            $hiddenLabel = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden');
            $item->setLabel($hiddenLabel ?? ' ');
            $item->setLabelColor(LabelColor::DANGER);
        } else if ($news->getIstopnews()) {
            $topLabel = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.istopnews');
            $item->setLabel($topLabel ?? ' ');
            $item->setLabelColor(LabelColor::SUCCESS);
        }

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
        $news = $this->newsRepository->findByUid(intval($id), false);
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
                $date = $news->getDatetime();
                if ($date) {
                    $item->addValue(new ItemValue("datetime", $date));
                }
            }

            if (boolval($this->getSetting('archive', 'field'))) {
                $date = $news->getArchive();
                if ($date) {
                    $item->addValue(new ItemValue("archive", $date));
                }
            }

            $imgCount = min($this->maxImageCount, intval($this->getSetting('image', 'field')));
            if ($imgCount > 0) {
                for ($i = 0; $i < $imgCount; $i++) {
                    $imgNum = $i > 0 ? $i : "";
                    $paramImage = "image{$imgNum}";
                    $paramImageUid = "image{$imgNum}Uid";
                    $paramShowinpreview = "showinpreview{$imgNum}";
                    $paramImagealt = "imagealt{$imgNum}";
                    $paramImagedesc = "imagedesc{$imgNum}";
                    $falMedia = null;
                    if ($news->getFalMedia()) {
                        $k = 0;
                        foreach ($news->getFalMedia() as $mediaItem) {
                            if (!$falMedia && $k == $i) {
                                /** @var FileReference */
                                $falMedia = $mediaItem;
                            }
                            $k++;
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
                                $item->addValue(new ItemValue($paramImageUid, strval($falMedia->getUid())));
                                $item->addValue(new ItemValue($paramImage, base64_encode($fileContent)));
                            }
                        }

                        if (intval($this->getSetting('showinpreview', 'field')) > $i) {
                            $showinpreview = $falMedia->getOriginalResource()->getProperty('showinpreview');
                            $item->addValue(new ItemValue($paramShowinpreview, $showinpreview));
                        }

                        if (intval($this->getSetting('imagealt', 'field')) > $i) {
                            $alternative = $falMedia->getOriginalResource()->getProperty('alternative');
                            $item->addValue(new ItemValue($paramImagealt, $alternative));
                        }

                        if (intval($this->getSetting('imagedesc', 'field')) > $i) {
                            $description = $falMedia->getOriginalResource()->getProperty('description');
                            $item->addValue(new ItemValue($paramImagedesc, $description));
                        }
                    }
                }
            }

            $relatedfileCount = min($this->maxFileCount, intval($this->getSetting('relatedfile', 'field')));
            if ($relatedfileCount > 0) {
                for ($i = 0; $i < $relatedfileCount; $i++) {
                    $fileNum = $i > 0 ? $i : "";
                    $paramRelatedfile = "relatedfile{$fileNum}";
                    $paramRelatedfileUid = "relatedfile{$fileNum}Uid";
                    $falRelatedFiles = null;
                    if ($news->getFalRelatedFiles()) {
                        $k = 0;
                        foreach ($news->getFalRelatedFiles() as $relatedFiles) {
                            if (!$falRelatedFiles && $k == $i) {
                                /** @var FileReference */
                                $falRelatedFiles = $relatedFiles;
                            }
                            $k++;
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
                                $item->addValue(new ItemValue($paramRelatedfileUid, strval($falRelatedFiles->getUid())));
                                $item->addValue(new ItemValue($paramRelatedfile, base64_encode($fileContent)));
                            }
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

            if (boolval($this->getSetting('hidden', 'field'))) {
                $item->addValue(new ItemValue("hidden", $news->getHidden()));
            }

            if (boolval($this->getSetting('starttime', 'field'))) {
                $date = $news->getStarttime();
                if ($date) {
                    $item->addValue(new ItemValue("starttime", $date));
                }
            }

            if (boolval($this->getSetting('endtime', 'field'))) {
                $date = $news->getEndtime();
                if ($date) {
                    $item->addValue(new ItemValue("endtime", $date));
                }
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
        $news = $this->newsRepository->findByUid($id, false);
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
            $date = $params["datetime"];
            if (!empty($date)) {
                $news->setDatetime($this->getSanityDate($date));
            }
        }

        if (boolval($this->getSetting('archive', 'field')) && isset($params["archive"])) {
            /** @var DateTime */
            $date = $params["archive"];
            if (!empty($date)) {
                $news->setArchive($this->getSanityDate($date));
            }
        }

        $imgCount = min($this->maxImageCount, intval($this->getSetting('image', 'field')));
        if ($imgCount > 0) {
            $falMedias = $news->getFalMedia();
            for ($i = 0; $i < $imgCount; $i++) {
                $imgNum = $i > 0 ? $i : "";
                $paramImage = "image{$imgNum}";
                $paramImageUid = "image{$imgNum}Uid";
                $paramShowinpreview = "showinpreview{$imgNum}";
                $paramImagealt = "imagealt{$imgNum}";
                $paramImagedesc = "imagedesc{$imgNum}";
                /** @var \GeorgRinger\News\Domain\Model\FileReference */
                $usedMedia = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                $isNew = true;
                if (isset($params[$paramImageUid]) && intval($params[$paramImageUid]) > 0) {
                    foreach ($falMedias as $falMedia) {
                        if ($falMedia->getUid() == intval($params[$paramImageUid])) {
                            /** @var \GeorgRinger\News\Domain\Model\FileReference */
                            $usedMedia = $falMedia;
                            $isNew = false;
                            continue;
                        }
                    }
                }
                if (isset($params[$paramImage]) && $params[$paramImage] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                    /** @var \Infonique\Newt\Domain\Model\FileReference */
                    $imageRef = $params[$paramImage];

                    $usedMedia->setFileUid($imageRef->getUidLocal());
                    if (intval($this->getSetting('showinpreview', 'field')) > $i && isset($params[$paramShowinpreview])) {
                        $usedMedia->setShowinpreview(intval($params[$paramShowinpreview]));
                    }
                    if (intval($this->getSetting('imagealt', 'field')) > $i && isset($params[$paramImagealt])) {
                        $usedMedia->setAlternative($params[$paramImagealt]);
                    }
                    if (intval($this->getSetting('imagedesc', 'field')) > $i && isset($params[$paramImagedesc])) {
                        $usedMedia->setDescription($params[$paramImagedesc]);
                    }

                    if ($isNew) {
                        $news->addFalMedia($usedMedia);
                    }
                } else if (isset($params[$paramImageUid]) && intval($params[$paramImageUid]) > 0 && !$isNew) {
                    // Remove image
                    $falMedias->detach($usedMedia);
                }
            }
        }

        $relatedfileCount = min($this->maxFileCount, intval($this->getSetting('relatedfile', 'field')));
        if ($relatedfileCount > 0) {
            $falFiles = $news->getFalRelatedFiles();
            for ($i = 0; $i < $relatedfileCount; $i++) {
                $fileNum = $i > 0 ? $i : "";
                $paramRelatedfile = "relatedfile{$fileNum}";
                $paramRelatedfileUid = "relatedfile{$fileNum}Uid";
                /** @var \GeorgRinger\News\Domain\Model\FileReference */
                $usedFile = GeneralUtility::makeInstance(\GeorgRinger\News\Domain\Model\FileReference::class);
                $isNew = true;
                if (isset($params[$paramRelatedfileUid]) && intval($params[$paramRelatedfileUid]) > 0) {
                    foreach ($falFiles as $falFile) {
                        if ($falFile->getUid() == intval($params[$paramRelatedfileUid])) {
                            /** @var \GeorgRinger\News\Domain\Model\FileReference */
                            $usedFile = $falFile;
                            $isNew = false;
                            continue;
                        }
                    }
                }
                if (isset($params[$paramRelatedfile]) && $params[$paramRelatedfile] instanceof \Infonique\Newt\Domain\Model\FileReference) {
                    /** @var \Infonique\Newt\Domain\Model\FileReference */
                    $fileRef = $params[$paramRelatedfile];
                    $usedFile->setFileUid($fileRef->getUidLocal());
                    if ($isNew) {
                        $news->addFalRelatedFile($usedFile);
                    }
                } else if (isset($params[$paramRelatedfileUid]) && intval($params[$paramRelatedfileUid]) > 0 && !$isNew) {
                    // Remove File
                    $falFiles->detach($usedFile);
                }
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

        if (boolval($this->getSetting('hidden', 'field')) && isset($params["hidden"])) {
            $news->setHidden($params["hidden"]);
        }

        if (boolval($this->getSetting('starttime', 'field')) && isset($params["starttime"])) {
            /** @var DateTime */
            $date = $params["starttime"];
            if (!empty($date)) {
                $news->setStarttime($this->getSanityDate($date));
            }
        }

        if (boolval($this->getSetting('endtime', 'field')) && isset($params["endtime"])) {
            /** @var DateTime */
            $date = $params["endtime"];
            if (!empty($date)) {
                $news->setEndtime($this->getSanityDate($date));
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
        if ($news->getHidden()) {
            $hiddenLabel = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden');
            $item->setLabel($hiddenLabel ?? ' ');
            $item->setLabelColor(LabelColor::DANGER);
        } else if ($news->getIstopnews()) {
            $topLabel = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.istopnews');
            $item->setLabel($topLabel ?? ' ');
            $item->setLabelColor(LabelColor::SUCCESS);
        }

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
            $news = $this->newsRepository->findByUid($id, false);
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

        // Only normal news are allowed
        $demand->setTypes([0]);

        $orderField = $this->getSetting('orderField', 'list');
        $orderDirection = $this->getSetting('orderDirection', 'list');
        if (!empty($orderField) && !empty($orderDirection)) {
            $demand->setOrder("{$orderField} {$orderDirection}");
            $demand->setOrderByAllowed("{$orderField},{$orderField} {$orderDirection}");
        }

        if ($model->getPageUid() > 0) {
            $demand->setStoragePage(strval($model->getPageUid()));
        }

        $news = $this->newsRepository->findDemanded($demand, false)->toArray();
        $hiddenLabel = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden');
        $topLabel = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.istopnews');

        $useItems = $startUid < 1;
        /** @var News $newsItem */
        foreach ($news as $newsItem) {
            if ($useItems) {
                $item = new Item();
                $item->setId(strval($newsItem->getUid()));
                $item->setTitle(trim($newsItem->getTitle()));
                $item->setDescription(trim(strip_tags($newsItem->getTeaser())));
                if ($newsItem->getHidden()) {
                    $item->setLabel($hiddenLabel ?? ' ');
                    $item->setLabelColor(LabelColor::DANGER);
                } else if ($newsItem->getIstopnews()) {
                    $item->setLabel($topLabel ?? ' ');
                    $item->setLabelColor(LabelColor::SUCCESS);
                }
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
            $istopnews->setLabel($label ?? '');
            $istopnews->setType(FieldType::CHECKBOX);
            $ret[] = $istopnews;
        }

        if (boolval($this->getSetting('title', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:header_formlabel');
            $title = new Field();
            $title->setName("title");
            $title->setLabel($label ?? '');
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
            $teaser->setLabel($label ?? '');
            if (boolval($this->getNewsSetting("rteForTeaser"))) {
                $teaser->setType(FieldType::HTML);
            } else {
                $teaser->setType(FieldType::TEXTAREA);
            }
            if (boolval($this->getSetting('teaser', 'required'))) {
                $teaser->setValidation($required);
            }
            $ret[] = $teaser;
        }

        if (boolval($this->getSetting('bodytext', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:bodytext_formlabel');
            $bodytext = new Field();
            $bodytext->setName("bodytext");
            $bodytext->setLabel($label ?? '');
            $bodytext->setType(FieldType::HTML);
            if (boolval($this->getSetting('bodytext', 'required'))) {
                $bodytext->setValidation($required);
            }
            $ret[] = $bodytext;
        }

        if (boolval($this->getSetting('datetime', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.datetime');
            $date = new Field();
            $date->setName("datetime");
            $date->setLabel($label ?? '');
            $date->setType(FieldType::DATETIME);
            $notRequiredOverride = Utils::isTrue($this->getNewsSetting("dateTimeNotRequired"));
            if (boolval($this->getSetting('datetime', 'required')) && !$notRequiredOverride) {
                $date->setValidation($required);
            }
            $ret[] = $date;
        }

        if (boolval($this->getSetting('archive', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.archive');
            $date = new Field();
            $date->setName("archive");
            $date->setLabel($label ?? '');
            if ($this->getNewsSetting("archiveDate") == "datetime") {
                $date->setType(FieldType::DATETIME);
            } else {
                $date->setType(FieldType::DATE);
            }
            if (boolval($this->getSetting('archive', 'required'))) {
                $date->setValidation($required);
            }
            $ret[] = $date;
        }

        $imgCount = min($this->maxImageCount, intval($this->getSetting('image', 'field')));
        if ($imgCount > 0) {
            $divider = new Field();
            $divider->setType(FieldType::DIVIDER);

            $imgReq = intval($this->getSetting('image', 'required'));
            for ($i = 0; $i < $imgCount; $i++) {
                if (count($ret) > 0) {
                    // Add the divider
                    $ret[] = $divider;
                }

                $imgNum = $i > 0 ? $i : "";
                $paramImage = "image{$imgNum}";
                $paramImageUid = "image{$imgNum}Uid";
                $imageUid = new Field();
                $imageUid->setName($paramImageUid);
                $imageUid->setType(FieldType::HIDDEN);
                $ret[] = $imageUid;

                $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.fal_media');
                $image = new Field();
                $image->setName($paramImage);
                $image->setLabel($label ?? '');
                $image->setType(FieldType::IMAGE);
                if ($imgReq > $i) {
                    $image->setValidation($required);
                }
                $ret[] = $image;

                $showinpreviewCount = intval($this->getSetting('showinpreview', 'field'));
                if ($showinpreviewCount > $i) {
                    $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews');
                    $showinpreview = new Field();
                    $showinpreview->setName("showinpreview{$imgNum}");
                    $showinpreview->setLabel($label ?? '');
                    $showinpreview->setType(FieldType::SELECT);
                    $showinpreview->addItem(new FieldItem(0, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.0')));
                    $showinpreview->addItem(new FieldItem(1, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.1')));
                    $showinpreview->addItem(new FieldItem(2, LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_media.showinviews.2')));
                    $default = $this->getSetting('showinpreview', 'value');
                    $showinpreview->setValue($default);
                    $ret[] = $showinpreview;
                }

                $imagealtCount = intval($this->getSetting('imagealt', 'field'));
                if ($imagealtCount > $i) {
                    $label = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file.alternative');
                    $imagealt = new Field();
                    $imagealt->setName("imagealt{$imgNum}");
                    $imagealt->setLabel($label ?? '');
                    $imagealt->setType(FieldType::TEXT);
                    if (intval($this->getSetting('imagealt', 'required')) > $i) {
                        $imagealt->setValidation($required);
                    }
                    $ret[] = $imagealt;
                }

                $imagedescCount = intval($this->getSetting('imagedesc', 'field'));
                if ($imagedescCount > $i) {
                    $label = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_tca.xlf:sys_file.description');
                    $imagedesc = new Field();
                    $imagedesc->setName("imagedesc{$imgNum}");
                    $imagedesc->setLabel($label ?? '');
                    $imagedesc->setType(FieldType::TEXTAREA);
                    if (intval($this->getSetting('imagedesc', 'required')) > $i) {
                        $imagedesc->setValidation($required);
                    }
                    $ret[] = $imagedesc;
                }
            }
            // Add the divider
            $ret[] = $divider;
        }

        $relatedfileCount = min($this->maxFileCount, intval($this->getSetting('relatedfile', 'field')));
        if ($relatedfileCount > 0) {
            for ($i = 0; $i < $relatedfileCount; $i++) {
                $imgNum = $i > 0 ? $i : "";

                $relatedfileUid = new Field();
                $relatedfileUid->setName("relatedfile{$imgNum}Uid");
                $relatedfileUid->setType(FieldType::HIDDEN);
                $ret[] = $relatedfileUid;

                $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.fal_related_files');
                $relatedfile = new Field();
                $relatedfile->setName("relatedfile{$imgNum}");
                $relatedfile->setLabel($label ?? '');
                $relatedfile->setType(FieldType::FILE);
                if (intval($this->getSetting('relatedfile', 'required')) > $i) {
                    $relatedfile->setValidation($required);
                }
                $ret[] = $relatedfile;
            }
        }

        if (boolval($this->getSetting('categories', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:news/Resources/Private/Language/locallang_db.xlf:tx_news_domain_model_news.categories');
            $categories = new Field();
            $categories->setName("categories");
            $categories->setLabel($label ?? '');
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

        // Add the divider
        $divider = new Field();
        $divider->setType(FieldType::DIVIDER);
        $ret[] = $divider;

        if (boolval($this->getSetting('hidden', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.hidden');
            $hidden = new Field();
            $hidden->setName("hidden");
            $hidden->setLabel($label ?? '');
            $hidden->setType(FieldType::CHECKBOX);
            $ret[] = $hidden;
        }

        if (boolval($this->getSetting('starttime', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:starttime_formlabel');
            $date = new Field();
            $date->setName("starttime");
            $date->setLabel($label ?? '');
            $date->setType(FieldType::DATETIME);
            if (boolval($this->getSetting('starttime', 'required'))) {
                $date->setValidation($required);
            }
            $ret[] = $date;
        }

        if (boolval($this->getSetting('endtime', 'field'))) {
            $label = LocalizationUtility::translate('LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:endtime_formlabel');
            $date = new Field();
            $date->setName("endtime");
            $date->setLabel($label ?? '');
            $date->setType(FieldType::DATETIME);
            if (boolval($this->getSetting('endtime', 'required'))) {
                $date->setValidation($required);
            }
            $ret[] = $date;
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

    /**
     * Return the settings of EXT:news
     */
    private function getNewsSetting(string $key)
    {
        if ($this->settings && isset($this->settingsNews[$key])) {
            return $this->settingsNews[$key];
        }
        return '';
    }

    /**
     * Sanity-Check DateTime
     *
     * @param DateTime|null $date
     * @return DateTime|null
     */
    private function getSanityDate(?DateTime $date): ?DateTime
    {
        if (!empty($date)) {
            $now = new DateTime();
            /** @var DateInterval */
            $age = $date->diff($now);
            if ($age->y > 100) {
                $date = $now;
            }
        }
        return $date;
    }
}
