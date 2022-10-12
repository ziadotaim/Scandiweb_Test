<?php
declare(strict_types=1);

namespace Scandiweb\Test\Setup\Patch\Data;

use \Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\App\State;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;

class Add_Product implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected ModuleDataSetupInterface $setup;
    /**
     * @var ProductInterfaceFactory
     */
    protected ProductInterfaceFactory $productInterfaceFactory;
    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;
    /**
     * @var State
     */
    protected State $appState;
    /**
     * @var EavSetup
     */
    protected EavSetup $eavSetup;
    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;
    /**
     * @var SourceItemInterfaceFactory
     */
    protected SourceItemInterfaceFactory $sourceItemFactory;
    /**
     * @var SourceItemsSaveInterface
     */
    protected SourceItemsSaveInterface $sourceItemsSaveInterface;
    /**
     * @var CategoryLinkManagementInterface
     */
    protected CategoryLinkManagementInterface $categoryLink;
    /**
     * @var array
     */
    protected array $sourceItems = [];

    /**
     * @param ModuleDataSetupInterface $setup
     * @param ProductInterfaceFactory $productInterfaceFactory
     * @param ProductRepositoryInterface $productRepository
     * @param State $appState
     * @param StoreManagerInterface $storeManager
     * @param EavSetup $eavSetup
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param SourceItemsSaveInterface $sourceItemsSaveInterface
     * @param CategoryLinkManagementInterface $categoryLink
     * @param CategoryCollectionFactory $categoryCollectionFactory
     */
    public function __construct(
        ModuleDataSetupInterface        $setup,
        ProductInterfaceFactory         $productInterfaceFactory,
        ProductRepositoryInterface      $productRepository,
        State                           $appState,
        StoreManagerInterface           $storeManager,
        EavSetup                        $eavSetup,
        SourceItemInterfaceFactory      $sourceItemFactory,
        SourceItemsSaveInterface        $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink,
        CategoryCollectionFactory       $categoryCollectionFactory
    )
    {
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->setup = $setup;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
    }

    /**
     * @return void
     */
    public function apply():void
    {
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    /**
     * @return void
     */
    public function execute():void
    {
        $product = $this->productInterfaceFactory->create();
        if ($product->getIdBySku('grip-trainer')) {
            return;
        }
        $product = $this->productRepository->save($product);
        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setAttributeSetId($attributeSetId)
            ->setName('Test Product')
            ->setSku('test-product')
            ->setUrlKey('testproduct')
            ->setPrice(9.99)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED);
        $product->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);
        $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
        $product->setWebsiteIds($websiteIDs);
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        $sourceItem->setQuantity(100);
        $sourceItem->setSku($product->getSku());
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItem;
        $this->sourceItemsSaveInterface->execute($this->sourceItems);
        $categoryTitles = ['Men', 'Women'];
        $categoryIds = $this->categoryCollectionFactory->create()
            ->addAttributeToFilter('name', ['in' => $categoryTitles])
            ->getAllIds();
        $this->categoryLink->assignProductToCategories($product->getSku(), $categoryIds);
    }
}
