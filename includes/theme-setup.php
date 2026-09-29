<?php
// --- STYLES & SCRIPTS ---
add_action( 'wp_enqueue_scripts', 'inspiry_enqueue_child_styles', PHP_INT_MAX );
function inspiry_enqueue_child_styles() {
    if ( ! is_admin() ) {
        wp_dequeue_style( 'parent-default' );
        wp_deregister_style( 'parent-default' );
        wp_dequeue_style( 'parent-custom' );
        wp_enqueue_style( 'parent-default', get_template_directory_uri() . '/style.css', array(), '3.12.1' );
        wp_enqueue_style( 'parent-custom' );
        
        // Cache Busting for Child Theme Default Style
        $child_default_path = get_stylesheet_directory() . '/style.css';
        $child_default_ver  = file_exists( $child_default_path ) ? filemtime( $child_default_path ) : '1.4.2';
        wp_enqueue_style( 'child-default', get_stylesheet_uri(), array( 'parent-default' ), $child_default_ver, 'all' );
        
        // Cache Busting for Child Custom CSS
        $child_custom_css_path = get_stylesheet_directory() . '/css/child-custom.css';
        $child_custom_css_ver  = file_exists( $child_custom_css_path ) ? filemtime( $child_custom_css_path ) : '1.4.2';
        wp_enqueue_style( 'child-custom', get_stylesheet_directory_uri() . '/css/child-custom.css', array( 'child-default' ), $child_custom_css_ver, 'all' );
        
        // Cache Busting for Child Custom JS
        $child_custom_js_path = get_stylesheet_directory() . '/js/child-custom.js';
        $child_custom_js_ver  = file_exists( $child_custom_js_path ) ? filemtime( $child_custom_js_path ) : '1.4.2';
        wp_enqueue_script( 'child-custom', get_stylesheet_directory_uri() . '/js/child-custom.js', array( 'jquery' ), $child_custom_js_ver, true );
    }
}

add_action( 'after_setup_theme', 'inspiry_load_translation_from_child' );
function inspiry_load_translation_from_child() {
    load_child_theme_textdomain( 'framework', get_stylesheet_directory() . '/languages' );
}

// --- POLYLANG SUPPORT ---
add_filter( 'pll_get_post_types', 'force_realhomes_polylang_cpt', 99, 2 );
function force_realhomes_polylang_cpt( $post_types, $is_settings ) {
    $post_types['property'] = 'property';
    return $post_types;
}

add_filter( 'pll_get_taxonomies', 'force_realhomes_polylang_taxonomies', 99, 2 );
function force_realhomes_polylang_taxonomies( $taxonomies, $is_settings ) {
    $taxonomies['property-city'] = 'property-city';
    $taxonomies['property-feature'] = 'property-feature';
    $taxonomies['property-type'] = 'property-type';
    $taxonomies['property-status'] = 'property-status';
    return $taxonomies;
}

// --- SVG SUPPORT ---
add_filter( 'upload_mimes', 'allow_custom_svg_uploads' );
function allow_custom_svg_uploads( $mimes ) {
    $mimes['svg'] = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    return $mimes;
}

add_action( 'admin_head', 'fix_svg_display_in_admin' );
function fix_svg_display_in_admin() {
    echo '<style type="text/css"> .attachment-266x266, .thumbnail img { width: 100% !important; height: auto !important; } </style>';
}

// --- UI & DASHBOARD TWEAKS ---
add_filter( 'get_terms_args', 'protect_frontend_cities', 10, 2 );
function protect_frontend_cities( $args, $taxonomies ) {
    if ( ! is_admin() && in_array( 'property-city', $taxonomies ) ) { $args['hide_empty'] = true; }
    return $args;
}

add_filter( 'terms_pre_query', 'intercept_rogue_backend_queries', 10, 2 );
function intercept_rogue_backend_queries( $terms, $query ) {
    if ( is_admin() && ! wp_doing_ajax() ) {
        global $pagenow;
        if ( in_array( $pagenow, ['edit-tags.php', 'term.php', 'post.php', 'post-new.php'] ) ) return $terms;
        if ( isset( $query->query_vars['taxonomy'] ) ) {
            $taxonomies = (array) $query->query_vars['taxonomy'];
            if ( in_array( 'property-city', $taxonomies ) ) return []; 
        }
    }
    return $terms;
}

