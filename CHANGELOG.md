# Changelog

All notable changes to the WooCommerce Local Pickup Costs plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

