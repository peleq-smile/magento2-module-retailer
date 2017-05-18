<?php
/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\Retailer
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2016 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Smile\Retailer\Model;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Smile\Retailer\Api\Data\RetailerInterface;
use Smile\Retailer\Api\Data\RetailerSearchResultsInterface;
use Smile\Retailer\Api\Data\RetailerSearchResultsInterfaceFactory;
use Smile\Retailer\Api\RetailerRepositoryInterface;
use Smile\Retailer\Model\ResourceModel\Retailer\Collection;
use Smile\Retailer\Model\ResourceModel\Retailer\CollectionFactory;

/**
 * Retailer Repository
 *
 * @category Smile
 * @package  Smile\Retailer
 * @author   Romain Ruaud <romain.ruaud@smile.fr>
 */
class RetailerRepository implements RetailerRepositoryInterface
{
    /**
     * @var \Smile\Seller\Model\SellerRepository
     */
    private $sellerRepository;

    /**
     * @var RetailerSearchResultsInterfaceFactory
     */
    private $searchResultFactory;

    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * Constructor.
     *
     * @param \Smile\Seller\Model\SellerRepositoryFactory       $sellerRepositoryFactory Seller repository.
     * @param \Smile\Retailer\Api\Data\RetailerInterfaceFactory $retailerFactory         Retailer factory.
     * @param RetailerSearchResultsInterfaceFactory             $searchResultFactory     Search Result factory.
     * @param CollectionFactory                                 $collectionFactory       Collection factory.
     */
    public function __construct(
        \Smile\Seller\Model\SellerRepositoryFactory $sellerRepositoryFactory,
        \Smile\Retailer\Api\Data\RetailerInterfaceFactory $retailerFactory,
        RetailerSearchResultsInterfaceFactory $searchResultFactory,
        CollectionFactory $collectionFactory
    )
    {
        $this->sellerRepository = $sellerRepositoryFactory->create([
            'sellerFactory'    => $retailerFactory,
            'attributeSetName' => RetailerInterface::ATTRIBUTE_SET_RETAILER,
        ]);

        $this->searchResultFactory = $searchResultFactory;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * {@inheritDoc}
     */
    public function save(\Smile\Retailer\Api\Data\RetailerInterface $retailer)
    {
        // Set data from «extension_attributes» proporty of $retailer as direct $retailer data
        /** @var \Smile\Retailer\Model\Retailer $retailer */
        $dataToAddToRetailer = [
            'address' => $retailer->getExtensionAttributes()->getAddress(),
        ];

        $retailer->addData($dataToAddToRetailer);

        return $this->sellerRepository->save($retailer);
    }

    /**
     * {@inheritDoc}
     */
    public function get($retailerId, $storeId = null)
    {
        return $this->sellerRepository->get($retailerId, $storeId);
    }

    /**
     * {@inheritDoc}
     */
    public function getByCode($retailerCode, $storeId = null)
    {
        return $this->sellerRepository->getByCode($retailerCode, $storeId);
    }

    /**
     * Search for retailers.
     *
     * @param SearchCriteriaInterface $searchCriteria Search criteria
     *
     * @return RetailerSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria)
    {
        /** @var Collection $collection */
        $collection = $this->collectionFactory->create();

        // Add filters from root filter group to the collection.
        foreach ($searchCriteria->getFilterGroups() as $group) {
            $this->addFilterGroupToCollection($group, $collection);
        }

        /** @var SortOrder $sortOrder */
        foreach ((array) $searchCriteria->getSortOrders() as $sortOrder) {
            $field = $sortOrder->getField();
            $collection->addOrder(
                $field,
                ($sortOrder->getDirection() == SortOrder::SORT_ASC) ? 'ASC' : 'DESC'
            );
        }
        $collection->setCurPage($searchCriteria->getCurrentPage());
        $collection->setPageSize($searchCriteria->getPageSize());
        $collection->load();

        $searchResult = $this->searchResultFactory->create();
        $searchResult->setSearchCriteria($searchCriteria);
        $searchResult->setItems($collection->getItems());
        $searchResult->setTotalCount($collection->getSize());

        return $searchResult;
    }

    /**
     * Helper function that adds a FilterGroup to the collection.
     *
     * @param \Magento\Framework\Api\Search\FilterGroup $filterGroup Filter Group
     * @param Collection                                $collection  Retailer collection
     *
     * @return void
     */
    protected function addFilterGroupToCollection(
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        Collection $collection
    )
    {
        $fields = [];
        foreach ($filterGroup->getFilters() as $filter) {
            $conditionType = $filter->getConditionType() ? $filter->getConditionType() : 'eq';

            $fields[] = ['attribute' => $filter->getField(), $conditionType => $filter->getValue()];
        }

        if ($fields) {
            $collection->addFieldToFilter($fields);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function delete(\Smile\Retailer\Api\Data\RetailerInterface $retailer)
    {
        return $this->sellerRepository->delete($retailer);
    }

    /**
     * {@inheritDoc}
     */
    public function deleteByIdentifier($retailerId)
    {
        return $this->sellerRepository->deleteByIdentifier($retailerId);
    }
}
