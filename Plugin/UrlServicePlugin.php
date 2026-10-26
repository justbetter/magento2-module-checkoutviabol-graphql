<?php

namespace JustBetter\CheckoutViaBolGraphQl\Plugin;

use Bol\CheckoutViaBol\Service\UrlService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\OrderFactory;
use Magento\SalesGraphQl\Model\Order\Token;
use Magento\Store\Model\ScopeInterface;

class UrlServicePlugin
{
    /**
     * UrlServicePlugin constructor.
     *
     * @param RequestInterface     $request
     * @param OrderFactory         $orderFactory
     * @param Token                $token
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly OrderFactory $orderFactory,
        private readonly Token $token,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Redirect to the configured success path, with a token for guest orders.
     *
     * @param UrlService $subject
     * @param string     $result
     *
     * @return string
     */
    public function afterGetCheckoutSuccessUrl(UrlService $subject, string $result): string
    {
        $order = $this->getOrder();
        $path = $this->getPath('success_path', $order);
        if (!$path) {
            return $result;
        }

        $url = '/' . $path;
        if (!$order instanceof OrderInterface || $order->getCustomerId()) {
            return $url;
        }

        $incrementId = $order->getIncrementId();
        $billingAddress = $order->getBillingAddress();
        if (!$incrementId || !$billingAddress) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'token' => $this->token->encrypt(
                $incrementId,
                (string)$billingAddress->getEmail(),
                (string)$billingAddress->getLastname(),
            ),
        ]);
    }

    /**
     * Use the configured cancel path as the error path.
     *
     * @param UrlService $subject
     * @param string     $result
     *
     * @return string
     */
    public function afterGetCvbOrderErrorPath(UrlService $subject, string $result): string
    {
        return $this->getPath('cancel_path', $this->getOrder()) ?: $result;
    }

    /**
     * Redirect to the configured cancel path on errors.
     *
     * @param UrlService $subject
     * @param string     $result
     *
     * @return string
     */
    public function afterGetCvbOrderErrorUrl(UrlService $subject, string $result): string
    {
        $path = $this->getPath('cancel_path', $this->getOrder());

        return $path ? '/' . $path : $result;
    }

    /**
     * Get a configured path for the order's store, without surrounding slashes.
     *
     * @param string              $field
     * @param OrderInterface|null $order
     *
     * @return string
     */
    private function getPath(string $field, ?OrderInterface $order): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'payment/cvb_graphql/' . $field,
            ScopeInterface::SCOPE_STORE,
            $order?->getStoreId()
        ), '/');
    }

    /**
     * Get the order from the orderIncrementId request parameter.
     *
     * @return OrderInterface|null
     */
    private function getOrder(): ?OrderInterface
    {
        $incrementId = (string)$this->request->getParam('orderIncrementId');
        if (!$incrementId) {
            return null;
        }

        $order = $this->orderFactory->create()->loadByIncrementId($incrementId);

        return $order->getId() ? $order : null;
    }
}
