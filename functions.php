<?php
/**
 * RealHomes Child theme functions.
 *
 * @package realhomes-child
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --------------------------------------------------------
// MODULARIZED FUNCTIONS
// --------------------------------------------------------

// 1. Theme Setup (Enqueues, Polylang, Elementor, Caching, SVG Support)
require_once get_stylesheet_directory() . '/includes/theme-setup.php';

// 2. CRM Sync (REST API Endpoint, ACF Overrides, AWS Attachments)
require_once get_stylesheet_directory() . '/includes/rest-api-sync.php';

// 3. Search Overrides (Pre_get_posts modifications)
require_once get_stylesheet_directory() . '/includes/search-overrides.php';

// 4. Frontend UI (Energy Classes, Location Trees, DOM Injections)
require_once get_stylesheet_directory() . '/includes/frontend-ui.php';