add_action( 'admin_menu', 'remove_realhomes_location_metabox', 999 );
function remove_realhomes_location_metabox() {
    remove_meta_box( 'property-citydiv', 'property', 'side' );
    remove_meta_box( 'property-citydiv', 'property', 'normal' );
    remove_meta_box( 'property-citydiv', 'property', 'advanced' );
}

add_filter( 'inspiry_property_search_features_args', 'show_empty_features_in_search' );
function show_empty_features_in_search( $args ) {
    $args['hide_empty'] = false;
    return $args;
}

add_filter( 'ere_property_meta_boxes', 'optimize_ere_locations_dropdown', 99 );
function optimize_ere_locations_dropdown( $meta_boxes ) {
    foreach ( $meta_boxes as $key => $meta_box ) {
        if ( isset( $meta_box['id'] ) && 'property-meta-locations' === $meta_box['id'] ) unset( $meta_boxes[$key] );
    }
    return $meta_boxes;
}

add_shortcode( 'master_search_form', 'render_master_search_form' );
function render_master_search_form() {
    if ( ! class_exists( '\Elementor\Plugin' ) ) return '';
    
    $pll_lang = function_exists('pll_current_language') ? pll_current_language() : 'el';
    $wp_locale = get_locale();

    if ( strpos($pll_lang, 'en') !== false || strpos($wp_locale, 'en') !== false ) {
        $elementor_template_id = 47944; 
    } else {
        $elementor_template_id = 5931; 
    }
    
    // Execute Elementor natively to render the widget
    return \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $elementor_template_id );
}

// --- CACHE BYPASS FOR LIVE SEARCH & LOCATIONS ---
add_action('template_redirect', 'rh_force_live_search_bypass', 1);
function rh_force_live_search_bypass() {
    if (!empty($_SERVER['QUERY_STRING'])) {
        if (!defined('DONOTCACHEPAGE')) { define('DONOTCACHEPAGE', true); }
        nocache_headers(); 
    }
}

//  PREVENT NULL ADMIN BAR NODES ---
add_action( 'admin_bar_menu', 'rh_fix_null_admin_bar_nodes', 999 );
function rh_fix_null_admin_bar_nodes( $wp_admin_bar ) {
    $nodes = $wp_admin_bar->get_nodes();
    if ( ! empty( $nodes ) ) {
        foreach ( $nodes as $node ) {
            if ( is_null( $node->title ) ) {
                $node->title = '';
                $wp_admin_bar->add_node( $node );
            }
        }
    }
}

