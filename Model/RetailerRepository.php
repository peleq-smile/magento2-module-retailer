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

use Magento\Framework\Api\ExtensibleDataInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SortOrder;
use Magento\Framework\Exception\NoSuchEntityException;
use Smile\Retailer\Api\Data\RetailerExtension;
use Smile\Retailer\Api\Data\RetailerInterface;
use Smile\Retailer\Api\Data\RetailerSearchResultsInterface;
use Smile\Retailer\Api\Data\RetailerSearchResultsInterfaceFactory;
use Smile\Retailer\Api\RetailerRepositoryInterface;
use Smile\Retailer\Model\ResourceModel\Retailer\Collection;
use Smile\Retailer\Model\ResourceModel\Retailer\CollectionFactory;
use Smile\StoreLocator\Api\Data\RetailerTimeSlotInterface;

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
    protected $sellerRepository;

    /**
     * @var RetailerSearchResultsInterfaceFactory
     */
    protected $searchResultFactory;

    /**
     * @var \Smile\Retailer\Api\Data\RetailerInterfaceFactory
     */
    protected $retailerFactory;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Framework\Api\ExtensibleDataObjectConverter
     */
    protected $extensibleDataObjectConverter;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * Constructor.
     *
     * @param \Smile\Seller\Model\SellerRepositoryFactory          $sellerRepositoryFactory Seller repository.
     * @param \Smile\Retailer\Api\Data\RetailerInterfaceFactory    $retailerFactory         Retailer factory.
     * @param RetailerSearchResultsInterfaceFactory                $searchResultFactory     Search Result factory.
     * @param CollectionFactory                                    $collectionFactory       Collection factory.
     * @param \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter
     * @param \Magento\Store\Model\StoreManagerInterface           $storeManager
     */
    public function __construct(
        \Smile\Seller\Model\SellerRepositoryFactory $sellerRepositoryFactory,
        \Smile\Retailer\Api\Data\RetailerInterfaceFactory $retailerFactory,
        RetailerSearchResultsInterfaceFactory $searchResultFactory,
        CollectionFactory $collectionFactory,
        \Magento\Framework\Api\ExtensibleDataObjectConverter $extensibleDataObjectConverter,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    )
    {
        $this->sellerRepository = $sellerRepositoryFactory->create([
            'sellerFactory'    => $retailerFactory,
            'attributeSetName' => RetailerInterface::ATTRIBUTE_SET_RETAILER,
        ]);

        $this->retailerFactory = $retailerFactory;
        $this->searchResultFactory = $searchResultFactory;
        $this->collectionFactory = $collectionFactory;
        $this->extensibleDataObjectConverter = $extensibleDataObjectConverter;
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritDoc}
     */
    public function save(\Smile\Retailer\Api\Data\RetailerInterface $retailer)
    {
        /** @var \Smile\Retailer\Model\Retailer $existingRetailer */
        /** @var \Smile\Retailer\Model\Retailer $retailer */
        // Handle case of create / update
        try {
            $existingRetailer = $this->getByCode($retailer->getSellerCode());
        } catch (NoSuchEntityException $e) {
            $existingRetailer = null;
        }

        // Retrieve existing data and override by input data
        $retailerDataArray = $this->extensibleDataObjectConverter
            ->toNestedArray($retailer, [], 'Smile\Retailer\Api\Data\RetailerExtensionInterface');
        $retailerDataArray = array_replace($retailerDataArray, $retailer->getData());

        // Case of address : set data from «extension_attributes» property of $retailer as direct $retailer data
        if (null !== $retailer->getExtensionAttributes() && null !== $retailer->getExtensionAttributes()->getAddress()) {
            $retailerDataArray['address'] = $retailer->getExtensionAttributes()->getAddress();
        }

        $retailer = $this->initializeRetailerData($retailerDataArray, $existingRetailer);
        $this->sellerRepository->save($retailer);

        return $this->getByCode($retailer->getSellerCode());
    }

    /**
     * {@inheritDoc}
     */
    public function getByCode($retailerCode, $storeId = null)
    {
        return $this->sellerRepository->getByCode($retailerCode, $storeId);
    }

    /**
     * Merge data from DB and updates from request
     *
     * @param array                          $retailerData
     * @param \Smile\Retailer\Model\Retailer $existingRetailer
     *
     * @return \Smile\Retailer\Api\Data\RetailerInterface
     * @throws NoSuchEntityException
     */
    protected function initializeRetailerData(array $retailerData, $existingRetailer = null)
    {
        if (null === $existingRetailer) {
            $retailer = $this->retailerFactory->create();
        } else {
            $retailer = $existingRetailer;
        }

        if ($this->storeManager->hasSingleStore()) {
            $retailerData['store_id'] = 0;
        }

        $retailer->addData($retailerData);

        // Handle case of extension_attributes data
        if (isset($retailerData[ExtensibleDataInterface::EXTENSION_ATTRIBUTES_KEY])
            && null !== $retailerData[ExtensibleDataInterface::EXTENSION_ATTRIBUTES_KEY]
        ) {
            /** @var RetailerExtension $extensionAttributes */
            $extensionAttributes = $retailerData[ExtensibleDataInterface::EXTENSION_ATTRIBUTES_KEY];
            $retailer->addData($extensionAttributes->__toArray());

            // Specific cases of RetailerTimeSlot
            if ($extensionAttributes->getOpeningHours()) {
                $rebuiltRetailerTimeSlotData = $this->rebuildRetailerTimeSlotsData($extensionAttributes->getOpeningHours());
                $retailer->setData('opening_hours', $rebuiltRetailerTimeSlotData);
            }
            if ($extensionAttributes->getSpecialOpeningHours()) {
                $rebuiltRetailerTimeSlotData = $this->rebuildRetailerTimeSlotsData($extensionAttributes->getSpecialOpeningHours());
                $retailer->setData('special_opening_hours', $rebuiltRetailerTimeSlotData);
            }
        }

        return $retailer;
    }

    /**
     * Re-build data of type RetailerTimeSlot… because RetailerTimeSlot::saveTimeSlots is called but do not handle schema used by API :-/.
     *
     * @param array $retailerTimeSlotsData
     *
     * @return array
     * @see    RetailerTimeSlot::saveTimeSlots
     */
    protected function rebuildRetailerTimeSlotsData(array $retailerTimeSlotsData)
    {
        $rebuiltRetailerTimeSlotData = [];

        if (null !== $retailerTimeSlotsData) {
            /** @var \Smile\StoreLocator\Model\Data\RetailerTimeSlot $retailerTimeSlotData */
            foreach ($retailerTimeSlotsData as $retailerTimeSlotData) {
                if (null !== $retailerTimeSlotData->getDayOfWeek()) {
                    $dayOfWeek = (int) $retailerTimeSlotData->getDayOfWeek();
                    unset($retailerTimeSlotData[RetailerTimeSlotInterface::DAY_OF_WEEK_FIELD]);

                    $rebuiltRetailerTimeSlotData[$dayOfWeek][] = $retailerTimeSlotData;
                } elseif (null !== $retailerTimeSlotData->getDate()) {
                    $date = $retailerTimeSlotData->getDate();
                    unset($retailerTimeSlotData[RetailerTimeSlotInterface::DATE_FIELD]);

                    $rebuiltRetailerTimeSlotData[$date][] = $retailerTimeSlotData;
                } // FIXME else throw exception ?

                // Handle extension_attributes
                /** @var \Smile\StoreLocator\Api\Data\RetailerTimeSlotExtension $extensionAttributes */
                $extensionAttributes = $retailerTimeSlotData->getExtensionAttributes();
                $extensionAttributesData = $extensionAttributes->__toArray();
                if (!empty($extensionAttributesData)) {
                    $retailerTimeSlotData->addData($extensionAttributes->__toArray());
                }
            }
        }

        return $rebuiltRetailerTimeSlotData;
    }

    /**
     * {@inheritDoc}
     */
    public function get($retailerId, $storeId = null)
    {
        return $this->sellerRepository->get($retailerId, $storeId);
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
