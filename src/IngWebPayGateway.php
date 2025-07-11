<?php

namespace Unquam\IngWebPaySdk;

class IngWebPayGateway
{
    protected IngWebPay $ingWebPay;

    /**
     * Constructor.
     *
     * Accepts an optional IngWebPay SDK instance.
     * If none is provided, a new instance will be created.
     *
     * @param IngWebPay|null $ingWebPay Optional SDK instance.
     */
    public function __construct(?IngWebPay $ingWebPay = null)
    {
        $this->ingWebPay = $ingWebPay ?? new IngWebPay();
    }

    /**
     * Initializes a payment with the provided data.
     *
     * Sets amount, currency, description, and optionally customer details on the SDK.
     * Sends the payment request and processes the response.
     * On failure, logs errors with error_log and returns null.
     *
     * @param array $data Payment data with keys:
     *                    - 'amount' (float|string)
     *                    - 'currency' (string)
     *                    - 'description' (string, optional)
     *                    - 'customerDetails' (array, optional)
     * @return array|null Returns array with 'orderId' and 'formUrl' on success, or null on failure.
     */
    public function initializePayment(array $data): ?array
    {
        try {
            // Set payment details on the SDK
            $this->ingWebPay->setAmount($data['amount']);
            $this->ingWebPay->setCurrency($data['currency']);
            $this->ingWebPay->setDescription($data['description'] ?? '');

            // Set customer details if provided
            if (!empty($data['customerDetails'])) {
                $this->ingWebPay->setCustomerDetails($data['customerDetails']);
            }

            // Send payment request
            $response = $this->ingWebPay->sendRequest();

            // Check if request succeeded
            if (!$response['success']) {
                error_log('ING WebPay error: ' . $response['result']);
                return null;
            }

            // Process and return payment response data
            return $this->ingWebPay->processResponse($response);
        } catch (\Throwable $e) {
            error_log('ING WebPay exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Retrieves the status of an order by its ID.
     *
     * Sets the order ID on the SDK and fetches order status.
     * Returns the status array on success or null on failure.
     * Logs errors via error_log.
     *
     * @param string $orderId The unique identifier of the order.
     * @return array|null The order status data or null if failed.
     */
    public function getOrderStatus(string $orderId): ?array
    {
        try {
            // Assign order ID
            $this->ingWebPay->setOrder($orderId);

            // Fetch raw order status
            $response = $this->ingWebPay->fetchOrderStatusRaw();

            // Check for empty or invalid response
            if (!$response) {
                error_log('ING WebPay error: empty response from getOrderStatus.');
                return null;
            }

            // Return decoded status response
            return $response;
        } catch (\Throwable $e) {
            error_log('ING WebPay exception in getOrderStatus: ' . $e->getMessage());
            return null;
        }
    }
}