// =========================================================
// CUSTOM OPEN GRAPH META TAGS
// =========================================================
add_action( 'wp_head', 'rh_output_open_graph_meta_tags', 1 );
function rh_output_open_graph_meta_tags() {
    $site_name = get_bloginfo( 'name' );
    $lang = function_exists( 'pll_current_language' )? pll_current_language(): 'el';
    if ( empty( $lang ) ) {$lang = 'el';}
    $og_locale = ( $lang === 'en' ) ? 'en_US' : 'el_GR';
    
    if ( is_singular( 'property' ) ) {
        $post_id = get_queried_object_id();
        $title = get_the_title( $post_id );
        if ( empty( $title ) ) {$title = $site_name;}

        $description = get_post_field('post_content',$post_id);
        $description = strip_shortcodes( $description );
        $description = wp_strip_all_tags( $description );
        $description = html_entity_decode($description,ENT_QUOTES,'UTF-8');
        $description = preg_replace('/\s+/',' ',$description);
        $description = trim( $description );
        if ( mb_strlen( $description, 'UTF-8' ) > 300 ) {
            $description = mb_substr(
                $description,
                0,
                297,
                'UTF-8'
            ) . '...';
        }
        if ( empty( $description ) ) { $description = get_bloginfo( 'description' );}

        $url = get_permalink( $post_id );

        $image = '';
        $thumbnail_id = get_post_thumbnail_id( $post_id );
        
        if ( empty( $thumbnail_id ) ) {
            $gallery = get_post_meta( $post_id, 'REAL_HOMES_gallery', true );
            if ( ! empty( $gallery ) ) {
                $gallery_ids = array_filter( array_map( 'intval', explode( ',', $gallery ) ) );
                if ( ! empty( $gallery_ids ) ) {
                    $thumbnail_id = reset( $gallery_ids );
                }
            }
        }

        // --- DIRECT AWS GHOST IMAGE EXTRACTION ---
        if ( ! empty( $thumbnail_id ) ) {
            $aws_url = get_post_meta( $thumbnail_id, '_external_aws_url', true );
            if ( ! empty( $aws_url ) ) {
                $image = $aws_url;
            } else {
                $image = wp_get_attachment_url( $thumbnail_id );
            }
        }

        echo "\n<!-- RealHomes Custom Open Graph -->\n";
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' .
            esc_attr( $title ) .
            '">' . "\n";
        echo '<meta property="fb:app_id" content="966242223397117" />'. "\n";
        echo '<meta property="og:description" content="' .
            esc_attr( $description ) .
            '">' . "\n";
        echo '<meta property="og:url" content="' .
            esc_url( $url ) .
            '">' . "\n";
        echo '<meta property="og:site_name" content="' .
            esc_attr( $site_name ) .
            '">' . "\n";
        echo '<meta property="og:locale" content="' .
                esc_attr( $og_locale ) .
                '">' . "\n";
        if ( ! empty( $image ) ) {
            echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
            echo '<meta property="og:image:secure_url" content="' . esc_url( $image ) . '">' . "\n";
            echo '<meta property="og:image:type" content="image/jpeg">' . "\n";
        }
        else {
            echo '<meta property="og:image" content="https://cdn4.mesites.net.gr/00/10/04/1004.jpg">' . "\n";
            echo '<meta property="og:image:secure_url" content="https://cdn4.thessaloniki-realestate.gr/00/10/04/1004.jpg">' . "\n";
            echo '<meta property="og:image:type" content="image/jpeg">' . "\n";
            echo '<meta property="og:image:width" content="1200">' . "\n";
            echo '<meta property="og:image:height" content="630">' . "\n";
        }

        return;
    }

    $title = wp_get_document_title();
    
    // Inject the custom description for the home and search pages
    if ( is_front_page() || is_home() || is_page_template('templates/properties-search.php') || is_page('properties-search') ) {
        if ( $lang === 'en' ) {
            $description = 'Our agency offers studios and apartments for sale or rent, primarily in the center of Thessaloniki.';
        } else {
            $description = 'Το γραφείο μας, διαθέτει για πώληση ή ενοικίαση studios, διαμερίσματα και γκαρσονιέρες κυρίως στο κέντρο της Θεσσαλονίκης.';
        }
    } else {
        $description = get_bloginfo( 'description' );
    }

    $url = home_url(
        add_query_arg(
            array(),
            $GLOBALS['wp']->request
        )
    );
    echo "\n<!-- RealHomes Custom Open Graph -->\n";
    echo '<meta property="og:type" content="website">' . "\n";
    echo '<meta property="og:title" content="' .
        esc_attr( $title ) .
        '">' . "\n";
    echo '<meta property="og:description" content="' .
        esc_attr( $description ) .
        '">' . "\n";
    echo '<meta property="og:url" content="' .
        esc_url( $url ) .
        '">' . "\n";
    echo '<meta property="og:site_name" content="' .
        esc_attr( $site_name ) .
        '">' . "\n";
    echo '<meta property="og:locale" content="' .
        esc_attr( $og_locale ) .
        '">' . "\n";

    // ENHANCED FALLBACK IMAGE FOR HOME PAGE & STANDARD PAGES
    echo '<meta property="og:image" content="https://cdn4.mesites.net.gr/00/10/04/1004.jpg">' . "\n";
    echo '<meta property="og:image:secure_url" content="https://cdn4.mesites.net.gr/00/10/04/1004.jpg">' . "\n";
    echo '<meta property="og:image:type" content="image/jpeg">' . "\n";
    echo '<meta property="og:image:width" content="1200">' . "\n";
    echo '<meta property="og:image:height" content="630">' . "\n";
    echo '<meta property="og:image:alt" content="' . esc_attr( $site_name ) . '">' . "\n";

    echo "<!-- /RealHomes Custom Open Graph -->\n";
}


