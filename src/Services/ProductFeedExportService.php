<?php

declare(strict_types=1);

namespace Zislogic\Ebay\Mip\Services;

use Illuminate\Database\Eloquent\Collection;
use Zislogic\Ebay\Mip\Exceptions\MipException;
use Zislogic\Ebay\Mip\Feed\ProductFeedBuilder;
use Zislogic\Ebay\Mip\Models\MipFeed;
use Zislogic\Ebay\Mip\Sftp\MipSftpClient;
use Zislogic\Ebay\Model\Inventory\Models\InventoryItem;

final class ProductFeedExportService
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        private readonly MipSftpClient $sftp,
        private readonly array $config,
    ) {}

    /**
     * Export all inventory items for a credential as a product-combined feed.
     *
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    public function export(int $credentialId): array
    {
        /** @var Collection<int, InventoryItem> $items */
        $items = InventoryItem::query()
            ->where('ebay_credential_id', $credentialId)
            ->with([
                'group',
                'group.specifications',
                'locale',
                'condition',
                'location',
                'aspects',
                'images',
                'ktypes',
                'offers',
                'offers.energyLabel',
                'offers.productSafety',
                'offers.epr',
                'offers.documents',
                'offers.hazmat',
                'offers.manufacturers',
                'offers.responsiblePersons',
                'offers.shippingCostOverrides',
                'offers.listing',
            ])
            ->get();

        if ($items->isEmpty()) {
            return ['filename' => '', 'count' => 0];
        }

        return $this->buildAndUpload($credentialId, $items);
    }

    /**
     * Export specific inventory items.
     *
     * @param Collection<int, InventoryItem> $items
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    public function exportItems(Collection $items): array
    {
        if ($items->isEmpty()) {
            return ['filename' => '', 'count' => 0];
        }

        $credentialId = $items->first()->ebay_credential_id;

        return $this->buildAndUpload($credentialId, $items);
    }

    /**
     * @param Collection<int, InventoryItem> $items
     * @return array{filename: string, count: int}
     *
     * @throws MipException
     */
    private function buildAndUpload(int $credentialId, Collection $items): array
    {
        /** @var array<string, mixed> $feedConfig */
        $feedConfig = $this->config['product_feed'] ?? [];
        $builder = new ProductFeedBuilder($feedConfig);

        foreach ($items as $item) {
            $this->addItemRow($builder, $item);
        }

        $csvContent = $builder->build();
        $filename = 'product-combined_' . date('Ymd_His') . '.csv';

        /** @var array<string, string> $paths */
        $paths = $this->config['paths'] ?? [];
        $remotePath = ($paths['product_feed'] ?? '/store/listing/product-combined') . '/' . $filename;

        $this->sftp->uploadFile($remotePath, $csvContent);

        MipFeed::query()->create([
            'ebay_credential_id' => $credentialId,
            'feed_type' => 'product_combined',
            'remote_path' => $remotePath,
            'item_count' => $builder->rowCount(),
            'status' => 'uploaded',
            'uploaded_at' => now(),
        ]);

        return ['filename' => $remotePath, 'count' => $builder->rowCount()];
    }

    private function addItemRow(ProductFeedBuilder $builder, InventoryItem $item): void
    {
        // Core identity
        $builder->setValue('sku', $item->sku);
        $builder->setValue('localized_for', $item->locale?->code);
        $builder->setValue('variation_group_id', $item->group?->inventory_item_group_key);

        // Product details
        $builder->setValue('title', $item->product_title);
        $builder->setValue('product_description', $item->product_description);
        $builder->setValue('ean', $item->product_ean);
        $builder->setValue('upc', $item->product_upc);
        $builder->setValue('isbn', $item->product_isbn);
        $builder->setValue('mpn', $item->product_mpn);
        $builder->setValue('brand', $item->product_brand);
        $builder->setValue('epid', $item->product_epid);

        // Condition
        $builder->setValue('condition', $item->condition?->name);
        $builder->setValue('condition_description', $item->condition_description);

        // Dimensions
        $builder->setValue('measurement_system', $item->measurement_system);
        $builder->setValue('length', $item->length !== null ? (string) $item->length : null);
        $builder->setValue('width', $item->width !== null ? (string) $item->width : null);
        $builder->setValue('height', $item->height !== null ? (string) $item->height : null);
        $builder->setValue('weight_major', $item->weight_value !== null ? (string) $item->weight_value : null);
        $builder->setValue('package_type', $item->package_type);

        // Availability
        $builder->setValue('total_ship_to_home_quantity', (string) $item->quantity);
        $builder->setValue('warehouse_location_id', $item->location?->merchant_location_key);

        // Aspects → Attribute Name/Value pairs
        foreach ($item->aspects as $aspect) {
            $builder->setValue('attribute_name', $aspect->name);
            $builder->setValue('attribute_value', $aspect->values);
        }

        // Group-level shared aspects (for multi-SKU items)
        if ($item->group !== null) {
            foreach ($item->group->aspects as $aspect) {
                $builder->setValue('attribute_name', $aspect->name);
                $builder->setValue('attribute_value', $aspect->values);
            }

            // Variation specifics
            foreach ($item->group->specifications as $spec) {
                $builder->setValue('variation_specific_name', $spec->name);
                $builder->setValue('variation_specific_value', $spec->values);
            }

            $builder->setValue('pictures_vary_on', $item->group->aspects_image_varies_by !== null
                ? implode('|', $item->group->aspects_image_varies_by)
                : null
            );
        }

        // Images
        foreach ($item->images->sortBy('sort_order') as $image) {
            $builder->setValue('picture_url', $image->url);
        }

        // KTypes → Compatible Products
        foreach ($item->ktypes as $ktype) {
            $builder->setValue('compatible_product', 'KType=' . $ktype->ktype);
        }

        // Offer-level fields (use first offer for the item's primary marketplace)
        $offer = $item->offers->first();

        if ($offer !== null) {
            $builder->setValue('channel_id', $offer->marketplace_code);
            $builder->setValue('category', $offer->category_number);
            $builder->setValue('secondary_category', $offer->secondary_category_number);
            $builder->setValue('shipping_policy', $offer->fulfillment_policy_id);
            $builder->setValue('payment_policy', $offer->payment_policy_id);
            $builder->setValue('return_policy', $offer->return_policy_id);
            $builder->setValue('list_price', $offer->price !== null ? (string) $offer->price : null);
            $builder->setValue('auctionreserveprice', $offer->auction_reserve_price !== null ? (string) $offer->auction_reserve_price : null);
            $builder->setValue('auctionstartprice', $offer->auction_start_price !== null ? (string) $offer->auction_start_price : null);
            $builder->setValue('listingduration', $offer->listing_duration);
            $builder->setValue('listingstartdate', $offer->listing_start_date?->toIso8601String());
            $builder->setValue('format', $offer->format);
            $builder->setValue('max_quantity_per_buyer', $offer->quantity_limit_per_buyer !== null ? (string) $offer->quantity_limit_per_buyer : null);
            $builder->setValue('minimum_advertised_price', $offer->minimum_advertised_price !== null ? (string) $offer->minimum_advertised_price : null);
            $builder->setValue('store_category_name_1', $offer->store_category_name1);
            $builder->setValue('store_category_name_2', $offer->store_category_name2);
            $builder->setValue('apply_tax', $offer->apply_tax ? 'TRUE' : 'FALSE');
            $builder->setValue('vat_percent', $offer->vat_percentage !== null ? (string) $offer->vat_percentage : null);
            $builder->setValue('best_offer_enabled', $offer->best_offer_enabled ? 'TRUE' : 'FALSE');
            $builder->setValue('bo_auto_accept_price', $offer->best_offer_auto_accept_price !== null ? (string) $offer->best_offer_auto_accept_price : null);
            $builder->setValue('bo_auto_decline_price', $offer->best_offer_auto_decline_price !== null ? (string) $offer->best_offer_auto_decline_price : null);
            $builder->setValue('hide_buyer_details', $offer->hide_buyer_details ? 'TRUE' : 'FALSE');
            $builder->setValue('include_ebay_product_details', $offer->include_catalog_product_details ? 'TRUE' : 'FALSE');
            $builder->setValue('eligible_for_ebayplus', $offer->ebay_plus_if_eligible ? 'TRUE' : 'FALSE');

            // EPR
            if ($offer->epr !== null) {
                $builder->setValue('ecoparticipationfee', $offer->epr->eco_participation_fee_value !== null
                    ? $offer->epr->eco_participation_fee_value . ' ' . $offer->epr->eco_participation_fee_currency
                    : null
                );
            }

            // Hazmat (first entry — MIP CSV has single hazmat fields)
            $hazmat = $offer->hazmat->first();
            if ($hazmat !== null) {
                $builder->setValue('hazmat_component', $hazmat->component);
                $builder->setValue('hazmat_signalword', $hazmat->signal_word);
                $builder->setValue('hazmat_pictograms', $hazmat->pictograms);
                $builder->setValue('hazmat_statements', $hazmat->statements);
            }

            // Energy label
            if ($offer->energyLabel !== null) {
                $builder->setValue('energyefficiencylabel_imageurl', $offer->energyLabel->image_url);
                $builder->setValue('energyefficiencylabel_imagedescription', $offer->energyLabel->image_description);
                $builder->setValue('energyefficiencylabel_productinformationsheet', $offer->energyLabel->product_information_sheet);
            }

            // Product safety (first entry)
            $safety = $offer->productSafety->first();
            if ($safety !== null) {
                $builder->setValue('product_safety_component', $safety->component);
                $builder->setValue('product_safety_pictograms', $safety->pictograms);
                $builder->setValue('product_safety_statements', $safety->statements);
            }

            // Documents
            $docIds = $offer->documents->pluck('document_id')->all();
            if ($docIds !== []) {
                $builder->setValue('documents', implode('|', $docIds));
            }

            // Manufacturer
            $manufacturer = $offer->manufacturers->first();
            if ($manufacturer !== null) {
                $builder->setValue('manufacturer_companyname', $manufacturer->company_name);
                $builder->setValue('manufacturer_contacturl', $manufacturer->contact_url);
                $builder->setValue('manufacturer_addressline1', $manufacturer->address_line1);
                $builder->setValue('manufacturer_addressline2', $manufacturer->address_line2);
                $builder->setValue('manufacturer_city', $manufacturer->city);
                $builder->setValue('manufacturer_country', $manufacturer->country);
                $builder->setValue('manufacturer_postalcode', $manufacturer->postal_code);
                $builder->setValue('manufacturer_stateorprovince', $manufacturer->state_or_province);
                $builder->setValue('manufacturer_phone', $manufacturer->phone);
                $builder->setValue('manufacturer_email', $manufacturer->email);
            }

            // Responsible persons
            foreach ($offer->responsiblePersons as $person) {
                $builder->setValue('responsibleperson_companyname', $person->company_name);
                $builder->setValue('responsibleperson_contacturl', $person->contact_url);
                $builder->setValue('responsibleperson_addressline1', $person->address_line1);
                $builder->setValue('responsibleperson_addressline2', $person->address_line2);
                $builder->setValue('responsibleperson_city', $person->city);
                $builder->setValue('responsibleperson_country', $person->country);
                $builder->setValue('responsibleperson_postalcode', $person->postal_code);
                $builder->setValue('responsibleperson_stateorprovince', $person->state_or_province);
                $builder->setValue('responsibleperson_phone', $person->phone);
                $builder->setValue('responsibleperson_email', $person->email);
                $builder->setValue('responsibleperson_types', $person->types);
            }

            // Shipping cost overrides
            foreach ($offer->shippingCostOverrides->sortBy('priority') as $override) {
                if ($override->shipping_service_type === 'DOMESTIC') {
                    $builder->setValue('domestic_shipping_p_cost', $override->shipping_cost !== null ? (string) $override->shipping_cost : null);
                    $builder->setValue('domestic_shipping_p_additional_cost', $override->additional_shipping_cost !== null ? (string) $override->additional_shipping_cost : null);
                    $builder->setValue('domestic_shipping_p_surcharge', $override->surcharge !== null ? (string) $override->surcharge : null);
                } else {
                    $builder->setValue('international_shipping_p_cost', $override->shipping_cost !== null ? (string) $override->shipping_cost : null);
                    $builder->setValue('international_shipping_p_additional_cost', $override->additional_shipping_cost !== null ? (string) $override->additional_shipping_cost : null);
                    $builder->setValue('international_shipping_p_surcharge', $override->surcharge !== null ? (string) $override->surcharge : null);
                }
            }
        }

        $builder->newRow();
    }
}
