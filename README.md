# WooCommerce Local Pickup Costs

A WordPress plugin that extends WooCommerce to provide location-specific cost overrides for local pickup shipping methods and enables URL-based pre-selection of pickup locations at checkout.

## Features

- **Location-Specific Costs**: Set custom pickup costs for each local pickup location
- **Cost Overrides**: Override the global cost from WooCommerce Local Pickup settings
- **URL Pre-selection**: Pre-select pickup location using `?pickup_location=location-id` URL parameter
- **Auto-selection**: Automatically select the local pickup shipping method when pre-selecting a location
- **Admin Interface**: User-friendly settings page under WooCommerce > Settings > Shipping > Local Pickup Costs

## Requirements

- WordPress 5.8 or higher
- WooCommerce 3.0 or higher
- PHP 7.4 or higher

## Installation

1. Upload the plugin files to `/wp-content/plugins/woocommerce-local-pickup-costs/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > Settings > Shipping > Local Pickup Costs
4. Configure costs for each pickup location

## Usage

### Setting Location-Specific Costs

1. Navigate to **WooCommerce > Settings > Shipping**
2. Click on the **Local Pickup Costs** tab
3. For each pickup location, enter a custom cost or leave blank to use the global cost
4. Set to `0` to make a location free
5. Click **Save changes**

### URL Pre-selection

Add the `pickup_location` parameter to your checkout URL to pre-select a specific location:

```
https://yoursite.com/checkout/?pickup_location=1
```

This will:
- Automatically select the local pickup shipping method
- Pre-select the specified pickup location
- Apply the location-specific cost

## How It Works

1. **Admin Settings**: The plugin adds a "Local Pickup Costs" tab under WooCommerce > Settings > Shipping that lists all configured local pickup locations
2. **Cost Override**: When a customer selects a pickup location at checkout, the plugin checks if a location-specific cost is set and applies it instead of the global cost
3. **URL Parameter**: The `pickup_location` parameter in the URL triggers JavaScript that automatically selects the pickup method and location on the checkout page

## Cost Logic

- **Empty field**: Uses the global cost from Local Pickup settings
- **Set to `0`**: Makes the location free (overrides global cost)
- **Any number**: Uses the custom cost for that location

## Development

### File Structure

```
woocommerce-local-pickup-costs/
├── woocommerce-local-pickup-costs.php   # Main plugin file
├── includes/
│   ├── class-wc-local-pickup-costs.php  # Core plugin class
│   ├── class-cost-handler.php           # Cost override logic
│   ├── admin/
│   │   └── class-admin-settings.php     # Admin settings page
│   └── frontend/
│       └── class-checkout-handler.php   # Checkout pre-selection
├── assets/
│   └── js/
│       └── checkout-preselect.js        # JavaScript for checkout
├── .github/
│   └── workflows/
│       └── deploy.yml                    # GitHub Actions deployment
├── .gitignore
└── README.md
```

## Security

All inputs are sanitized and validated:
- Nonce verification for all form submissions
- Capability checks for admin functions
- Input sanitization using WordPress functions
- Output escaping for display

## Hooks

### WooCommerce Hooks

The plugin uses the following WooCommerce hooks:

- `woocommerce_get_sections_shipping`: Add new tab to shipping settings
- `woocommerce_get_settings_shipping`: Render settings page
- `woocommerce_package_rates`: Modify shipping rates based on location costs
- `woocommerce_settings_save_shipping`: Save location costs
- `woocommerce_store_api_checkout_update_order_from_request`: Apply costs during order finalization

### Plugin Filter Hooks

#### `wc_lpc_pickup_location_cost`

Filter hook to adjust pickup location costs dynamically. This hook allows developers to modify the cost before it's applied to shipping rates or order items.

**Parameters:**
- `$custom_cost` (float) - The custom cost from plugin settings
- `$location_index` (int) - The location array index
- `$location_data` (array) - Full location data (name, address, enabled status, etc.)
- `$original_cost` (float) - Original WooCommerce cost before override
- `$context` (object) - Context object (`WC_Shipping_Rate` for display or `WC_Order_Item_Shipping` for finalization)

**Return Value:**
- Return modified cost (float) to apply that cost
- Return `false` or `null` to skip override and use original WooCommerce cost

**Example Usage:**

```php
// Apply 10% discount to downtown location
add_filter( 'wc_lpc_pickup_location_cost', function( $custom_cost, $location_index, $location_data, $original_cost, $context ) {
    if ( $location_index === 0 && strpos( $location_data['name'], 'Downtown' ) !== false ) {
        return $custom_cost * 0.9;
    }
    return $custom_cost;
}, 10, 5 );

// Skip override for specific location and use WooCommerce default
add_filter( 'wc_lpc_pickup_location_cost', function( $custom_cost, $location_index, $location_data, $original_cost, $context ) {
    if ( $location_index === 2 ) {
        return false; // Skip override, use original WooCommerce cost
    }
    return $custom_cost;
}, 10, 5 );

// Apply dynamic pricing based on cart total
add_filter( 'wc_lpc_pickup_location_cost', function( $custom_cost, $location_index, $location_data, $original_cost, $context ) {
    if ( WC()->cart && WC()->cart->get_subtotal() > 100 ) {
        return 0; // Free pickup for orders over $100
    }
    return $custom_cost;
}, 10, 5 );
```

## License

GPL v2 or later

## Credits

Developed by Abundant Designs

## Support

For issues and feature requests, please visit the [GitHub repository](https://github.com/your-username/woocommerce-local-pickup-costs).