// =========================================================
// PRICE RENDERING GLITCH FIX
// =========================================================
add_filter( 'ere_property_price', 'rh_force_remove_decimal_glitch', 999 );
add_filter( 'inspiry_property_price', 'rh_force_remove_decimal_glitch', 999 );

function rh_force_remove_decimal_glitch( $price_html ) {
    // Targets a comma or dot followed by 4 or more zeroes (the glitch) and strips them.
    // Standard thousands separators (e.g., 195.000) are safely ignored because they only have 3 zeroes.
    return preg_replace('/[,.]0000+/', '', $price_html);
}

// =========================================================
// CUSTOM HOMEPAGE TITLE & DESCRIPTION OVERRIDE
// =========================================================
add_filter( 'rank_math/frontend/title', 'rh_force_homepage_title', 99 );
add_filter( 'pre_get_document_title', 'rh_force_homepage_title', 99 );

function rh_force_homepage_title( $title ) {
    if ( is_front_page() || is_home() || is_page_template('templates/properties-search.php') || is_page('properties-search') ) {
        return 'Thessaloniki Real Estate';
    }
    return $title;
}

add_filter( 'rank_math/frontend/description', 'rh_force_homepage_description', 99 );
function rh_force_homepage_description( $description ) {
    if ( is_front_page() || is_home() || is_page_template('templates/properties-search.php') || is_page('properties-search') ) {
        $lang = function_exists('pll_current_language') ? pll_current_language() : 'el';
        
        if ( $lang === 'en' ) {
            return 'Our agency offers studios and apartments for sale or rent, primarily in the center of Thessaloniki.';
        } else {
            return 'Το γραφείο μας, διαθέτει για πώληση ή ενοικίαση studios, διαμερίσματα και γκαρσονιέρες κυρίως στο κέντρο της Θεσσαλονίκης.';
        }
    }
    return $description;
}

// =========================================================
// KILL DUPLICATE NATIVE AJAX LOCATION SCRIPT
// =========================================================
add_action( 'wp_enqueue_scripts', 'rh_kill_duplicate_ajax_scripts', 999 );
function rh_kill_duplicate_ajax_scripts() {
    // We only want the Elementor (RHEA) search script to run.
    // This forcefully removes the parent theme's native location script.
    wp_dequeue_script( 'realhomes-locations' );
    wp_deregister_script( 'realhomes-locations' );
}

// =========================================================
// KILL DUPLICATE NATIVE AJAX LOCATION SCRIPT (BROWSER LEVEL)
// =========================================================
add_action( 'wp_footer', 'rh_block_duplicate_ajax_request', 1 );
function rh_block_duplicate_ajax_request() {
    ?>
    <script type="text/javascript">
        if (typeof jQuery !== 'undefined') {
            jQuery.ajaxPrefilter(function(options, originalOptions, jqXHR) {
                // If the request contains the native theme's location action, kill it instantly
                if (options.data && typeof options.data === 'string' && options.data.indexOf('action=realhomes_get_location_options') !== -1) {
                    jqXHR.abort();
                }
            });
        }
    </script>
    <?php
}


// =========================================================
// MAP MARKER PERFORMANCE FIX (REAL THUMBNAILS)
// =========================================================
add_filter( 'realhomes_properties_on_map', 'rh_force_actual_thumbnails_for_map', 99 );
add_filter( 'inspiry_properties_on_map', 'rh_force_actual_thumbnails_for_map', 99 );

