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
    public function __construct(
        private readonly RequestInterface $request,
        private readonly OrderFactory $orderFactory,
        private readonly Token $token,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    public function afterGetCheckoutSuccessUrl(UrlService $subject, string $result): string
    {
        $order = $this->getOrder();
        $path = $this->getPath('success_path', $order);
        if (!$path) {
            return $result;
        }

        $url = '/' . $path;
        if (!$order || $order->getCustomerId()) {
            return $url;
        }

        return $url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'token' => $this->token->encrypt(
                $order->getIncrementId(),
                (string)$order->getBillingAddress()->getEmail(),
                (string)$order->getBillingAddress()->getLastname(),
            ),
        ]);
    }

    public function afterGetCvbOrderErrorPath(UrlService $subject, string $result): string
    {
        return $this->getPath('cancel_path', $this->getOrder()) ?: $result;
    }

    public function afterGetCvbOrderErrorUrl(UrlService $subject, string $result): string
    {
        $path = $this->getPath('cancel_path', $this->getOrder());

        return $path ? '/' . $path : $result;
    }

    private function getPath(string $field, ?OrderInterface $order): string
    {
        return trim((string)$this->scopeConfig->getValue(
            'payment/cvb_graphql/' . $field,
            ScopeInterface::SCOPE_STORE,
            $order?->getStoreId()
        ), '/');
    }

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
