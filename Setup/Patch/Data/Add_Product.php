<?php
declare(strict_types=1);

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

class Add_Product implements DataPatchInterface{
    protected ModuleDataSetupInterface $setup;

    protected ProductInterfaceFactory $productInterfaceFactory;

    protected ProductRepositoryInterface $productRepository;

    protected State $appState;

		protected EavSetup $eavSetup;

    protected StoreManagerInterface $storeManager;

		protected SourceItemInterfaceFactory $sourceItemFactory;

		protected SourceItemsSaveInterface $sourceItemsSaveInterface;

		protected CategoryLinkManagementInterface $categoryLink;

    protected array $sourceItems = [];

    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        StoreManagerInterface $storeManager,
        EavSetup $eavSetup,
				SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
				CategoryLinkManagementInterface $categoryLink,
        CategoryCollectionFactory $categoryCollectionFactory
    ) {
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

    public function apply(){
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    public function execute()
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