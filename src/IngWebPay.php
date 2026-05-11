<?php

namespace Unquam\IngWebPaySdk;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Unquam\IngWebPaySdk\Constants\CountryCodes;
use Unquam\IngWebPaySdk\Constants\ConvertTable;

class IngWebPay
{
    private string $_amount = "0.00"; // total order value
    private string $_currency = "946"; // RON code по ISO 4217
    private string $_order = ""; // unique order ID
    private string $_description = ""; // description of the order
    private string $_jsonParams = '{"FORCE_3DS2":true}';

    // Configuration properties
    protected ?string $_username = null;
    protected ?string $_password = null;
    protected ?string $_email = null;
    protected ?string $_return_url = null;
    protected ?string $_post_action = null;
    protected ?string $_order_status = null;
    protected ?string $_form_url = null;
    protected ?string $_orderBundle = null;
    protected ?string $_language = null;
    protected ?string $_certificate = null;
    protected ?string $_protocol = null;
    protected ?int $_test_indicator = 0;
    protected ?int $_check_amount = 0;
    protected array $convertTable = ConvertTable::MAP;
    protected array $countryCodeMap;

    /**
     * Constructor initializes the SDK with configuration options.
     *
     * It loads default configuration values from the application's config files,
     * then overrides them with any user-provided values.
     * Only allowed configuration keys are accepted.
     *
     * @param array $config Optional configuration overrides.
     * @throws \JsonException If JSON operations fail (used elsewhere in class).
     */
    public function __construct(array $config = [])
    {
        // Set default configuration values from config files
        $defaults = [
            'test_indicator' => config('ing-web-pay.test_indicator'),
            'username' => config('ing-web-pay.username'),
            'password' => config('ing-web-pay.password'),
            'return_url' => config('ing-web-pay.return_url'),
            'post_action' => config('ing-web-pay.post_action'),
            'order_status' => config('ing-web-pay.order_status'),
            'check_amount' => config('ing-web-pay.check_amount'),
            'language' => config('ing-web-pay.language'),
            'protocol' => config('ing-web-pay.protocol'),
            'certificate' => config('ing-web-pay.certificate'),
        ];

        // Allowed configuration keys to prevent unexpected properties
        $allowedConfig = array_keys($defaults);

        // Merge default config with any user overrides
        $finalConfig = array_merge($defaults, $config);

        // Assign properties dynamically with trimming strings
        foreach ($finalConfig as $key => $value) {
            if (in_array($key, $allowedConfig, true)) {
                $this->{'_' . $key} = is_string($value) ? trim($value) : $value;
            }
        }

        // Initialize the country code map
        $this->countryCodeMap = CountryCodes::COUNTRY_CODES;
    }

    /**
     * Sets the amount in the smallest currency unit (e.g., cents for USD, RON).
     *
     * The input amount can be a float or string representing the main currency unit
     * (e.g., "100.00" for 100 RON). The method converts it to minor units by multiplying by 100.
     *
     * @param float|string $amount Amount in main currency unit (e.g., "100.00" for 100 RON).
     * @return void
     * @throws InvalidArgumentException If the amount is negative.
     */
    public function setAmount(float|string $amount): void
    {
        // Ensure the amount is a valid float value
        $amountFloat = (float) $amount;

        if ($amountFloat < 0) {
            throw new InvalidArgumentException('Amount cannot be negative.');
        }

        // Format the amount to two decimal places and convert to string
        $formattedAmount = number_format($amountFloat, 2, '.', '');

        // Multiply by 100 to convert to minor units (e.g., cents)
        $minorUnits = (int) round($formattedAmount * 100);

        // Set the amount property
        $this->_amount = (string) $minorUnits;
    }

    /**
     * Sets the currency code using the ISO 4217 numeric standard.
     *
     * The currency should be provided as a string containing the numeric code,
     * for example "946" for Romanian Leu (RON).
     *
     * @param string $currency Numeric currency code as string.
     * @return void
     */
    public function setCurrency(string $currency): void
    {
        $this->_currency = $currency;
    }

    /**
     * Sets the unique order ID.
     *
     * This method assigns a unique identifier string to the order.
     * It is used to track and reference the specific transaction.
     *
     * @param string $order Unique identifier for the order.
     * @return void
     */
    public function setOrder(string $order): void
    {
        $this->_order = $order;
    }

