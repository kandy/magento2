<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\CatalogUrlRewrite\Model\Product;

use Magento\Catalog\Model\Product;
use Magento\CatalogUrlRewrite\Model\ObjectRegistry;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;

class CategoriesUrlRewriteGenerator
{
    /**
     * @var \Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator
     */
    protected $productUrlPathGenerator;

    /**
     * @var \Magento\UrlRewrite\Service\V1\Data\UrlRewriteFactory
     */
    protected $urlRewriteFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $config;

    /**
     * @param ProductUrlPathGenerator $productUrlPathGenerator
     * @param UrlRewriteFactory $urlRewriteFactory
     * @param ScopeConfigInterface|null $config
     */
    public function __construct(
        ProductUrlPathGenerator $productUrlPathGenerator,
        UrlRewriteFactory $urlRewriteFactory,
        ScopeConfigInterface $config = null
    )
    {
        $this->productUrlPathGenerator = $productUrlPathGenerator;
        $this->urlRewriteFactory = $urlRewriteFactory;
        $this->config = $config ?: ObjectManager::getInstance()->get(ScopeConfigInterface::class);
    }

    /**
     * Generate product rewrites with categories
     *
     * @param int $storeId
     * @param Product $product
     * @param ObjectRegistry $productCategories
     * @return UrlRewrite[]
     */
    public function generate($storeId, Product $product, ObjectRegistry $productCategories)
    {
        if (!$this->isCategoryRewritesEnabled($storeId)){
            return [];
        }

        foreach ($productCategories->getList() as $category) {
            $urls[] = $this->urlRewriteFactory->create()
                ->setEntityType(ProductUrlRewriteGenerator::ENTITY_TYPE)
                ->setEntityId($product->getId())
                ->setRequestPath($this->productUrlPathGenerator->getUrlPathWithSuffix($product, $storeId, $category))
                ->setTargetPath($this->productUrlPathGenerator->getCanonicalUrlPath($product, $category))
                ->setStoreId($storeId)
                ->setMetadata(['category_id' => $category->getId()]);
        }
        return $urls;
    }

    /**
     * Check config value of generate_rewrites_on_save
     *
     * @param int $storeId
     * @return bool
     */
    private function isCategoryRewritesEnabled($storeId)
    {
        return (bool)$this->config->getValue(
            'catalog/seo/generate_rewrites_on_save',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
