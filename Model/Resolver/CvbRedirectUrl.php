<?php

namespace JustBetter\CheckoutViaBolGraphQl\Model\Resolver;

use Bol\CheckoutViaBol\Api\CvbOrderRepositoryInterface;
use Bol\CheckoutViaBol\Api\Data\CvbOrderInterfaceFactory;
use Bol\CheckoutViaBol\Exception\CvbApiException;
use Bol\CheckoutViaBol\Model\Ui\ConfigProvider;
use Bol\CheckoutViaBol\Service\CvbService;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Psr\Log\LoggerInterface;

class CvbRedirectUrl implements ResolverInterface
{
    /**
     * CvbRedirectUrl constructor.
     *
     * @param CvbService                  $cvbService
     * @param CvbOrderRepositoryInterface $cvbOrderRepository
     * @param CvbOrderInterfaceFactory    $cvbOrderFactory
     * @param OrderRepositoryInterface    $orderRepository
     * @param SearchCriteriaBuilder       $searchCriteriaBuilder
     * @param OrderManagementInterface    $orderManagement
     * @param LoggerInterface             $logger
     */
    public function __construct(
        private readonly CvbService $cvbService,
        private readonly CvbOrderRepositoryInterface $cvbOrderRepository,
        private readonly CvbOrderInterfaceFactory $cvbOrderFactory,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly OrderManagementInterface $orderManagement,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Start and confirm a bol.checkout session for the order and return the redirect url.
     *
     * @param Field        $field
     * @param mixed        $context
     * @param ResolveInfo  $info
     * @param mixed[]|null $value
     * @param mixed[]|null $args
     *
     * @return string|null
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): ?string
    {
        $order = $value['model'] ?? null;

        if (!$order instanceof OrderInterface
            || $order->getPayment()?->getMethod() !== ConfigProvider::CODE
            || $order->getState() !== Order::STATE_PENDING_PAYMENT
            || $this->hasCvbOrder($order)
        ) {
            return null;
        }

        try {
            $this->cancelPreviousOrders($order);
        } catch (Exception $e) {
            $this->logger->error('Could not cancel previous bol.checkout orders', ['exception' => $e]);
        }

        try {
            $cvbSessionId = $this->cvbService->createCvbSessionFromOrder($order)['id'];

            $cvbOrder = $this->cvbOrderFactory->create();
            $cvbOrder->setOrderId((int)$order->getEntityId());
            $cvbOrder->setCvbSessionId($cvbSessionId);
            $cvbOrder->setIsFillInSession(false);
            $this->cvbOrderRepository->save($cvbOrder);

            return $this->cvbService->confirmSession($cvbSessionId, $order);
        } catch (CvbApiException) {
            return null;
        }
    }

    /**
     * Cancel pending bol.checkout orders placed earlier from the same quote, e.g. after a cancelled payment.
     *
     * @param OrderInterface $order
     *
     * @return void
     */
    private function cancelPreviousOrders(OrderInterface $order): void
    {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('quote_id', $order->getQuoteId())
            ->addFilter('entity_id', $order->getEntityId(), 'neq')
            ->addFilter('state', Order::STATE_PENDING_PAYMENT)
            ->create();

        foreach ($this->orderRepository->getList($searchCriteria)->getItems() as $previousOrder) {
            if ($previousOrder->getPayment()?->getMethod() === ConfigProvider::CODE) {
                $this->orderManagement->cancel((int)$previousOrder->getEntityId());
            }
        }
    }

    /**
     * Check whether a bol.checkout session was already started for the order.
     *
     * @param OrderInterface $order
     *
     * @return bool
     */
    private function hasCvbOrder(OrderInterface $order): bool
    {
        try {
            $this->cvbOrderRepository->getByMagentoOrderId((int)$order->getEntityId());

            return true;
        } catch (NoSuchEntityException) {
            return false;
        }
    }
}