    /**
     * Sets the email address for the order.
     *
     * This method sanitizes the provided email address by removing any invalid characters
     * and ensuring it is in a valid format. It uses PHP's filter_var function with
     * FILTER_SANITIZE_EMAIL to clean the input.
     *
     * @param string $email Email address associated with the order.
     * @return void
     */
    public function setEmail(string $email): void {
        $this->_email = filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sets the order description.
     *
     * This method sanitizes the provided description string by removing any HTML tags
     * and trimming leading and trailing whitespace. This ensures the description
     * is safe and clean for further processing or display, preventing injection
     * of unwanted HTML or scripts.
     *
     * @param string $description Description of the order.
     * @return void
     */
    public function setDescription(string $description): void
    {
        // Sanitize description by trimming whitespace and stripping HTML tags
        $this->_description = trim(strip_tags($description));
    }

    /**
     * Sets the URL for the payment form.
     *
     * This method validates the provided URL to ensure it is a properly formatted URL.
     * If the URL is invalid, it throws an InvalidArgumentException to prevent
     * assigning a malformed URL, which could cause errors during payment processing.
     * On success, it assigns the URL to the internal property used for the payment form.
     *
     * @param string $formUrl The URL to the payment form.
     * @throws InvalidArgumentException If the URL is not valid.
     * @return void
     */
    public function setFormUrl(string $formUrl): void {
        if (filter_var($formUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Invalid URL format.');
        }
        $this->_form_url = $formUrl;
    }

    /**
     * Cleans and formats a string for use in the payment system.
     *
     * This method performs several transformations on the input string:
     * 1. Trims leading and trailing whitespace.
     * 2. Replaces special characters according to a predefined conversion table,
     *    typically converting symbols and accented characters to simpler ASCII equivalents.
     * 3. Attempts to transliterate the UTF-8 encoded string to ASCII to remove non-ASCII characters.
     * 4. Optionally removes all non-alphanumeric characters except hyphens and spaces if `$clean` is true.
     * 5. Limits the length of the resulting string to `$limit` characters if a limit is specified,
     *    using multibyte-safe trimming if available.
     *
     * This cleaning process ensures that strings are safe and properly formatted
     * for transmission and processing by the payment system.
     *
     * @param string $string The input string to clean.
     * @param bool $clean If true, removes all non-alphanumeric characters except hyphens and spaces.
     * @param int $limit Maximum length of the returned string; 0 means no limit.
     * @return string The cleaned and formatted string.
     */
    private function cleanString(string $string, bool $clean = false, int $limit = 0): string
    {
        // Trim leading and trailing whitespace
        $string = trim($string);

        // Replace characters using conversion table (e.g. special chars to ASCII)
        $string = strtr($string, $this->convertTable);

        // Transliterate UTF-8 string to ASCII
        $translit = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $string);
        if ($translit !== false) {
            $string = $translit;
        }

        // Remove all non-alphanumeric characters (except hyphens and spaces) if requested
        if ($clean) {
            $string = preg_replace('/[^a-zA-Z0-9\- ]+/', '', $string);
        }

        // Limit string length if limit is set
        if ($limit > 0) {
            if (function_exists('mb_strimwidth')) {
                $string = mb_strimwidth($string, 0, $limit);
            } else {
                $string = substr($string, 0, $limit);
            }
        }

        return $string;
    }

    /**
     * Cleans and formats a phone number string.
     *
     * This method removes all non-digit characters from the input phone number,
     * then applies specific formatting rules:
     * - If the cleaned number is exactly 10 digits and starts with '0',
     *   the leading '0' is replaced with '4' (e.g., Romanian phone numbers).
     *
     * After formatting, it validates the phone number against a pattern that requires:
     * - The first digit is between 1 and 9 (no leading zero)
     * - The total length is between 3 and 12 digits
     *
     * If the number passes validation, the cleaned and formatted phone number is returned.
     * Otherwise, an empty string is returned indicating an invalid phone number.
     *
     * @param string $phoneNumber The raw phone number input.
     * @return string The cleaned, formatted phone number or empty string if invalid.
     */
    private function cleanPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-digit characters
        $phone = preg_replace('/\D/', '', $phoneNumber);

        // If phone starts with '0' and length is exactly 10, replace leading '0' with '4'
        if (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '4' . $phone;
        }

        // Validate phone number pattern: starts with non-zero digit and length between 3 and 12 digits
        if (preg_match('/^[1-9][0-9]{2,11}$/', $phone)) {
            return $phone;
        }

        // Return empty string if phone number is invalid
        return '';
    }

