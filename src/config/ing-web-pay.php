<?php

return [
    'test_indicator' => env('ING_TEST_INDICATOR', 1), // 1 for test environment, 0 for production
    'username' => 'TEST_API', // Test username, replace with actual username in production
    'password' => 'q1w2e3r4Q!', // Test credentials, replace with actual credentials in production
    'return_url' => env('ING_RETURN_URL'), // URL to return after payment processing
    'post_action' => env('ING_TEST_INDICATOR', 1)
        ? 'https://securepay-uat.ing.ro/mpi_uat/rest/register.do'
        : 'https://securepay.ing.ro/mpi/rest/register.do', // URL for payment processing

    'order_status' => env('ING_TEST_INDICATOR', 1)
        ? 'https://securepay-uat.ing.ro/mpi_uat/rest/getOrderStatusExtended.do'
        : 'https://securepay.ing.ro/mpi/rest/getOrderStatus.do', // URL for checking order status
    'check_amount' => 1, // 1 to check amount, 0 otherwise
    'language' => 'ro', // 'ro' for Romanian, 'en' for English
    'protocol' => env('APP_PROTOCOL', 'https://'),
    'certificate' =>  base_path('ing_web_pay_crt/ChainBundle2.crt'), // Path to the certificate file
];
