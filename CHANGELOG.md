# Changelog

All notable changes to the WooCommerce Local Pickup Costs plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

