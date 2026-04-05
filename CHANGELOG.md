# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-04-05

### Added
- **User-private links**: Users can create personal dashboard links visible only to themselves, managed via Settings → Personal → DashLink. Feature is disabled by default; admins can enable it and set a per-user link limit (1–50, default 10). Includes full CRUD, drag-drop reorder, import/export, and icon upload. Admin links always appear first in the dashboard widget.

### Fixed
- **Group visibility**: Group names containing `.` (dot), ` ` (space), or other common special characters can now be added to the "visible to groups" list (fixes #2)

### Changed
- **Nextcloud compatibility**: Now supports Nextcloud 31–33 (max-version raised from 32 to 33)

## [1.1.0] - 2025-12-28

### Added
- **SecurityService**: Centralized security validation and sanitization service
  - URL validation with protocol restrictions
  - Download URL validation with SSRF protection
  - Text sanitization for XSS prevention
  - Filename validation for path traversal prevention
  - Integer range validation
  - Target and group ID validation
- **RateLimitService**: Distributed caching-based rate limiting
  - Configurable per-action rate limits
  - User-specific rate limiting
  - Automatic expiration handling

### Changed
- **IconService**: Updated to use SecurityService for all validations
  - Icon download now validates URLs before fetching
  - Icon filenames validated on retrieval
  - SVG files sanitized during upload
  - Mime-type validation added to prevent spoofing
- **LinkService**: Updated to use SecurityService for input validation
  - All create/update operations validate and sanitize inputs
  - URL validation blocks dangerous protocols
  - Text inputs sanitized to prevent XSS
- **SettingsService**: Updated to sanitize widget title
  - Widget title sanitized with length limit
  - HTML tags stripped, special characters encoded
- **LinkController**: Enhanced with rate limiting and validation
  - Import endpoint rate-limited (5/hour)
  - File size limits enforced (1MB for imports)
  - JSON depth limits (10 levels)
  - Link count limits (100 per import)
- **Dependencies**: Added enshrined/svg-sanitize (^0.19) for SVG sanitization

### Fixed

**Icon Upload/Management:**
- Icon preview now appears immediately after selecting a file, without needing to save first
- Delete icon button improved with perfect circular shape (proper circle instead of ellipse)
- Delete button hover effect changed to darker red with subtle glow instead of black border

**3D Card Flip Effect:**
- Fixed card flip animation to rotate the entire card including shadow as a single unit, creating a more realistic 3D effect
- Eliminated white background flash during flip transition - now shows widget background seamlessly
- Fixed Firefox browser issue where front content was incorrectly visible on the back during flip

### Technical Details
- Improved security rating from C+ (69/100) to A (90+)
- All critical and high-priority vulnerabilities resolved
- OWASP Top 10 compliance achieved
- Nextcloud security guidelines followed
- CSRF protection verified (correctly implemented)

## [1.0.0] - 2024-12-28

### Added

**Core Dashboard Widget:**
- Dashboard widget displaying external links with customizable icons
- Three modular hover effects: Blur Overlay, 3D Card Flip, Slide Panel
- Group-based visibility filtering (show links to specific user groups)
- Responsive grid layout (1-4 columns based on viewport)
- Full dark mode support with Nextcloud CSS variables

**Admin Panel:**
- Complete CRUD operations for link management
- Drag & drop reordering with visual position badges
- Modern Nextcloud UI components (toggle switches, modals, pickers)
- Live preview panel with group filter simulation
- Icon management: upload, delete, download from URLs
- Group picker with autocomplete for visibility control

**Import/Export System:**
- Export links to JSON with absolute Nextcloud URLs
- Import links from JSON with duplicate detection (by title or URL)
- Automatic icon downloading from external URLs during import
- Position management (imported links appended after existing)

**Technical Implementation:**
- PHP 8.1+ backend with Nextcloud App Framework
- Vue 3 with Composition API
- 15 RESTful API endpoints
- Modular effect system (easily extensible)
- Database migration with proper indexing
- Icon storage in Nextcloud app data directory (supports PNG, JPG, GIF, SVG, WebP up to 2MB)

[1.0.0]: https://github.com/lexioj/dashlink/releases/tag/v1.0.0
