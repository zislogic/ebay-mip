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
        'product_feed' => '/store/listing/product-combined',
        'inventory_feed' => '/store/listing/product-inventory',
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

    /*
    |--------------------------------------------------------------------------
    | Product Combined Feed Structure
    |--------------------------------------------------------------------------
    |
    | Defines which CSV columns are included in the product-combined feed.
    |
    | SingleFields: columns that appear exactly once per row.
    | MultiFields:  columns that repeat with a numeric suffix (%d).
    |               'header' is a sprintf pattern, 'max' is the maximum count.
    |
    | Source: https://developer.ebay.com/api-docs/user-guides/static/mip-user-guide/mip-sample-files.html
    | Update this config if eBay changes the sample CSV — no PHP changes needed.
    |
    */
    'product_feed' => [
        'SingleFields' => [
            'Remove this column',
            'SKU',
            'Localized For',
            'Variation Group ID',
            'Title',
            'Subtitle',
            'Product Description',
            'Additional Info',
            'Group Picture URL',
            'UPC',
            'ISBN',
            'EAN',
            'MPN',
            'Brand',
            'ePID',
            'Condition',
            'Condition Description',
            'Measurement System',
            'Length',
            'Width',
            'Height',
            'Weight Major',
            'Weight Minor',
            'Package Type',
            'Total Ship To Home Quantity',
            'Channel ID',
            'Category',
            'Secondary Category',
            'Shipping Policy',
            'Payment Policy',
            'Return Policy',
            'Best Offer Enabled',
            'BO Auto Accept Price',
            'BO Auto Decline Price',
            'List Price',
            'AuctionReservePrice',
            'AuctionStartPrice',
            'ListingDuration',
            'ListingStartDate',
            'Format',
            'Max Quantity Per Buyer',
            'Strikethrough Price',
            'Minimum Advertised Price',
            'Minimum Advertised Price Handling',
            'Store Category Name 1',
            'Store Category Name 2',
            'Sold Off Ebay',
            'Sold On Ebay',
            'Apply Tax',
            'Tax Category',
            'VAT Percent',
            'Include eBay Product Details',
            'TemplateName',
            'CustomFields',
            'Eligible For EbayPlus',
            'Pictures Vary On',
            'Warehouse Location ID',
            'Hide Buyer Details',
            'Compliance policies',
            'Take-back policy',
            'producerProductId',
            'productPackageId',
            'shipmentPackageId',
            'productDocumentationId',
            'ecoParticipationFee',
            'Hazmat Pictograms',
            'Hazmat SignalWord',
            'Hazmat Statements',
            'Hazmat Component',
            'EnergyEfficiencyLabel ImageURL',
            'EnergyEfficiencyLabel ImageDescription',
            'EnergyEfficiencyLabel ProductInformationSheet',
            'Regional ProductCompliancePolicies',
            'Manufacturer CompanyName',
            'Manufacturer contactUrl',
            'Manufacturer AddressLine1',
            'Manufacturer AddressLine2',
            'Manufacturer City',
            'Manufacturer Country',
            'Manufacturer PostalCode',
            'Manufacturer StateOrProvince',
            'Manufacturer Phone',
            'Manufacturer Email',
            'Product Safety Component',
            'Product Safety Pictograms',
            'Product Safety Statements',
            'Documents',
        ],
        'MultiFields' => [
            ['header' => 'Variation Specific Name %d', 'max' => 5],
            ['header' => 'Variation Specific Value %d', 'max' => 5],
            ['header' => 'Picture URL %d', 'max' => 12],
            ['header' => 'Attribute Name %d', 'max' => 45],
            ['header' => 'Attribute Value %d', 'max' => 45],
            ['header' => 'Compatible Product %d', 'max' => 3000],
            ['header' => 'Domestic Shipping P%d Cost', 'max' => 4],
            ['header' => 'Domestic Shipping P%d Additional Cost', 'max' => 4],
            ['header' => 'Domestic Shipping P%d Surcharge', 'max' => 4],
            ['header' => 'International Shipping P%d Cost', 'max' => 5],
            ['header' => 'International Shipping P%d Additional Cost', 'max' => 5],
            ['header' => 'International Shipping P%d Surcharge', 'max' => 5],
            ['header' => 'ResponsiblePerson%d CompanyName', 'max' => 3],
            ['header' => 'ResponsiblePerson%d ContactUrl', 'max' => 3],
            ['header' => 'ResponsiblePerson%d AddressLine1', 'max' => 3],
            ['header' => 'ResponsiblePerson%d AddressLine2', 'max' => 3],
            ['header' => 'ResponsiblePerson%d City', 'max' => 3],
            ['header' => 'ResponsiblePerson%d Country', 'max' => 3],
            ['header' => 'ResponsiblePerson%d PostalCode', 'max' => 3],
            ['header' => 'ResponsiblePerson%d StateOrProvince', 'max' => 3],
            ['header' => 'ResponsiblePerson%d Phone', 'max' => 3],
            ['header' => 'ResponsiblePerson%d Email', 'max' => 3],
            ['header' => 'ResponsiblePerson%d Types', 'max' => 3],
        ],
    ],
];
