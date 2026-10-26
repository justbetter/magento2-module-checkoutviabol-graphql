# Magento 2 bol.checkout GraphQL

Adds GraphQL support for the [bol.checkout](https://cvb.composer.bol.com/) payment method (`bol/magento2-module-checkoutviabol`).

## Features

- `cvb_redirect_url` on `CustomerOrder`, which starts and confirms a bol.checkout session for a pending bol.checkout order and returns the url to redirect the customer to
- Configurable redirects back to your frontend after paying or cancelling

## Installation

The bol.checkout module is installed from bol's own Composer repository, so make sure the bol repository is in the `repositories` section of your `composer.json`:

```json
"repositories": {
  "bol": {
    "type": "composer",
    "url": "https://cvb.composer.bol.com/"
  }
}
```

Or add it with `composer config repositories.bol composer https://cvb.composer.bol.com/`.

- `composer require justbetter/magento2-module-checkoutviabol-graphql`
- `bin/magento module:enable JustBetter_CheckoutViaBolGraphQl`
- `bin/magento setup:upgrade`
- `bin/magento setup:di:compile`

## Configuration

bol only receives paths (`/cvb/...`), the domain is configured on your bol merchant account. Point it to your frontend and make sure `/cvb/*` on that domain is passed to Magento.

Configure where the customer is redirected to in `Stores > Configuration > Sales > Payment Methods > Bol.checkout GraphQL`:

| Field        | Description                                                                                                                                                        |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Success path | Path on your frontend after a successful payment, e.g. `checkout/success`. Guest orders get a `token` query parameter, which can be used with `guestOrderByToken`. |
| Cancel path  | Path on your frontend when the payment is cancelled or fails. Use this to reactivate the cart.                                                                     |

These are paths, not urls, because bol only accepts paths. The customer always returns to the domain configured at bol. When left empty, the default bol.checkout urls are used.

## Usage

Request `cvb_redirect_url` when placing the order and redirect the customer to it:

```graphql
mutation {
  placeOrder(input: { cart_id: "..." }) {
    orderV2 {
      number
      cvb_redirect_url
    }
  }
}
```

`cvb_redirect_url` is only filled once per order, for orders paid with bol.checkout that are still pending payment.
