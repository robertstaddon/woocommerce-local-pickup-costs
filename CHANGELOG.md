# Changelog

All notable changes to the WooCommerce Local Pickup Costs plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.5] - 2025-01-XX

### Fixed
- Fixed duplicate hook registration issue: Added static flag to prevent hooks from being registered multiple times if class is instantiated repeatedly
- Hooks now register only once, even if constructor is called multiple times

## [1.3.4] - 2025-01-XX

### Added
- Enhanced debug logging at initialization to confirm hooks are registered
- Comprehensive entry-point logging in `modify_pickup_rates_for_blocks()` to trace filter execution
- Debug logging for early returns to help diagnose why filter may not be firing

## [1.3.3] - 2025-01-XX

### Fixed
- Fixed cost override not displaying in cart/checkout: Now extracts location index directly from rate ID (`pickup_location:0` → index `0`) instead of unreliable location name matching
- Removed location name extraction and matching logic that was causing all locations to show default $2.00 cost

### Added
- Comprehensive debug logging in `modify_pickup_rates_for_blocks()` method to output all available rate properties
- Debug output includes: rate ID, extracted location index, rate object class, label, and cost values for troubleshooting

## [1.3.2] - 2025-01-XX

### Changed
- Moved Cost Override field instructions to page header for cleaner table layout
- Removed redundant description text from under each Cost Override field
- Updated instructions to include information about setting cost to 0 for free pickup

## [1.3.1] - 2025-01-XX

### Changed
- Removed sort column from admin table for cleaner layout
- Status column now uses checkbox-style toggles with `status-enabled` and `status-disabled` classes matching WooCommerce admin.css styling
- Replaced text-based status indicators with visual checkbox icons

## [1.3.0] - 2025-01-XX

### Fixed
- Fixed cart display issue: Cost overrides now appear correctly in cart and checkout BEFORE order finalization
- Added `woocommerce_shipping_package_rates` filter to modify rates at cart calculation stage
- Customers now see correct override costs immediately when selecting pickup locations

### Added
- Redesigned admin settings page as a proper table layout
- Table displays: Location Name, Address, Status (Enabled/Disabled), and Cost Override columns
- All locations shown (enabled and disabled) for better visibility
- Improved address formatting with proper city, state, postcode, and country display
- Better visual status indicators using WooCommerce styling

### Changed
- Admin page now uses single table view instead of individual fields per location
- Location retrieval now includes ALL locations (not just enabled) to show full status

## [1.2.0] - 2025-01-XX

### Fixed
- Fixed cost override to work correctly with WooCommerce Checkout Blocks
- Changed method detection from string search to proper method_id check (`method_id === 'pickup_location'`)
- Implemented location matching by name from shipping item meta instead of relying on rate IDs
- Fixed cost application to shipping item directly instead of order total
- Added proper order totals recalculation after cost modification

### Removed
- Removed all classic checkout compatibility code
- Removed `modify_local_pickup_cost_classic()` method
- Removed `get_location_index_from_rate_id()` method
- Removed `woocommerce_package_rates` filter hook
- Plugin now exclusively supports WooCommerce Checkout Blocks

### Added
- Comprehensive debug logging at every step of the cost override process
- Logging includes: location name extraction, matching process, custom cost application, and totals
- Detailed troubleshooting information for verifying cost override functionality

## [1.1.4] - 2025-01-XX

### Added
- Added comprehensive debug logging to identify shipping item properties and metadata
- Added logging for all request keys and available methods
- Added detailed shipping item data logging (method ID, title, total, meta, and data)

## [1.1.3] - 2025-01-XX

### Fixed
- Fixed memory exhaustion by logging only specific request properties instead of entire request object
- Changed debug logging to use `get_json_params()` and log only shipping_method and extensions

## [1.1.2] - 2025-01-XX

### Fixed
- Version bump for deployment

## [1.1.1] - 2025-01-XX

### Fixed
- Fixed fatal error: "Too few arguments" by changing from filter to action hook
- Corrected function signature to accept only 2 arguments (order and request)
- Added comprehensive debug logging to investigate data structure

## [1.1.0] - 2025-01-XX

### Added
- WooCommerce Checkout Blocks support via `woocommerce_store_api_checkout_update_order_from_request` hook
- Blocks-compatible cost override system for pickup locations
- Order meta tracking for pickup location index and original cost

### Changed
- Completely rewrote cost handler to use Store API filters instead of `woocommerce_package_rates`
- Primary focus on Checkout Blocks compatibility
- Maintains classic checkout fallback for backwards compatibility

### Technical
- Uses `woocommerce_store_api_checkout_update_order_from_request` action for Blocks checkout
- Modifies order shipping total directly before finalization
- Stores pickup location index as order meta for reference

## [1.0.8] - 2025-01-XX

### Fixed
- Fixed section check in save function to use `$_GET['section']` instead of `$_POST['current_section']`
- Settings now properly save to database when "Save changes" is clicked
- Changed section detection from POST to GET parameter to match WooCommerce behavior

## [1.0.7] - 2025-01-XX

### Fixed
- Fixed form fields to display saved values by fetching current costs from database on render
- Form now properly reflects the saved values instead of always showing old values
- Settings properly persist and display after save

## [1.0.6] - 2025-01-XX

### Fixed
- Fixed settings save to properly read array-formatted POST data
- Corrected how location costs are retrieved from `$_POST['wc_lpc_location_costs']` array
- Settings now properly save and persist

## [1.0.5] - 2025-01-XX

### Fixed
- Removed AJAX save behavior and switched to standard WooCommerce admin page save mechanism
- Settings now save using the default WordPress post-back form submission
- Improved UI consistency with WooCommerce admin pages

### Changed
- Converted custom settings rendering to WooCommerce's Settings API
- Use custom field type for location-specific cost inputs within standard settings framework

## [1.0.4] - 2025-01-XX

### Fixed
- Changed location retrieval to read from the `pickup_location_pickup_locations` WordPress option
- Updated cost storage to use array indices matching the pickup locations array
- Locations are now identified by their array position (0, 1, 2, 3...) instead of instance IDs
- Cost overrides are now stored and retrieved by array index for proper matching

### Changed
- Cost storage structure now matches WooCommerce's pickup locations array structure
- Location identification uses the same index as the `pickup_location_pickup_locations` option

## [1.0.3] - 2025-01-XX

### Fixed
- Fixed location retrieval to correctly identify WooCommerce Local Pickup method instances
- Simplified location detection by treating each `local_pickup` method instance as a separate location
- Removed incorrect logic that tried to find sub-locations within method instances

## [1.0.2] - 2025-01-XX

### Fixed
- Updated version constant to match plugin header (1.0.1)

## [1.0.1] - 2025-01-XX

### Added
- HPOS (High-Performance Order Storage) compatibility declaration
- Compatibility with WooCommerce's custom order tables feature

## [1.0.0] - 2025-01-XX

### Added
- Initial release
- Admin settings tab under WooCommerce > Settings > Shipping > Local Pickup Costs
- Display all local pickup locations with individual cost fields
- Location-specific cost override system
- URL parameter pre-selection feature (`?pickup_location=location-id`)
- Automatic pickup method selection at checkout
- Support for zeroing out global costs with location-specific costs
- GitHub Actions deployment workflow
- Comprehensive documentation (README.md)

### Technical
- Plugin structure with proper file organization
- Security measures (nonce verification, capability checks, input sanitization)
- WooCommerce hooks integration for shipping rates modification
- Session-based location pre-selection
- JavaScript-based checkout manipulation

