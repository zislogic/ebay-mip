<?php

declare(strict_types=1);

return [
    'sftp' => [
        'sandbox' => [
            'host' => env('EBAY_MIP_SANDBOX_SFTP_HOST', 'mip.sandbox.ebay.com'),
            'port' => (int) env('EBAY_MIP_SANDBOX_SFTP_PORT', 22),
        ],
        'production' => [
            'host' => env('EBAY_MIP_PRODUCTION_SFTP_HOST', 'mip.ebay.com'),
            'port' => (int) env('EBAY_MIP_PRODUCTION_SFTP_PORT', 22),
        ],
    ],

    'paths' => [
        'order_latest' => '/store/order/order-latest',
        'order_eod' => '/store/order/order-latest-eod',
        'order_fulfillment' => '/store/order-fulfillment',
    ],

    /*
    |--------------------------------------------------------------------------
    | CSV Column Mapping
    |--------------------------------------------------------------------------
    |
    | Maps MIP CSV header names to database column names.
    | Any CSV column NOT listed here is automatically stored in the `meta` JSON column.
    | If eBay renames a column, update the mapping here — no PHP code changes needed.
    |
    */
    'column_map' => [
        // Order-level: CSV header => DB column on mip_orders
        'orders' => [
            'orderID' => 'order_id',
            'buyerID' => 'buyer_user_id',
            'buyerEmail' => 'buyer_email',
            'buyerName' => 'buyer_name',
            'orderLogisticsStatus' => 'order_status',
            'orderPaymentStatus' => 'payment_status',
            'orderSumTotalCurrency' => 'currency',
            'orderSumTotal' => 'total_price',
            'shipToAddressName' => 'ship_to_name',
            'shipToAddressPhone' => 'ship_to_phone',
            'shipToAddressLine1' => 'ship_to_street1',
            'shipToAddressLine2' => 'ship_to_street2',
            'shipToAddressCity' => 'ship_to_city',
            'shipToAddressStateOrProvince' => 'ship_to_state',
            'shipToAddressPostalCode' => 'ship_to_zip',
            'shipToAddress' => 'ship_to_country',
            'createdDate' => 'ordered_at',
            'paymentClearedDate' => 'paid_at',
        ],

        // Line-item level: CSV header => DB column on mip_order_lines
        'order_lines' => [
            'lineItemID' => 'line_item_id',
            'itemID' => 'item_id',
            'SKU' => 'sku',
            'title' => 'title',
            'quantity' => 'quantity',
            'unitPrice' => 'unit_price',
            'unitPriceCurrency' => 'currency',
            'logisticsStatus' => 'logistics_status',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fulfillment Export CSV Headers
    |--------------------------------------------------------------------------
    |
    | The exact header names eBay expects in the fulfillment upload CSV.
    |
    */
    'fulfillment_headers' => [
        'Order ID',
        'Line Item ID',
        'Logistics Status',
        'Shipment Carrier',
        'Shipment Tracking',
    ],
];
