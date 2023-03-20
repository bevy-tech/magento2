<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types=1);

namespace Magento\ConfigurableProduct\Controller\Adminhtml\Product\Attribute;

use Magento\Catalog\Api\ProductAttributeRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Test\Fixture\Attribute as AttributeFixture;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\TestFramework\Fixture\DataFixture;
use Magento\TestFramework\TestCase\AbstractBackendController;
use Magento\Eav\Model\Config;
use Magento\Catalog\Api\Data\ProductAttributeInterface;

/**
 * Checks creating attribute options process.
 *
 * @see \Magento\ConfigurableProduct\Controller\Adminhtml\Product\Attribute\CreateOptions
 * @magentoAppArea adminhtml
 * @magentoDbIsolation enabled
 */
class CreateOptionsTest extends AbstractBackendController
{
    /**
     * @var ProductAttributeRepositoryInterface
     */
    private $productAttributeRepository;

    /**
     * @var Config
     */
    private $eavConfig;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $productRepository = $this->_objectManager->get(ProductRepositoryInterface::class);
        $productRepository->cleanCache();
        $this->productAttributeRepository = $this->_objectManager->create(ProductAttributeRepositoryInterface::class);
        $this->eavConfig = $this->_objectManager->create(Config::class);
    }

    /**
     * @magentoDataFixture Magento/ConfigurableProduct/_files/product_configurable.php
     *
     * @return void
     */
    public function testAddAlreadyAddedOption(): void
    {
        $this->getRequest()->setMethod(HttpRequest::METHOD_POST);
        $attribute = $this->_objectManager->get(ProductAttributeRepositoryInterface::class)
            ->get('test_configurable');
        $this->getRequest()->setParams([
            'options' => [
                [
                    'label' => 'Option 1',
                    'is_new' => true,
                    'attribute_id' => (int)$attribute->getAttributeId(),
                ],
            ],
        ]);
        $this->dispatch('backend/catalog/product_attribute/createOptions');
        $responseBody = $this->_objectManager->get(SerializerInterface::class)
            ->unserialize($this->getResponse()->getBody());
        $this->assertNotEmpty($responseBody);
        $this->assertStringContainsString(
            (string)__('The value of attribute ""%1"" must be unique', $attribute->getAttributeCode()),
            $responseBody['message']
        );
    }

    /**
     * @inheritDoc
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $reflection = new \ReflectionObject($this);
        foreach ($reflection->getProperties() as $property) {
            if (!$property->isStatic() && 0 !== strpos($property->getDeclaringClass()->getName(), 'PHPUnit')) {
                $property->setAccessible(true);
                $property->setValue($this, null);
            }
        }
    }

    /**
     * Test updating a product attribute and checking the frontend_class for the sku attribute.
     *
     * @return void
     * @throws LocalizedException
     */
    #[
        DataFixture(AttributeFixture::class, as: 'attr'),
    ]
    public function testAttributeWithBackendTypeHasSameValueInFrontendClass()
    {
        // Load the 'sku' attribute.
        /** @var ProductAttributeInterface $attribute */
        $attribute = $this->productAttributeRepository->get('sku');
        $expectedFrontEndClass = $attribute->getFrontendClass();

        // Save the attribute.
        $this->productAttributeRepository->save($attribute);

        // Check that the frontend_class was updated correctly.
        try {
            $skuAttribute = $this->eavConfig->getAttribute('catalog_product', 'sku');
            $this->assertEquals($expectedFrontEndClass, $skuAttribute->getFrontendClass());
        } catch (LocalizedException $e) {
            $this->fail($e->getMessage());
        }
    }
}
