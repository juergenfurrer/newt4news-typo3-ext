<?php

declare(strict_types=1);

namespace Infonique\Newt4News\Newt;

use DateInterval;
use DateTime;
use GeorgRinger\News\Domain\Model\News;
use GeorgRinger\News\Domain\Repository\NewsRepository;
use Infonique\Newt\NewtApi\EndpointInterface;
use Infonique\Newt\NewtApi\Field;
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
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class NewsEndpoint implements EndpointInterface
{
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
        $news->setIstopnews($params["istopnews"]);
        $news->setTitle($params["title"]);
        $news->setTeaser($params["teaser"]);
        $news->setBodytext($params["bodytext"]);

        if ($model->getPageUid() > 0) {
            $news->setPid($model->getPageUid());
        }
        if ($params["image"] && $params["image"] instanceof \Infonique\Newt\Domain\Model\FileReference) {
            /** @var \Infonique\Newt\Domain\Model\FileReference */
            $imageRef = $params["image"];
            $fileReference = new \GeorgRinger\News\Domain\Model\FileReference();
            $fileReference->setFileUid($imageRef->getUidLocal());
            $news->addFalMedia($fileReference);
        }

        // Set News-Type
        $news->setType("0");

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
        if ($model->getBackendUserUid() > 0) {
            $news->setCruserId($model->getBackendUserUid());
        }
        /** @var ObjectManager */
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        /** @var NewsRepository */
        $newsRepository = $objectManager->get(NewsRepository::class);
        $newsRepository->add($news);

        // persist the item
        $objectManager->get(PersistenceManager::class)->persistAll();

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
        $istopnews = new Field();
        $istopnews->setName("istopnews");
        $istopnews->setLabel("Top News");
        $istopnews->setType(FieldType::CHECKBOX);

        $title = new Field();
        $title->setName("title");
        $title->setLabel("Title");
        $title->setType(FieldType::TEXT);
        $fieldValidation = new FieldValidation();
        $fieldValidation->setRequired(true);
        $title->setValidation($fieldValidation);

        $teaser = new Field();
        $teaser->setName("teaser");
        $teaser->setLabel("Teaser");
        $teaser->setType(FieldType::TEXTAREA);

        $bodytext = new Field();
        $bodytext->setName("bodytext");
        $bodytext->setLabel("Body text");
        $bodytext->setType(FieldType::TEXTAREA);

        $datetime = new Field();
        $datetime->setName("datetime");
        $datetime->setLabel("Date & Time");
        $datetime->setType(FieldType::DATETIME);

        $image = new Field();
        $image->setName("image");
        $image->setLabel("Image");
        $image->setType(FieldType::IMAGE);

        return [
            $istopnews,
            $title,
            $teaser,
            $bodytext,
            $datetime,
            $image,
        ];
    }
}
