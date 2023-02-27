<?php



namespace Lof\FilterStock\Model\Layer\Filter;

use Lof\Inventory\Model\WarehouseRepository;
use Lof\Inventory\Model\ResourceModel\Stock\CollectionFactory;

class Stock extends \Magento\Catalog\Model\Layer\Filter\AbstractFilter
{
    const IN_STOCK_COLLECTION_FLAG = 'lof_stock_filter_applied';
    const CONFIG_FILTER_LABEL_PATH = 'lof_stockfilter/settings/label';
    const CONFIG_URL_PARAM_PATH    = 'lof_stockfilter/settings/url_param';
    protected $_activeFilter = false;
    protected $_requestVar = 'in-stock';
    protected $_scopeConfig;
    protected $_customerSessionFactory;
    protected $_warehouseRepository;
    protected $_productCollectionFactory;
    /**
     * @var CollectionFactory
     */
    protected $collectionFactory;
    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Catalog\Model\Layer $layer
     * @param WarehouseRepository $warehouseRepository
     * @param CollectionFactory $collectionFactory
     * @param \Magento\Customer\Model\SessionFactory $customerSessionFactory
     * @param \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Customer\Model\SessionFactory $customerSessionFactory,
        WarehouseRepository $warehouseRepository,
        CollectionFactory $collectionFactory,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        \Magento\Catalog\Model\Layer\Filter\ItemFactory $filterItemFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\Layer $layer,
        \Magento\Catalog\Model\Layer\Filter\Item\DataBuilder $itemDataBuilder,
        array $data = []
    ) {
        $this->_scopeConfig = $scopeConfig;
        $this->_customerSessionFactory = $customerSessionFactory;
        $this->_warehouseRepository = $warehouseRepository;
        $this->collectionFactory = $collectionFactory;
        $this->_productCollectionFactory = $productCollectionFactory;
        parent::__construct($filterItemFactory, $storeManager, $layer, $itemDataBuilder, $data);
        $this->_requestVar = $this->_scopeConfig->getValue(
            self::CONFIG_URL_PARAM_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getProductCollection()
    {
        $collection = $this->_productCollectionFactory->create();
        $collection->addAttributeToSelect('*');
        $collection->setPageSize(1); // only get 10 products
        $collection->setCurPage(1);
         // fetching only 3 products
        return $collection;
    }

    /**
     * @param \Magento\Framework\App\RequestInterface $request
     * @return $this
     */
    public function apply(\Magento\Framework\App\RequestInterface $request)
    {
        $filter = $request->getParam($this->getRequestVar(), null);
        if (is_null($filter)) {
            return $this;
        };
        $attributeValue    = explode(',', $filter);
        $this->_activeFilter = true;
        $filter = 0;
        $collection = $this->getLayer()->getProductCollection();
        $collection->setFlag(self::IN_STOCK_COLLECTION_FLAG, true);
        $collection->getSelect()->where('stock_status_index.stock_status = ?', $filter);

        $this->getLayer()->getState()->addFilter(
            $this->_createItem($this->getLabel($filter), $filter)
        );
        return $this;
    }
    /**
     * Get filter name
     *
     * @return string
     */
    public function getName()
    {
        return $this->_scopeConfig->getValue(
            self::CONFIG_FILTER_LABEL_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }
    /**
     * Get data array for building status filter items
     *
     * @return array
     */
    protected function _getItemsData()
    {
        if ($this->getLayer()->getProductCollection()->getFlag(self::IN_STOCK_COLLECTION_FLAG)) {
            return [];
        }
        $data = [];
        foreach ($this->getStatuses() as $status) {
            $data[] = [
                'label' => $this->getLabel($status),
                'value' => $status,
                'count' => $this->getProductsCount($status)
            ];
        }
        return $data;
    }
    /**
     * get available statuses
     * @return array
     */
    public function getStatuses()
    {
        return [
            \Magento\CatalogInventory\Model\Stock::STOCK_IN_STOCK,
            \Magento\CatalogInventory\Model\Stock::STOCK_OUT_OF_STOCK
        ];
    }
    /**
     * @return array
     */
    public function getLabels()
    {
        return [
            \Magento\CatalogInventory\Model\Stock::STOCK_IN_STOCK => __('In Stock'),
            \Magento\CatalogInventory\Model\Stock::STOCK_OUT_OF_STOCK => __('Out of stock'),
        ];
    }
    /**
     * @param $value
     * @return string
     */
    public function getLabel($value)
    {
        $labels = $this->getLabels();
        if (isset($labels[$value])) {
            return $labels[$value];
        }
        return '';
    }

    /**
     * @param $value
     * @return string
     */
    public function getProductsCount($value)
    {
        $collection = $this->getLayer()->getProductCollection();
        $select = clone $collection->getSelect();
        // reset columns, order and limitation conditions
        $select->reset(\Zend_Db_Select::COLUMNS);
        $select->reset(\Zend_Db_Select::ORDER);
        $select->reset(\Zend_Db_Select::LIMIT_COUNT);
        $select->reset(\Zend_Db_Select::LIMIT_OFFSET);
        $select->where('stock_status_index.stock_status = ?', $value);
        $select->columns(
            [
                'count' => new \Zend_Db_Expr("COUNT(e.entity_id)")
            ]
        );
        return $collection->getConnection()->fetchOne($select);
    }
}