function rh_force_actual_thumbnails_for_map( $properties_data ) {
    if ( empty( $properties_data ) || ! is_array( $properties_data ) ) {
        return $properties_data;
    }

    foreach ( $properties_data as $key => $property ) {
        if ( ! empty( $property['thumb'] ) ) {
            
            // Target only your CRM CDN images
            if ( strpos( $property['thumb'], 'cdn' ) !== false ) {
                
                // 1. Swap the vanity domain back to the native CRM domain
                $thumb_url = str_replace( 'thessaloniki-realestate.gr', 'mesites.net.gr', $property['thumb'] );
                
                // 2. Ensure we use the large image as the source, not the tiny t.jpg
                $thumb_url = str_replace( 't.jpg', 'l.jpg', $thumb_url );
                
                // 3. Strip the protocol for the proxy
                $clean_url = str_replace( ['https://', 'http://'], '', $thumb_url );
                
                // 4. Route through the free proxy: 400px width, WebP format, 80% quality
                $properties_data[$key]['thumb'] = 'https://wsrv.nl/?url=' . $clean_url . '&w=400&output=webp&q=80';
            }
        }
    }

    return $properties_data;
}

// =========================================================
// MAP MARKER PERFORMANCE FIX (HEAD INTERCEPTOR)
// =========================================================
add_action('wp_head', 'rh_early_map_data_interceptor', 1);
function rh_early_map_data_interceptor() {
    ?>
    <script type="text/javascript">
        (function() {
            // Trap the map data array before the DOM even finishes loading
            let _internalMapData = window.propertiesMapData || [];
            Object.defineProperty(window, 'propertiesMapData', {
                get: function() { return _internalMapData; },
                set: function(newData) {
                    if (Array.isArray(newData)) {
                        newData.forEach(function(item) {
                            if (item && item.thumb && typeof item.thumb === 'string') {
                                if (item.thumb.indexOf('cdn') !== -1) {
                                    // 1. Swap domain and ensure we use the large image
                                    let cleanUrl = item.thumb
                                        .replace(/^https?:\/\//, '')
                                        .replace(/thessaloniki-realestate\.gr/g, 'mesites.net.gr')
                                        .replace(/t\.jpg/g, 'l.jpg');
                                    
                                    // 2. Route through free proxy
                                    item.thumb = 'https://wsrv.nl/?url=' + cleanUrl + '&w=400&output=webp&q=80';
                                }
                            }
                        });
                    }
                    _internalMapData = newData;
                },
                configurable: true
            });
        })();
    </script>
    <?php
}

// =========================================================
// DEFER HEAVY THIRD-PARTY SCRIPTS (RECAPTCHA)
// =========================================================
add_filter( 'script_loader_tag', 'rh_defer_heavy_third_party_scripts', 10, 3 );
function rh_defer_heavy_third_party_scripts( $tag, $handle, $src ) {
    // Target Google reCAPTCHA
    if ( strpos( $src, 'recaptcha/api.js' ) !== false ) {
        // Only defer if it hasn't already been deferred or async'd
        if ( strpos( $tag, 'defer' ) === false && strpos( $tag, 'async' ) === false ) {
            return str_replace( ' src', ' defer="defer" src', $tag );
        }
    }
    return $tag;
}

// =========================================================
// LAZY-LOAD RECAPTCHA ON USER INTERACTION
// =========================================================
add_action( 'wp_print_scripts', 'rh_dequeue_recaptcha_for_lazy_load', 100 );
function rh_dequeue_recaptcha_for_lazy_load() {
    if ( ! is_admin() ) {
        wp_dequeue_script( 'inspiry-google-recaptcha' );
        wp_deregister_script( 'inspiry-google-recaptcha' );
    }
}

add_action( 'wp_footer', 'rh_inject_lazy_recaptcha_loader', 999 );
function rh_inject_lazy_recaptcha_loader() {
    ?>
    <script type="text/javascript">
        (function() {
            let recaptchaLoaded = false;
            function loadRecaptchaOnInteraction() {
                if (recaptchaLoaded) return;
                recaptchaLoaded = true;

                // Remove interaction listeners
                ['touchstart', 'scroll', 'mousemove', 'keydown', 'click'].forEach(function(evt) {
                    window.removeEventListener(evt, loadRecaptchaOnInteraction, { passive: true });
                });

                // Dynamically inject reCAPTCHA
                let script = document.createElement('script');
                script.src = 'https://www.google.com/recaptcha/api.js?render=6LdRtaMtAAAAAO5m2tzRh3Dsuue3eu1PwAbgLVyR&onload=loadInspiryReCAPTCHA&ver=4.5.5';
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            }

            // Trigger on user movement or immediately if clicking login
            ['touchstart', 'scroll', 'mousemove', 'keydown'].forEach(function(evt) {
                window.addEventListener(evt, loadRecaptchaOnInteraction, { passive: true, once: true });
            });
            jQuery(document).on('click', '.rh_login_tab, #login-button, .user-menu-wrapper', loadRecaptchaOnInteraction);
        })();
    </script>
    <?php
}


// =========================================================
// REMOVE UNNECESSARY CORE JS FROM SEARCH PAGE
// =========================================================
add_action( 'wp_enqueue_scripts', 'rh_clean_unused_search_scripts', 99 );
function rh_clean_unused_search_scripts() {
    if ( is_page_template( 'templates/properties-search.php' ) || is_page( 'properties-search' ) ) {
        wp_dequeue_script( 'comment-reply' );
    }
}
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );


