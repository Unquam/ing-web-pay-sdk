![ING Logo](https://ing.ro/dam/ingro/images/logo.svg)

# IngWebPay SDK for PHP

This SDK provides a simple PHP integration with the ING WebPay payment gateway. It allows you to initialize payment orders, send requests, and check order statuses.

## Features

- Set order details (amount, currency, description)
- Set customer details (contact and billing/shipping addresses)
- Send payment initialization requests
- Fetch and parse order status responses
- Simple error handling with `error_log`
- Optional custom configuration via constructor
- Compatible with PHP 7.4+

## Installation

You can install the SDK via Composer (assuming it's published on Packagist):

```bash
composer require your-vendor/ing-webpay-sdk
```

If you're using this SDK as a Laravel package, publish the configuration file using Artisan:
```bash
php artisan vendor:publish --provider="Unquam\IngWebPaySdk\IngWebPayServiceProvider" --tag="config"
```

## Composer Autoload

This SDK supports Composer autoloading.  
If you installed the package via Composer, it will be automatically loaded by Composer's autoloader, so you can use the classes without manual `require` or `include`.

## Configuration File Example (`config/ing-web-pay.php`)

```php
return [
    // ING WebPay API username (use 'TEST_API' for testing, replace with your production username)
    'username' => 'TEST_API',

    // ING WebPay API password (use test password during development, replace with your production password)
    'password' => 'q1w2e3r4Q!',

    // ... other configuration options
];
```

This will copy the default config file to your Laravel project's config/ing-web-pay.php, where you can customize credentials and URLs.

## Configuration
| Key              | Description                                        | Example                                |
| ---------------- |----------------------------------------------------| -------------------------------------- |
| `username`       | Your IngWebPay username                            | `"myuser"`                             |
| `password`       | Your IngWebPay password                            | `"mypassword"`                         |
| `return_url`     | URL where the user will be redirected after payment | `"https://your-site.com/return"`       |
| `post_action`    | WebPay payment initiation URL                      | `env('ING_TEST_INDICATOR', 1) ? 'https://securepay-uat.ing.ro/mpi_uat/rest/register.do' : 'https://securepay.ing.ro/mpi/rest/register.do'`  |
| `order_status`   | WebPay order status query URL                      | `env('ING_TEST_INDICATOR', 1) ? 'https://securepay-uat.ing.ro/mpi_uat/rest/getOrderStatusExtended.do' : 'https://securepay.ing.ro/mpi/rest/getOrderStatus.do'` |
| `certificate`    | Optional path to SSL certificate for verification  | `"/path/to/ChainBundle2.crt"`                  |
| `test_indicator` | Set to 1 to disable SSL verification for testing   | `1` (test) or `0` (production)         |

## Environment Variables (.env)

Configure the following variables in your `.env` file to set up the SDK properly:

```env
# ING WebPay environment indicator:
# Set to 1 to enable test mode (SSL verification disabled)
# Set to 0 to enable production mode (SSL verification enabled)
ING_TEST_INDICATOR=1

# URL where the user will be redirected after payment.
# Replace 'https://your-site.com/your-return-path' with your actual return URL.
ING_RETURN_URL='https://your-site.com/your-return-path'

# Protocol used by your application (http or https)
APP_PROTOCOL=http
```

## Example Usage

Here's a simple example demonstrating how to initialize a payment using the `IngWebPayGateway`:

```php
use Unquam\IngWebPaySdk\IngWebPayGateway;

$gateway = new IngWebPayGateway();

$response = $gateway->initializePayment([
    'order' => 'ORDER123',
    'amount' => 100.00,
    'currency' => '946', // RON currency code
    'description' => 'Order #ORDER123',
    'customerDetails' => [
        'email' => 'customer@example.com',
        'phone' => '40712345678',
        'billingInfo' => [
            'country' => 'RO',
            'city' => 'Bucharest',
            'postAddress' => '123 Main St',
        ],
    ],
]);

if ($response && isset($response['formUrl'], $response['orderId'])) {
// IMPORTANT: You **must** save the 'orderId' returned by the gateway
    // in your local database associated with your order.
    // This 'orderId' is required later to query the payment status via getOrderStatus().
    $order->transaction_id = $response['orderId'];
    $order->save();

    // The 'formUrl' contains the payment link where the customer should be redirected
    // to complete the transaction using their card.
    echo "Redirect user to payment form: " . $response['formUrl'];
} else {
    echo "Payment initialization failed.";
}
```

## Currency Format

- The `amount` you send when initializing a payment should be in decimal format (e.g., `100.00` for one hundred RON).
- However, the amount in the response from the gateway (e.g., in `getOrderStatus`) is represented in minor units (e.g., `100` means 1.00 RON, where 1 RON = 100 bani).

### Retrieving Payment Status
After the customer completes (or fails to complete) the payment, ING will redirect them to your `return_url`.
In that return handler, you can retrieve the payment status using the `getOrderStatus` method and take action accordingly.

Here's an example how to use `getOrderStatus`:
```php
use Unquam\IngWebPaySdk\IngWebPayGateway;

// Retrieve payment status from ING using stored orderId
$gateway = new IngWebPayGateway();

$status = $gateway->getOrderStatus($orderId);
```
## Payment Status Response Structure

When calling `getOrderStatus($orderId)`, the SDK returns an associative array with detailed information about the transaction.

### Example Response:

```php
[
    "errorCode" => "0", // "0" means success
    "errorMessage" => "Success",
    "orderNumber" => "55701045", // Internal gateway order number
    "orderStatus" => 2, // See status codes below
    "actionCode" => 0,
    "actionCodeDescription" => "Request processed successfully",
    "amount" => 100, // Amount in minor units (e.g., "100" means 1.00 RON)
    "currency" => "946", // ISO 4217 numeric code for RON
    "date" => 1752261834463, // Order creation timestamp (milliseconds)
    "paymentDate" => 1752261857681, // Payment confirmation timestamp (milliseconds)
    "orderDescription" => "Comanda nr. EP4MN8KX",
    "ip" => "127.0.0.1", // IP address of the customer
    "merchantOrderParams" => [ ... ],
    "attributes" => [ ... ],
    "cardAuthInfo" => [
        "expiration" => "1226",
        "cardholderName" => "CARDHOLDER",
        "pan" => "411111****1111",
        "approvalCode" => "AB1234",
    ],
    "orderBundle" => [ ... ],
    "reconciliationId" => "6d36d96b50814f62ae8e"
]
```

## Order Status Codes

The following table describes possible `orderStatus` values returned by `getOrderStatus()`:

| Code | Description (RO)                                                                      | Description (EN)                                |
|------|----------------------------------------------------------------------------------------|-------------------------------------------------|
| `0`  | Comanda înregistrată, dar neplătită                                                   | Order registered, but not yet paid              |
| `1`  | Plată preautorizată (pentru tranzacții în 2 pași)                                     | Pre-authorized payment (for 2-step transactions)|
| `2`  | Tranzacție autorizată                                                                 | Payment authorized (successful)                |
| `3`  | Tranzacție anulată                                                                    | Transaction canceled                            |
| `4`  | Tranzacție reversată                                                                  | Transaction refunded                            |
| `5`  | Tranzacție inițiată prin sistemul ACS al băncii emitente                              | Transaction initiated via the issuer bank's ACS |
| `6`  | Tranzacție respinsă                                                                   | Transaction failed                              |

## Test Card Details

You can use the following test card details when running in test mode (`ING_TEST_INDICATOR=1`).  
These cards are **only valid in the test environment** provided by ING WebPay.

| Field             | Value                |
|------------------|----------------------|
| Card Number      | `4662861119116040` |
| Expiration Date  | Any future date (e.g. `05/29`) |
| CVV              | `203`                |
| Cardholder Name  | Any name (e.g. `ING VISA`)     |

> ⚠️ Make sure you're using the **UAT endpoint**:  
> `https://securepay-uat.ing.ro/mpi_uat/rest/register.do`  
> by setting `ING_TEST_INDICATOR=1` in your `.env` file.


## License
> **Disclaimer:** This software is provided "as is" without any warranty.  
> The authors are not responsible for any damages or losses arising from its use.
> 
This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