    /**
     * Prepares and sets customer details for the order.
     *
     * This method extracts and cleans customer contact and address details from the provided array.
     * It validates the presence and correctness of the billing country code against the predefined
     * country codes map. Shipping address details are optionally included if provided and valid.
     *
     * All strings are sanitized and truncated according to limits suitable for the payment system.
     * Finally, the method encodes the cleaned data as JSON and assigns it to the internal order bundle property.
     *
     * @param array $customerDetails Array containing customer contact and address details.
     * @return $this Returns the current instance for method chaining.
     * @throws InvalidArgumentException If the billing country code is missing or invalid.
     * @throws \JsonException If JSON encoding fails.
     */
    public function setCustomerDetails(array $customerDetails): self
    {
        // Helper function to get values from the array with a default fallback
        $get = static fn($array, $key, $default = '') => $array[$key] ?? $default;

        // Extract customer contact details
        $email = $get($customerDetails, 'email', '');
        $phone = $this->cleanPhoneNumber($get($customerDetails, 'phone', ''));
        $homePhone = $this->cleanPhoneNumber($get($customerDetails, 'homePhone', ''));
        $workPhone = $this->cleanPhoneNumber($get($customerDetails, 'workPhone', ''));
        $contact = $get($customerDetails, 'contact', '');

        // Billing address details
        $billingInfo = $get($customerDetails, 'billingInfo', []);
        $billingCountry = $get($billingInfo, 'country', null);
        $billingCity = $get($billingInfo, 'city', '');
        $billingAddress1 = $get($billingInfo, 'postAddress', 'NA');
        $billingAddress2 = $get($billingInfo, 'postAddress2', 'NA');
        $billingAddress3 = $get($billingInfo, 'postAddress3', 'NA');
        $billingPostcode = $get($billingInfo, 'postalCode', '');
        $billingState = $get($billingInfo, 'state', '');

        // Shipping address details
        $deliveryInfo = $get($customerDetails, 'deliveryInfo', []);
        $shippingCountry = $get($deliveryInfo, 'country', null);
        $shippingCity = $get($deliveryInfo, 'city', '');
        $shippingAddress1 = $get($deliveryInfo, 'postAddress', 'NA');
        $shippingAddress2 = $get($deliveryInfo, 'postAddress2', 'NA');
        $shippingAddress3 = $get($deliveryInfo, 'postAddress3', 'NA');
        $shippingPostcode = $get($deliveryInfo, 'postalCode', '');
        $shippingState = $get($deliveryInfo, 'state', '');

        // Validate billing country code
        if ($billingCountry === null || !isset($this->countryCodeMap[$billingCountry])) {
            throw new InvalidArgumentException('Invalid or missing billing country code.');
        }

        // Prepare customer data array with cleaned values
        $customerData = [
            'email' => $this->cleanString($email, false, 254),
            'phone' => $this->cleanString($phone, true, 12),
            'homePhone' => $this->cleanString($homePhone, true, 12),
            'workPhone' => $this->cleanString($workPhone, true, 12),
            'contact' => $this->cleanString($contact, true, 40),
            'billingInfo' => [
                'country' => $this->countryCodeMap[$billingCountry],
                'city' => $this->cleanString($billingCity, true, 40),
                'postAddress' => $this->cleanString($billingAddress1, true, 50),
                'postAddress2' => $this->cleanString($billingAddress2, true, 50),
                'postAddress3' => $this->cleanString($billingAddress3, true, 50),
                'postalCode' => $this->cleanString($billingPostcode, true, 16),
                'state' => $this->cleanString($billingState, true, 3),
            ],
        ];

        // Include shipping details if valid
        if ($shippingCountry !== null && isset($this->countryCodeMap[$shippingCountry])) {
            $customerData['deliveryInfo'] = [
                'country' => $this->countryCodeMap[$shippingCountry],
                'city' => $this->cleanString($shippingCity, true, 40),
                'postAddress' => $this->cleanString($shippingAddress1, true, 50),
                'postAddress2' => $this->cleanString($shippingAddress2, true, 50),
                'postAddress3' => $this->cleanString($shippingAddress3, true, 50),
                'postalCode' => $this->cleanString($shippingPostcode, true, 16),
                'state' => $this->cleanString($shippingState, true, 3),
            ];
        }

        // Assign the prepared data as JSON to the order bundle property
        $this->_orderBundle = json_encode(['customerDetails' => $customerData], JSON_THROW_ON_ERROR);

        return $this;
    }

