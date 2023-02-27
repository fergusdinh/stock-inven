<?php
namespace Lof\FilterStock\Model\Plugin;

use Lof\Inventory\Model\WarehouseRepository;
use Lof\Inventory\Model\ResourceModel\Stock\CollectionFactory;

class FilterList
{
    const CONFIG_ENABLED_XML_PATH   = 'lof_stockfilter/settings/enabled';
    const CONFIG_POSITION_XML_PATH  = 'lof_stockfilter/settings/position';
    const STOCK_FILTER_CLASS        = 'Lof\FilterStock\Model\Layer\Filter\Stock';
    /**
     * @var \Magento\Framework\ObjectManager
     */
    protected $_objectManager;
    /**
     * @var \Magento\Catalog\Model\Layer
     */
    protected $_layer;
    /**
     * @var \Magento\Framework\StoreManagerInterface
     */
    protected $_storeManager;
    /**
     * @var \Magento\CatalogInventory\Model\ResourceModel\Stock\Status
     */
    protected $_stockResource;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    protected $_customerSessionFactory;

    protected $_warehouseRepository;

    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Customer\Model\SessionFactory $customerSessionFactory
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param WarehouseRepository $warehouseRepository
     * @param CollectionFactory $collectionFactory
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\Status $stockResource
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Customer\Model\SessionFactory $customerSessionFactory,
        WarehouseRepository $warehouseRepository,
        CollectionFactory $collectionFactory,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Status $stockResource,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
    ) {
        $this->_storeManager = $storeManager;
        $this->_objectManager = $objectManager;
        $this->_customerSessionFactory = $customerSessionFactory;
        $this->_warehouseRepository = $warehouseRepository;
        $this->collectionFactory = $collectionFactory;
        $this->_stockResource = $stockResource;
        $this->_scopeConfig = $scopeConfig;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        $outOfStockEnabled = $this->_scopeConfig->isSetFlag(
            \Magento\CatalogInventory\Model\Configuration::XML_PATH_DISPLAY_PRODUCT_STOCK_STATUS,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        $extensionEnabled = $this->_scopeConfig->isSetFlag(
            self::CONFIG_ENABLED_XML_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
        return $outOfStockEnabled && $extensionEnabled;
    }

    /**
     * @param \Magento\Catalog\Model\Layer\FilterList\Interceptor $filterList
     * @param \Magento\Catalog\Model\Layer $layer
     * @return array
     */
    public function beforeGetFilters(
        \Magento\Catalog\Model\Layer\FilterList\Interceptor $filterList,
        \Magento\Catalog\Model\Layer $layer
    ) {
        $this->_layer = $layer;
        if ($this->isEnabled()) {
            $collection = $layer->getProductCollection();
        }
        return array($layer);
    }

    /**
     * @param \Magento\Catalog\Model\Layer\FilterList\Interceptor $filterList
     * @param array $filters
     * @return array
     */
    public function afterGetFilters(
        \Magento\Catalog\Model\Layer\FilterList\Interceptor $filterList,
        array $filters
    ) {
        if ($this->isEnabled()) {
            $position = $this->getFilterPosition();
            $stockFilter = $this->getStockFilter();
            switch ($position) {
                case \Lof\FilterStock\Model\Source\Position::POSITION_BOTTOM:
                    $filters[] = $this->getStockFilter();
                    break;
                case \Lof\FilterStock\Model\Source\Position::POSITION_TOP:
                    array_unshift($filters, $stockFilter);
                    break;
                case \Lof\FilterStock\Model\Source\Position::POSITION_AFTER_CATEGORY:
                    $processed = [];
                    $stockFilterAdded = false;
                    foreach ($filters as $key => $value) {
                        $processed[] = $value;
                        if ($value instanceof \Magento\Catalog\Model\Layer\Filter\Category || $value instanceof \Magento\CatalogSearch\Model\Layer\Filter\Category) {
                            $processed[] = $stockFilter;
                            $stockFilterAdded = true;
                        }
                    }
                    $filters = $processed;
                    if (!$stockFilterAdded) {
                        array_unshift($filters, $stockFilter);
                    }
                    break;
            }

        }
        return $filters;
    }

    /**
     * @return \Lof\FilterStock\Model\Layer\Filter\Stock
     */
    public function getStockFilter()
    {
        $filter = $this->_objectManager->create(
            $this->getStockFilterClass(),
            ['layer' => $this->_layer]
        );
        return $filter;
    }

    /**
     * @return string
     */
    public function getStockFilterClass()
    {
        return self::STOCK_FILTER_CLASS;
    }

    public function getFilterPosition()
    {
        return $this->_scopeConfig->getValue(
            self::CONFIG_POSITION_XML_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
}
