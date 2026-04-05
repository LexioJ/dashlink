# Setup & Configuration Files

This document contains app configuration and setup details.

## Routes Definition (appinfo/routes.php)

**15 RESTful API endpoints organized by functionality:**

```php
<?php
return [
    'routes' => [
        // Public (Dashboard) Routes - 2 endpoints
        ['name' => 'link#index', 'url' => '/api/v1/links', 'verb' => 'GET'],
        ['name' => 'link#getIcon', 'url' => '/api/v1/links/{id}/icon', 'verb' => 'GET'],

        // Admin Link Management - 11 endpoints
        ['name' => 'link#adminIndex', 'url' => '/api/v1/admin/links', 'verb' => 'GET'],
        ['name' => 'link#create', 'url' => '/api/v1/admin/links', 'verb' => 'POST'],
        ['name' => 'link#update', 'url' => '/api/v1/admin/links/{id}', 'verb' => 'PUT'],
        ['name' => 'link#delete', 'url' => '/api/v1/admin/links/{id}', 'verb' => 'DELETE'],
        ['name' => 'link#uploadIcon', 'url' => '/api/v1/admin/links/{id}/icon', 'verb' => 'POST'],
        ['name' => 'link#deleteIcon', 'url' => '/api/v1/admin/links/{id}/icon', 'verb' => 'DELETE'],
        ['name' => 'link#updateOrder', 'url' => '/api/v1/admin/links/order', 'verb' => 'PUT'],
        ['name' => 'link#export', 'url' => '/api/v1/admin/links/export', 'verb' => 'GET'],
        ['name' => 'link#import', 'url' => '/api/v1/admin/links/import', 'verb' => 'POST'],
        ['name' => 'link#downloadIcon', 'url' => '/api/v1/admin/links/{id}/download-icon', 'verb' => 'POST'],
        ['name' => 'link#getGroups', 'url' => '/api/v1/admin/groups', 'verb' => 'GET'],

        // Admin Settings - 2 endpoints
        ['name' => 'settings#index', 'url' => '/api/v1/admin/settings', 'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/v1/admin/settings', 'verb' => 'PUT'],

        // Admin Page
        ['name' => 'admin#index', 'url' => '/admin', 'verb' => 'GET'],
    ]
];
```

## App Info (appinfo/info.xml)

```xml
<?xml version="1.0"?>
<info xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:noNamespaceSchemaLocation="https://apps.nextcloud.com/schema/apps/info.xsd">
    <id>dashlink</id>
    <name>DashLink</name>
    <summary>Dashboard widget for external website links with customizable effects</summary>
    <description><![CDATA[
DashLink provides a dashboard widget that displays external website links configured by administrators.

Core Features:
- Dashboard widget displaying unlimited external links (max 10 visible)
- Custom icon/logo upload for each link (PNG, JPG, GIF, SVG, WebP up to 2MB)
- Group-based visibility control (show links to specific user groups)
- Three modular hover animation effects (Blur Overlay, 3D Card Flip, Slide Panel)
- Responsive grid layout (1-4 columns) with full dark mode support

Admin Panel:
- Complete CRUD operations for link management
- Drag & drop reordering with visual position badges
- Export/Import links to/from JSON with icon URL support
- Automatic icon downloading from external URLs during import
- Duplicate detection on import (by title or URL)
- Live preview panel with group filter simulation
- Modern Nextcloud UI (toggle switches, modals, autocomplete pickers)

Technical:
- 15 RESTful API endpoints
- Modular effect system - easily add custom effects
- Vue 3 with Composition API
- PHP 8.1+ backend with proper dependency injection
    ]]></description>
    <version>1.0.0</version>
    <licence>agpl</licence>
    <author mail="your@email.com">Your Name</author>
    <namespace>DashLink</namespace>
    <category>dashboard</category>
    <category>organization</category>

    <dependencies>
        <nextcloud min-version="31" max-version="32"/>
    </dependencies>

    <settings>
        <admin>OCA\DashLink\Settings\AdminSection</admin>
    </settings>
</info>
```

## Project Structure

```
dashlink/
├── appinfo/
│   ├── info.xml                    # App metadata
│   ├── routes.php                  # API routes definition
│   └── (no database.xml)           # Schema handled via lib/Migration/*
├── lib/
│   ├── AppInfo/
│   │   └── Application.php         # App bootstrap & registration
│   ├── Controller/
│   │   ├── LinkController.php      # REST API for links
│   │   ├── SettingsController.php  # Global settings API
│   │   └── AdminController.php     # Admin pages controller
│   ├── Dashboard/
│   │   └── DashLinkWidget.php      # Dashboard widget implementation
│   ├── Db/
│   │   ├── Link.php                # Link entity
│   │   └── LinkMapper.php          # Database mapper
│   ├── Service/
│   │   ├── LinkService.php         # Business logic
│   │   ├── IconService.php         # Icon upload & management
│   │   └── SettingsService.php     # Global settings management
│   ├── Settings/
│   │   └── AdminSection.php        # Admin settings integration
│   └── Migration/
│       └── Version001Date*.php     # Database migration
├── src/
│   ├── components/
│   │   ├── Dashboard.vue           # Main widget component
│   │   ├── LinkCard.vue            # Single link card (loads effects dynamically)
│   │   ├── AdminPanel.vue          # Admin management interface
│   │   ├── LinkForm.vue            # Form for create/edit link
│   │   ├── IconUploader.vue        # Drag & drop icon upload
│   │   ├── GroupSelector.vue       # Group selection component
│   │   ├── EffectSelector.vue      # Hover effect dropdown
│   │   └── WidgetPreview.vue       # Live preview component
│   ├── effects/                    # === MODULAR EFFECTS SYSTEM ===
│   │   ├── index.js                # Effect registry & exports
│   │   ├── EffectBase.vue          # Base component for effects
│   │   ├── effect_blur/
│   │   │   ├── index.js            # Effect metadata & registration
│   │   │   └── EffectBlur.vue      # Blur overlay effect
│   │   ├── effect_flip/
│   │   │   ├── index.js            # Effect metadata & registration
│   │   │   └── EffectFlip.vue      # 3D card flip effect
│   │   └── effect_slide/
│   │       ├── index.js            # Effect metadata & registration
│   │       └── EffectSlide.vue     # Slide-in panel effect
│   ├── composables/
│   │   ├── useLinks.js             # Links state management
│   │   ├── useGroups.js            # Groups state management
│   │   └── useSettings.js          # Settings state management
│   ├── dashboard.js                # Dashboard entry point
│   └── admin.js                    # Admin entry point
├── css/
│   ├── dashboard.scss              # Widget styles
│   ├── admin.scss                  # Admin panel styles
│   └── effects/                    # Effect-specific styles
│       ├── _blur.scss
│       ├── _flip.scss
│       └── _slide.scss
├── img/
│   ├── app.svg                     # App icon
│   └── app-dark.svg                # App icon (dark mode)
├── templates/
│   └── admin.php                   # Admin page template
├── l10n/                           # Translations
├── tests/
│   ├── Unit/
│   └── Integration/
├── package.json
├── webpack.config.js
├── composer.json
└── Makefile
```