    /**
     * Validates customer details to ensure required fields are present and formats are correct.
     *
     * This method checks for the presence of mandatory fields (e.g., email) in the
     * provided customer details array. It also validates the format of these fields,
     * such as verifying that the email address is valid.
     *
     * If any required fields are missing or invalid, the method returns an array of error messages
     * describing these issues. If all validations pass, it returns an empty array.
     *
     * @param array $customerDetails The customer details to validate.
     * @return array<string> Array of validation error messages; empty if all fields are valid.
     */
    private function validateCustomerDetails(array $customerDetails): array
    {
        $errors = [];

        // List of required fields to check
        $requiredFields = ['email'];

        // Check for missing required fields
        foreach ($requiredFields as $field) {
            if (empty($customerDetails[$field])) {
                $errors[] = "customerDetails.{$field} is required";
            }
        }

        // Validate email format if email is provided
        if (!empty($customerDetails['email']) && !filter_var($customerDetails['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'customerDetails.email is invalid';
        }

        return $errors;
    }

    /**
     * Checks if the required fields are present and non-empty in the given data array.
     *
     * This method validates that all specified required fields exist in the data array
     * and are not empty after trimming whitespace. If any required field is missing or empty,
     * an error message is added to the returned list.
     *
     * @param array $data Data array to validate (e.g., customerDetails, deliveryInfo, billingInfo).
     * @param array $requiredFields List of field names that are mandatory.
     * @param string $prefix Prefix used in error messages to indicate the data section (e.g., "customerDetails").
     * @return array<string> List of error messages for fields that are missing or empty.
     */
    private function validateRequired(array $data, array $requiredFields, string $prefix): array
    {
        $errors = [];

        foreach ($requiredFields as $field) {
            // Check if field is missing or its trimmed value is empty
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                $errors[] = "{$prefix}.{$field} is required";
            }
        }

        return $errors;
    }

    /**
     * Returns an associative array of POST fields required to initiate the payment order request.
     *
     * This method collects all necessary parameters such as authentication credentials,
     * order details, payment amount, URLs, language settings, and any additional JSON parameters,
     * formatting them as key-value pairs suitable for sending in a HTTP POST request.
     *
     * @return array<string, string|null> Key-value pairs representing POST data for the payment request.
     */
    public function getPostFields(): array
    {
        $fields = [
            'userName'     => $this->_username,
            'password'     => $this->_password,
            'currency'     => $this->_currency,
            'description'  => $this->_description,
            'email'        => $this->_email,
            'amount'       => $this->_amount,
            'returnUrl'    => $this->_return_url,
            'language'     => $this->_language,
            'jsonParams'   => $this->_jsonParams
        ];

        if (!is_null($this->_orderBundle)) {
            $fields['orderBundle'] = $this->_orderBundle;
        }

        return $fields;
    }

    /**
     * Returns an array of POST fields necessary to query the status of an existing order.
     *
     * This includes authentication credentials and the unique order identifier.
     * The returned data can be used to send a request to the payment provider
     * to retrieve the current status of the specified order.
     *
     * @return array<string, string|null> Key-value pairs for order status request.
     */
    public function getOrderStatusFields(): array
    {
        return [
            'userName' => $this->_username,
            'password' => $this->_password,
            'orderId'  => $this->_order,
        ];
    }

    /**
     * Sends a POST request to the payment gateway with the order data.
     *
     * Uses Guzzle HTTP client to send the request with the prepared POST fields.
     * SSL certificate verification is conditional based on the test indicator:
     * - In production (test_indicator = 0), SSL verification is enabled using the provided certificate or default.
     * - In test mode, SSL verification is disabled.
     *
     * @return array<string, mixed> Returns an array with keys:
     *   - 'result' => string Response body content or error message.
     *   - 'success' => bool Indicates if the request was successful.
     */
    public function sendRequest(): array
    {
        $postFields = $this->getPostFields();

        // Options for the POST request
        $options = [
            RequestOptions::FORM_PARAMS => $postFields,
            RequestOptions::ALLOW_REDIRECTS => true,
            'http_errors' => false, // Do not throw exceptions on HTTP protocol errors
        ];

        // Configure SSL verification based on environment
        if ($this->_test_indicator === 0) {
            // Production environment: verify SSL certificate
            $options['verify'] = $this->_certificate ?: true; // use the certificate if provided, otherwise default to true
        } else {
            // Test environment: disable SSL certificate verification
            $options['verify'] = false;
        }

        // Initialize Guzzle HTTP client with the base URI (payment gateway URL)
        $client = new Client([
            'base_uri' => $this->_post_action
        ]);

        try {
            // Perform POST request to the base URI with given options
            $response = $client->post('', $options);

            // Get response body content as string
            $body = $response->getBody()->getContents();

            return ['result' => $body, 'success' => true];
        } catch (RequestException $e) {
            // Return error message on exception
            return ['result' => $e->getMessage(), 'success' => false];
        }
    }

    /**
     * Fetches the order status from the ING WebPay system.
     *
     * Sends a POST request with credentials and order ID to retrieve the current status of an order.
     * SSL verification behavior depends on the test indicator:
     * - Production mode (test_indicator = 0): verifies SSL with the provided certificate or default.
     * - Test mode: disables SSL verification.
     *
     * Handles exceptions from HTTP requests and JSON decoding by logging errors and returning null.
     *
     * @return array|null Returns the decoded response as an associative array on success, or null on failure.
     * @throws GuzzleException Propagates Guzzle exceptions if any occur outside the try-catch blocks.
     */
    public function fetchOrderStatusRaw(): ?array
    {
        // Prepare the POST fields necessary to query order status: user credentials and order ID
        $postFields = $this->getOrderStatusFields();

        // Create a new Guzzle HTTP client with configured base URI and SSL verification based on environment
        $client = new Client([
            'base_uri' => $this->_order_status,
            'verify' => $this->_test_indicator === 0 ? $this->_certificate ?? true : false,
            'http_errors' => false,
        ]);

        try {
            // Send POST request with form parameters and allow HTTP redirects if any
            $response = $client->post('', [
                RequestOptions::FORM_PARAMS => $postFields,
                RequestOptions::ALLOW_REDIRECTS => true,
            ]);

            // Read the response body contents as string
            $body = $response->getBody()->getContents();
        } catch (RequestException $e) {
            // Log network or request errors encountered during the HTTP call
            error_log('ING WebPay system error: ' . $e->getMessage());

            // Return null to signal failure in fetching the order status
            return null;
        }

        try {
            // Decode the JSON response body into an associative array, throwing on error
            return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            // Log JSON parsing errors indicating invalid or malformed response
            error_log('ING WebPay system error: invalid JSON response in fetchOrderStatusRaw.');

            // Return null as the response could not be properly interpreted
            return null;
        }
    }

    /**
     * Processes the response returned by the ING WebPay API.
     *
     * Attempts to decode the JSON response and checks for expected keys.
     * - If a valid `formUrl` is found, sets it internally and returns an array with `orderId` and `formUrl`.
     * - If an error code and message are present, logs the error and returns null.
     * - Handles invalid JSON by logging and returning null.
     *
     * @param array $response The raw response array, expected to contain a 'result' key with JSON string.
     * @return array|null Returns an associative array with 'orderId' and 'formUrl' if successful, or null on failure.
     * @throws \JsonException If JSON decoding fails (with JSON_THROW_ON_ERROR).
     */
    public function processResponse(array $response): ?array
    {
        $decoded = json_decode($response['result'] ?? '', true, 512, JSON_THROW_ON_ERROR);

        // Although JSON_THROW_ON_ERROR throws on error, the next check is extra safety
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ING WebPay system error: invalid JSON response.');
            return null;
        }

        if (isset($decoded['formUrl'])) {
            // Store the form URL internally for further use
            $this->setFormUrl($decoded['formUrl']);

            // Return the orderId and formUrl for client usage
            return [
                'orderId' => $decoded['orderId'] ?? null,
                'formUrl' => $decoded['formUrl'],
            ];
        }

        if (isset($decoded['errorCode'], $decoded['errorMessage'])) {
            // Log API error details returned by the payment gateway
            error_log(sprintf(
                'ING WebPay system error: error initializing transaction. Code: %s, Message: %s',
                $decoded['errorCode'],
                $decoded['errorMessage']
            ));
            return null;
        }

        // Fallback for unknown error cases
        error_log('ING WebPay system error: unknown error during processing #return #API #response #registerorder.');
        return null;
    }
}
