<?php
namespace MDOQ\SitePerformanceMonitoring\Model;

use MDOQ\SitePerformanceMonitoring\Api\SiteRepositoryInterface;
use MDOQ\SitePerformanceMonitoring\Api\Data\SiteInterface;
use MDOQ\SitePerformanceMonitoring\Model\SiteFactory as ModelFactory;
use MDOQ\SitePerformanceMonitoring\Model\ResourceModel\Site as ResourceModel;
use MDOQ\SitePerformanceMonitoring\Model\ResourceModel\Site\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface as CollectionProcessor;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Class SiteRepository
 */
class SiteRepository implements SiteRepositoryInterface
{
    public function getNew(): SiteInterface
    {
        return $this->modelFactory->create();
    }

    /**
     * SiteRepository constructor.
     *
     * @param SiteFactory $objectFactory
     * @param ObjectResourceModel $objectResourceModel
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     */
    public function __construct(
        protected ModelFactory $modelFactory,
        protected ResourceModel $resourceModel,
        protected CollectionFactory $collectionFactory,
        protected SearchResultsInterfaceFactory $searchResultsFactory,
        protected CollectionProcessor $collectionProcessor
    ) {
    }

    /**
     * @inheritDoc
     *
     * @throws CouldNotSaveException
     */
    public function save(SiteInterface $object)
    {
        $this->resourceModel->save($object);
        return $object;
    }

    /**
     * @inheritDoc
     */
    public function getById($id)
    {
        $object = $this->modelFactory->create();
        $this->resourceModel->load($object, $id);
        if (!$object->getId()) {
            throw new NoSuchEntityException(__('Site with id "%1" does not exist.', $id));
        }
        return $object;
    }

    /**
     * @inheritDoc
     */
    public function delete(SiteInterface $object)
    {
        try {
            $this->resourceModel->delete($object);
        } catch (\Exception $exception) {
            throw new CouldNotDeleteException(__($exception->getMessage()));
        }
        return true;
    }

    /**
     * @inheritDoc
     */
    public function deleteById($id)
    {
        return $this->delete($this->getById($id));
    }

    /**
     * @inheritDoc
     */
    public function getList(SearchCriteriaInterface $criteria)
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($criteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($criteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}
