<?php

namespace JustBetter\CheckoutViaBolGraphQl\Model\Resolver;

use Bol\CheckoutViaBol\Exception\CvbApiException;
use Bol\CheckoutViaBol\Service\CvbAvailabilityService;
use Bol\CheckoutViaBol\Service\CvbService;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class StartCvbCheckout implements ResolverInterface
{
    /**
     * StartCvbCheckout constructor.
     *
     * @param GetCartForUser          $getCartForUser
     * @param CvbAvailabilityService  $availabilityService
     * @param CvbService              $cvbService
     * @param StoreManagerInterface   $storeManager
     * @param ScopeConfigInterface    $scopeConfig
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly CvbAvailabilityService $availabilityService,
        private readonly CvbService $cvbService,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly CartRepositoryInterface $cartRepository,
    ) {
    }

    /**
     * Start a bol.checkout fill-in session for the guest cart and return the redirect url.
     *
     * @param Field            $field
     * @param ContextInterface $context
     * @param ResolveInfo      $info
     * @param mixed[]|null     $value
     * @param mixed[]|null     $args
     *
     * @return string
     *
     * @throws GraphQlInputException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): string
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $cart = $this->getCartForUser->execute((string)($args['cart_id'] ?? ''), $context->getUserId(), $storeId);

        if ($cart->getCustomerId() || !$cart->hasItems() || !$this->availabilityService->isCvbAvailable($storeId)) {
            throw new GraphQlInputException(__('bol.checkout is not available for this cart.'));
        }

        if (!$this->scopeConfig->getValue('payment/cvb_graphql/checkout_path', ScopeInterface::SCOPE_STORE, $storeId)) {
            throw new GraphQlInputException(__('The bol.checkout return path is not configured.'));
        }

        try {
            $cvbSession = $this->cvbService->createCvbSession($cart, (int)($args['action_identifier'] ?? 2));
        } catch (CvbApiException) {
            throw new GraphQlInputException(__('bol.checkout is currently unavailable.'));
        }

        $cart->setData('cvb_session_id', $cvbSession['id']);
        $this->cartRepository->save($cart);

        return $cvbSession['redirectUrl'];
    }
}
