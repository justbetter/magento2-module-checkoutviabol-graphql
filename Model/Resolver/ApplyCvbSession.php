<?php

namespace JustBetter\CheckoutViaBolGraphQl\Model\Resolver;

use Bol\CheckoutViaBol\Controller\Session\Checkout;
use Bol\CheckoutViaBol\Exception\CvbApiException;
use Bol\CheckoutViaBol\Service\CvbService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\QuoteGraphQl\Model\Cart\GetCartForUser;
use Magento\Store\Model\StoreManagerInterface;
use ReflectionException;
use ReflectionMethod;

class ApplyCvbSession implements ResolverInterface
{
    /**
     * ApplyCvbSession constructor.
     *
     * @param GetCartForUser          $getCartForUser
     * @param CvbService              $cvbService
     * @param Checkout                $checkoutController
     * @param CartRepositoryInterface $cartRepository
     * @param StoreManagerInterface   $storeManager
     */
    public function __construct(
        private readonly GetCartForUser $getCartForUser,
        private readonly CvbService $cvbService,
        private readonly Checkout $checkoutController,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Fill the guest cart with the details chosen in the bol.checkout fill-in session.
     *
     * @param Field            $field
     * @param ContextInterface $context
     * @param ResolveInfo      $info
     * @param mixed[]|null     $value
     * @param mixed[]|null     $args
     *
     * @return mixed[]
     *
     * @throws GraphQlInputException
     * @throws ReflectionException
     */
    public function resolve(Field $field, $context, ResolveInfo $info, ?array $value = null, ?array $args = null): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $cart = $this->getCartForUser->execute((string)($args['cart_id'] ?? ''), $context->getUserId(), $storeId);

        if ($cart->getCustomerId()) {
            throw new GraphQlInputException(__('bol.checkout is not available for this cart.'));
        }

        $sessionId = (string)($args['session_id'] ?? '');

        try {
            $cvbSession = $this->cvbService->getCvbSession($sessionId, (string)($args['nonce'] ?? ''), $storeId);
        } catch (CvbApiException) {
            throw new GraphQlInputException(__('Could not retrieve the bol.checkout session.'));
        }

        // Use internal bol function to fill quote data
        (new ReflectionMethod($this->checkoutController, 'preFillQuote'))->invoke($this->checkoutController, $cvbSession, $cart);

        $cart->setData('cvb_session_id', $sessionId);
        $cart->getShippingAddress()->setCollectShippingRates(true);
        $cart->setTotalsCollectedFlag(false)->collectTotals();
        $this->cartRepository->save($cart);

        return ['model' => $cart];
    }
}