// Asynchronous Non-Blocking CSS Loading
add_filter( 'style_loader_tag', function( $html, $handle ) {
    $async_handles = [ 'leaflet-css', 'font-awesome-5-all', 'flatpickr-css' ];
    if ( ! is_admin() && in_array( $handle, $async_handles, true ) ) {
        return str_replace( 
            "rel='stylesheet'", 
            "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", 
            $html 
        );
    }
    return $html;
}, 10, 2 );


// =========================================================
// DEFER EXTERNAL LEAFLET MAP CSS
// =========================================================
add_filter( 'style_loader_tag', 'rh_defer_leaflet_css', 10, 2 );
function rh_defer_leaflet_css( $html, $handle ) {
    // Only target the specific Leaflet stylesheet on the frontend
    if ( ! is_admin() && $handle === 'leaflet-css' ) {
        // Swap 'stylesheet' for 'preload' so it downloads in the background, 
        // then instantly flips back to a stylesheet once finished.
        return str_replace( 
            "rel='stylesheet'", 
            "rel='preload' as='style' onload=\"this.onload=null;this.rel='stylesheet'\"", 
            $html 
        );
    }
    return $html;
}

// =========================================================
// PRELOAD LCP HERO IMAGE (MOBILE PERFORMANCE)
// =========================================================
add_action( 'wp_head', 'rh_preload_lcp_hero_image', 1 );
function rh_preload_lcp_hero_image() {
    // Only execute on the homepage to avoid wasting bandwidth on subpages
    if ( is_front_page() || is_home() || is_page_template('templates/properties-search.php') || is_page('properties-search') ) {
        // Force the browser to instantly fetch the massive Elementor background image
        echo '<link rel="preload" as="image" href="https://www.thessaloniki-realestate.gr/wp-content/uploads/2026/09/slide-three-1_new.webp">' . "\n";
    }
}


// =========================================================
// PROPERTY CARDS: FREE WEBP & RESIZE PROXY (ZERO AWS COST)
// =========================================================
add_filter( 'wp_get_attachment_image_attributes', 'rh_free_proxy_resize_external_images', 99, 3 );
function rh_free_proxy_resize_external_images( $attr, $attachment, $size ) {
    // Target external CDN images currently using the 'l.jpg' (large) suffix
    if ( isset( $attr['src'] ) && strpos( $attr['src'], 'cdn' ) !== false && strpos( $attr['src'], 'l.jpg' ) !== false ) {
        
        $thumb_sizes = ['property-thumb-image', 'modern-property-child-slider', 'thumbnail', 'medium'];
        
        if ( in_array( $size, $thumb_sizes ) || ( is_array( $size ) && $size[0] <= 800 ) ) {
            // Strip the protocol as required by the proxy
            $clean_url = str_replace( ['https://', 'http://'], '', $attr['src'] );
            
            // Route through the free proxy: 600px width, WebP format, 80% quality
            $attr['src'] = 'https://wsrv.nl/?url=' . $clean_url . '&w=600&output=webp&q=80';
            
            // Destroy the srcset so the mobile browser doesn't ignore our 
            // proxy and default back to downloading the massive S3 file
            if ( isset( $attr['srcset'] ) ) {
                unset( $attr['srcset'] );
                unset( $attr['sizes'] );
            }
        }
    }
    return $attr;
}
