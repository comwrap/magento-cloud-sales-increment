<?php

declare(strict_types=1);

namespace Comwrap\CloudSalesIncrement\Test\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Payment;
use Magento\Store\Model\StoreManagerInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

class OrderIncrementIdTest extends TestCase
{
    private ?ResourceConnection $resourceConnection;
    private ?ObjectManagerInterface $objectManager;

    /**
     * @magentoDataFixture Magento/Sales/_files/default_rollback.php
     * @magentoDataFixture Magento/Catalog/_files/product_simple.php
     */
    public function testOrderIdAutoincrementIs1(): void
    {
        $this->modifyAutoIncrementVariables();

        $orders = $this->createOrders();

        $firstOrderId = (int) current($orders)->getIncrementId();
        $secondOrderId = (int) next($orders)->getIncrementId();

        $this->assertEquals(++$firstOrderId, $secondOrderId);
    }

    private function modifyAutoIncrementVariables(): void
    {
        $this->resourceConnection->getConnection()->query("SET @@auto_increment_increment=3");
        $this->resourceConnection->getConnection()->query("SET @@auto_increment_offset=2");
    }

    private function createOrders(): array
    {
        $addressData = [
            'region' => 'CA',
            'region_id' => '12',
            'postcode' => '11111',
            'lastname' => 'lastname',
            'firstname' => 'firstname',
            'street' => 'street',
            'city' => 'Los Angeles',
            'email' => 'admin@example.com',
            'telephone' => '11111111',
            'country_id' => 'US'
        ];

        /** @var ProductRepositoryInterface $productRepository */
        $productRepository = $this->objectManager->create(ProductRepositoryInterface::class);
        /** @var Product $product */
        $product = $productRepository->get('simple');
        /** @var OrderAddress $billingAddress */
        $billingAddress = $this->objectManager->create(OrderAddress::class, ['data' => $addressData]);
        $billingAddress->setAddressType('billing');

        $shippingAddress = clone $billingAddress;
        $shippingAddress->setId(null)->setAddressType('shipping');

        /** @var OrderRepositoryInterface $orderRepository */
        $orderRepository = $this->objectManager->create(OrderRepositoryInterface::class);

        $orders = [];
        for ($i = 0; $i < 2; $i++) {
            /** @var Payment $payment */
            $payment = $this->objectManager->create(Payment::class);
            $payment->setMethod('checkmo')
                ->setAdditionalInformation('last_trans_id', '11122' . $i)
                ->setAdditionalInformation(
                    'metadata',
                    [
                        'type' => 'free',
                        'fraudulent' => false,
                    ]
                );

            /** @var OrderItem $orderItem */
            $orderItem = $this->objectManager->create(OrderItem::class);
            $orderItem->setProductId($product->getId())
                ->setQtyOrdered(2)
                ->setBasePrice($product->getPrice())
                ->setPrice($product->getPrice())
                ->setRowTotal($product->getPrice())
                ->setProductType('simple')
                ->setName($product->getName())
                ->setSku($product->getSku());

            /** @var Order $order */
            $order = $this->objectManager->create(Order::class);
            $order->setState(Order::STATE_PROCESSING)
                ->setStatus($order->getConfig()->getStateDefaultStatus(Order::STATE_PROCESSING))
                ->setSubtotal(100)
                ->setGrandTotal(100)
                ->setBaseSubtotal(100)
                ->setBaseGrandTotal(100)
                ->setOrderCurrencyCode('USD')
                ->setBaseCurrencyCode('USD')
                ->setCustomerIsGuest(true)
                ->setCustomerEmail('customer@null.com')
                ->setBillingAddress($billingAddress)
                ->setShippingAddress($shippingAddress)
                ->setStoreId($this->objectManager->get(StoreManagerInterface::class)->getStore()->getId())
                ->addItem($orderItem)
                ->setPayment($payment);

            $orders[] = $orderRepository->save($order);
        }

        return $orders;
    }

    protected function setUp(): void
    {
        $this->objectManager = Bootstrap::getObjectManager();
        $this->resourceConnection = $this->objectManager->get(ResourceConnection::class);
    }
}
