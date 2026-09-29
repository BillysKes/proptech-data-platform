<?php
// --- GLOBAL VARIABLE HACK ---
if (empty($_GET) && !empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?') !== false) {
    $uri_parts = explode('?', $_SERVER['REQUEST_URI']);
    if (!empty($uri_parts[1])) {
        parse_str($uri_parts[1], $rescued_vars);
        $_GET = array_merge($_GET, $rescued_vars);
        $_REQUEST = array_merge($_REQUEST, $rescued_vars);
        $_SERVER['QUERY_STRING'] = $uri_parts[1];
    }
}

// =========================================================================
// HELPER: EXTRACT SEARCH VALUES
// =========================================================================
function rh_get_search_request_val($key, $as_array = false) {
    $val = null;
    $alt_key = str_replace('.', '_', $key); 
    
    if (isset($_REQUEST[$key])) { $val = $_REQUEST[$key]; }
    elseif (isset($_REQUEST[$alt_key])) { $val = $_REQUEST[$alt_key]; }
    
    if ($val === null && !empty($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $parsed_qs);
        if (isset($parsed_qs[$key])) { $val = $parsed_qs[$key]; } elseif (isset($parsed_qs[$alt_key])) { $val = $parsed_qs[$alt_key]; }
    }
    
    if ($val === null && !empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '?') !== false) {
        $uri_parts = explode('?', $_SERVER['REQUEST_URI']);
        if (!empty($uri_parts[1])) {
            parse_str($uri_parts[1], $parsed_uri);
            if (isset($parsed_uri[$key])) { $val = $parsed_uri[$key]; } elseif (isset($parsed_uri[$alt_key])) { $val = $parsed_uri[$alt_key]; }
        }
    }
    
    if ($val === null && wp_doing_ajax() && !empty($_SERVER['HTTP_REFERER'])) {
        $referer_url = wp_parse_url($_SERVER['HTTP_REFERER']);
        if (!empty($referer_url['query'])) {
            parse_str($referer_url['query'], $parsed_ref);
            if (isset($parsed_ref[$key])) { $val = $parsed_ref[$key]; } elseif (isset($parsed_ref[$alt_key])) { $val = $parsed_ref[$alt_key]; }
        }
    }
    
    if ($val === null) return $as_array ? [] : null;

    if ($as_array) {
        if (!is_array($val)) $val = [$val];
        return array_filter($val, function($v) { 
            $lower = mb_strtolower($v, 'UTF-8');
            return $v !== '' && $lower !== 'any' && $lower !== 'αδιάφορο'; 
        });
    } else {
        $val = is_array($val) ? current($val) : $val;
        $lower = mb_strtolower($val, 'UTF-8');
        return ($val !== '' && $lower !== 'any' && $lower !== 'αδιάφορο') ? sanitize_text_field($val) : null;
    }
}

// =========================================================================
// CUSTOM SEARCH FILTERS (BILINGUAL & MULTI-KEY)
// =========================================================================
add_action('pre_get_posts', 'override_realhomes_custom_search', 9999);
function override_realhomes_custom_search($query) {
    if (is_admin() && !wp_doing_ajax()) return;

    $post_type = $query->get('post_type');
    $is_property_query = ($post_type === 'property' || (is_array($post_type) && in_array('property', $post_type)));
    if ( !$is_property_query ) return; 

    $search_keys = ['advance_search', 'type', 'status', 'inspiry_Τελευταίος_όροφος', 'inspiry_Ύδρευση/Άρδευση', 'inspiry_wc', 'inspiry_Ελάχ._επίπλωση', 'inspiry_Ελάχ._εξοπλισμός', 'inspiry_Μπαλκόνι', 'inspiry_Ανελκυστήρας'];
    $is_active_search = false;
    
    foreach ($search_keys as $s_key) {
        if (rh_get_search_request_val($s_key) !== null || !empty(rh_get_search_request_val($s_key, true))) { 
            $is_active_search = true; 
            break; 
        }
    }
    
    if (!$is_active_search) return;

    $meta_query = $query->get('meta_query');
    if (!is_array($meta_query)) $meta_query = [];

    // Strip default logic to apply multi-key overrides
    $custom_keys_to_intercept = [
        'inspiry_Έτος_κατασκευής_μετά_το', 'inspiry_Μέγιστη_τιμή_αγοράς_ανά_τ.μ.', 'inspiry_Απόδοση_πάνω_από', 
        'inspiry_Μέγιστη_απόσταση_από_παραλία', 'inspiry_Ύδρευση/Άρδευση', 'inspiry_Ανελκυστήρας', 'inspiry_wc', 
        'inspiry_wc_type', 'inspiry_Τύπος_wc', 'inspiry_τύπος_wc', 'inspiry_Τελευταίος_όροφος', 'inspiry_Μπαλκόνι', 
        'inspiry_Ελάχ._όροφος', 'inspiry_Μέγ._όροφος', 'inspiry_Ελάχ._επίπλωση', 'inspiry_Ελάχ._εξοπλισμός', 
        'inspiry_Όχληση', 'REAL_HOMES_property_bedrooms', 'REAL_HOMES_property_bathrooms'  
    ];

    foreach ($meta_query as $index => $clause) {
        if (is_array($clause) && isset($clause['key']) && in_array($clause['key'], $custom_keys_to_intercept)) {
            unset($meta_query[$index]);
        }
    }

    $meta_query['relation'] = 'AND';

    // Core fields
    if ($val = rh_get_search_request_val('bedrooms')) $meta_query[] = [ 'key' => 'REAL_HOMES_property_bedrooms', 'value' => intval($val), 'compare' => '>=', 'type' => 'NUMERIC' ];
    if ($val = rh_get_search_request_val('bathrooms')) $query->set('custom_bath_wc_sum', intval($val));

    // Numeric Matchers
    if ($val = rh_get_search_request_val('inspiry_Έτος_κατασκευής_μετά_το')) $meta_query[] = [ 'key' => 'REAL_HOMES_property_year_built', 'value' => intval($val), 'compare' => '>=', 'type' => 'NUMERIC' ];
    if ($val = rh_get_search_request_val('inspiry_Μέγιστη_τιμή_αγοράς_ανά_τ.μ.')) $meta_query[] = [ 'key' => 'REAL_HOMES_price_per_sqm', 'value' => floatval($val), 'compare' => '<=', 'type' => 'NUMERIC' ];
    if ($val = rh_get_search_request_val('inspiry_Απόδοση_πάνω_από')) $meta_query[] = [ 'key' => 'REAL_HOMES_return_pc', 'value' => floatval($val), 'compare' => '>=', 'type' => 'NUMERIC' ];
    if ($val = rh_get_search_request_val('inspiry_Μέγιστη_απόσταση_από_παραλία')) $meta_query[] = [ 'key' => 'REAL_HOMES_beach_dist', 'value' => intval($val), 'compare' => '<=', 'type' => 'NUMERIC' ];
    if ($val = rh_get_search_request_val('inspiry_Ελάχ._όροφος')) $meta_query[] = [ 'key' => 'REAL_HOMES_floor_number', 'value' => floatval($val), 'compare' => '>=', 'type' => 'DECIMAL' ];
    if ($val = rh_get_search_request_val('inspiry_Μέγ._όροφος')) $meta_query[] = [ 'key' => 'REAL_HOMES_floor_number', 'value' => floatval($val), 'compare' => '<=', 'type' => 'DECIMAL' ];
    


    // Elevator / Lift
    if ($val = rh_get_search_request_val('inspiry_Ανελκυστήρας')) {
        preg_match('/\d+/', $val, $matches);
        $lift_number = !empty($matches[0]) ? intval($matches[0]) : 1;
        
        if ($lift_number == 1) {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_crm_lift', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
                [ 'key' => 'REAL_HOMES_floor_number', 'value' => [-0.5, 0.5], 'compare' => 'BETWEEN', 'type' => 'DECIMAL' ]
            ];
        } else {
            $meta_query[] = [
                'relation' => 'OR',
                [ 'key' => '_crm_lift', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ],
                [ 'key' => 'REAL_HOMES_floor_number', 'value' => $lift_number, 'compare' => '<', 'type' => 'DECIMAL' ]
            ];
        }
    }

    // WC & WC Type (Count is native, Type is CRM ID: 1=Private, 2=Potential, 3=Ext. Private, 4=Shared)
    $wc_val = rh_get_search_request_val('inspiry_wc') ?: rh_get_search_request_val('inspiry_wc_type');
    if ($wc_val) {
        if (is_numeric(trim($wc_val))) {
            $meta_query[] = [ 'key' => 'inspiry_wc', 'value' => intval($wc_val), 'compare' => '>=', 'type' => 'NUMERIC' ];
        } else {
            $lower_val = mb_strtolower(trim($wc_val), 'UTF-8');
            if (in_array($lower_val, ['ατομικό ή κοινόχρηστο', 'private or shared'])) {
                $meta_query[] = [ 'key' => '_crm_wctype', 'value' => [1, 2, 3, 4], 'compare' => 'IN', 'type' => 'NUMERIC' ];
            } elseif (in_array($lower_val, ['ατομικό', 'private'])) {
                $meta_query[] = [ 'key' => '_crm_wctype', 'value' => [1, 2, 3], 'compare' => 'IN', 'type' => 'NUMERIC' ];
            } elseif (in_array($lower_val, ['κοινόχρηστο', 'shared'])) {
                $meta_query[] = [ 'key' => '_crm_wctype', 'value' => 4, 'compare' => '=', 'type' => 'NUMERIC' ];
            }
        }
    }

    // Top Floor (CRM: 1=Yes, -1=No. Enforced strict < 0 for No.)
    if ($val = rh_get_search_request_val('inspiry_Τελευταίος_όροφος')) {
        $lower_val = mb_strtolower(trim($val), 'UTF-8');
        if (in_array($lower_val, ['ναι', 'yes'])) {
            $meta_query[] = [ 'key' => '_crm_lastfloor', 'value' => 1, 'compare' => '=', 'type' => 'NUMERIC' ];
        } elseif (in_array($lower_val, ['όχι', 'no'])) {
            $meta_query[] = [ 'key' => '_crm_lastfloor', 'value' => 0, 'compare' => '<', 'type' => 'NUMERIC' ];
        }
    }

    // Balcony (CRM: 1=Yes, 2=Small, 3=Large)
    if ($val = rh_get_search_request_val('inspiry_Μπαλκόνι')) {
        $lower_val = mb_strtolower(trim($val), 'UTF-8');
        if (in_array($lower_val, ['ναι', 'yes'])) {
            $meta_query[] = [ 'key' => '_crm_balcony', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ];
        } elseif (in_array($lower_val, ['μεγάλο', 'large'])) {
            $meta_query[] = [ 'key' => '_crm_balcony', 'value' => 3, 'compare' => '=', 'type' => 'NUMERIC' ];
        }
    }

    // Equipment (CRM: <0=No, >=1=Semi, >=2=Full)
    if ($val = rh_get_search_request_val('inspiry_Ελάχ._εξοπλισμός')) {
        $lower_val = mb_strtolower(trim($val), 'UTF-8');
        if (in_array($lower_val, ['ναι', 'yes'])) {
            $meta_query[] = [ 'key' => '_crm_equipped', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ];
        } elseif (in_array($lower_val, ['χωρίς εξοπλισμό', 'un-equipped'])) {
            $meta_query[] = [ 'key' => '_crm_equipped', 'value' => 0, 'compare' => '<', 'type' => 'NUMERIC' ];
        } elseif (in_array($lower_val, ['ημιεξοπλισμένο', 'semi-equipped'])) {
            $meta_query[] = [ 'key' => '_crm_equipped', 'value' => 1, 'compare' => '>=', 'type' => 'NUMERIC' ];
        } else {
            $meta_query[] = [ 'key' => '_crm_equipped', 'value' => 2, 'compare' => '>=', 'type' => 'NUMERIC' ];
        }
    }

    // Furnishing (CRM: <0=No, >=1=Semi, >=2=Full)
    if ($val = rh_get_search_request_val('inspiry_Ελάχ._επίπλωση')) {
        $lower_val = mb_strtolower(trim($val), 'UTF-8');
        if (in_array($lower_val, ['ναι', 'yes'])) {
            $meta_query[] = [ 'key' => '_crm_furnished', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ];
        } elseif (in_array($lower_val, ['χωρίς επίπλωση', 'unfurnished'])) {
            $meta_query[] = [ 'key' => '_crm_furnished', 'value' => 0, 'compare' => '<', 'type' => 'NUMERIC' ];
        } elseif (in_array($lower_val, ['ημιεπιπλωμένο', 'semi-furnished'])) {
            $meta_query[] = [ 'key' => '_crm_furnished', 'value' => 1, 'compare' => '>=', 'type' => 'NUMERIC' ];
        } else {
            $meta_query[] = [ 'key' => '_crm_furnished', 'value' => 2, 'compare' => '>=', 'type' => 'NUMERIC' ];
        }
    }

    // Water / Irrigation (CRM: 1=Yes, 2=Supply, 3/5=Irrigation, 4=Borehole. Excluding 6=Near)
    $water_vals = rh_get_search_request_val('inspiry_Ύδρευση/Άρδευση', true);
    if (!empty($water_vals)) {
        $water_query = [ 'relation' => 'OR' ];
        foreach ( $water_vals as $val ) {
            $clean_val = mb_strtolower( trim( sanitize_text_field( $val ) ), 'UTF-8' );
            if (in_array($clean_val, ['με αρδευτικό δίκτυο', 'irrigation network'])) {
                $water_query[] = [ 'key' => '_crm_water', 'value' => [3, 5], 'compare' => 'IN', 'type' => 'NUMERIC' ];
            } elseif (in_array($clean_val, ['με γεώτρηση', 'borehole'])) {
                $water_query[] = [ 'key' => '_crm_water', 'value' => 4, 'compare' => '=', 'type' => 'NUMERIC' ];
            } elseif (in_array($clean_val, ['με ύδρευση', 'water supply', 'ναι', 'yes'])) {
                $water_query[] = [ 'key' => '_crm_water', 'value' => [1, 2], 'compare' => 'IN', 'type' => 'NUMERIC' ];
            }
        }
        if ( count( $water_query ) > 1 ) $meta_query[] = $water_query;
    }

    if (!empty($meta_query)) {
        $query->set('meta_query', $meta_query);
    }
}

// =========================================================================
// 3. BATHROOMS + WC SUM CLAUSES
// =========================================================================
add_filter('posts_clauses', 'rh_custom_bath_wc_sum_clauses', 10, 2);
function rh_custom_bath_wc_sum_clauses($clauses, $query) {
    $sum_val = $query->get('custom_bath_wc_sum');
    
    if ($sum_val) {
        global $wpdb;
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_bath ON ({$wpdb->posts}.ID = pm_bath.post_id AND pm_bath.meta_key = 'REAL_HOMES_property_bathrooms')";
        $clauses['join'] .= " LEFT JOIN {$wpdb->postmeta} AS pm_wc ON ({$wpdb->posts}.ID = pm_wc.post_id AND pm_wc.meta_key = 'inspiry_wc')";
        
        $clauses['where'] .= $wpdb->prepare(
            " AND ( CAST(IFNULL(NULLIF(pm_bath.meta_value, ''), '0') AS UNSIGNED) + CAST(IFNULL(NULLIF(pm_wc.meta_value, ''), '0') AS UNSIGNED) ) >= %d", 
            $sum_val
        );
        
        if (empty($clauses['groupby']) || strpos($clauses['groupby'], "{$wpdb->posts}.ID") === false) {
            $clauses['groupby'] = "{$wpdb->posts}.ID";
        }
    }
    
    return $clauses;
}

// =========================================================================
// 4. REDIRECT TO SINGLE PROPERTY ON EXACT ID MATCH
// =========================================================================
add_action('template_redirect', 'rh_custom_id_search_redirect');
function rh_custom_id_search_redirect() {
    if (isset($_GET['property-id']) && !empty(trim($_GET['property-id']))) {
        $search_id = sanitize_text_field(trim($_GET['property-id']));
        
        $args = [
            'post_type' => 'property',
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key'     => 'REAL_HOMES_property_id',
                    'value'   => $search_id,
                    'compare' => '='
                ]
            ]
        ];
        
        $query = new WP_Query($args);
        
        if ($query->have_posts()) {
            $property_permalink = get_permalink($query->posts[0]->ID);
            wp_safe_redirect($property_permalink);
            exit;
        } else {
            wp_safe_redirect(site_url('/με-κωδικο/?not_found=1'));
            exit;
        }
    }
}

// =========================================================================
// INJECT SEARCHED LOCATION POLYGONS TO MAP
// =========================================================================
add_action('wp_footer', 'rh_inject_searched_location_polygons', 99);
function rh_inject_searched_location_polygons() {
    // Grab the active locations from the search parameters
    $locations = rh_get_search_request_val('location', true); 
    
    //If we are on a direct location category page instead of the search page
    if (empty($locations) && is_tax('property-city')) {
        $term = get_queried_object();
        if ($term) {
            $locations = [$term->slug];
        }
    }

    if (!empty($locations)) {
        $polygons = [];
        foreach ($locations as $loc_slug) {
            $term = get_term_by('slug', sanitize_title($loc_slug), 'property-city');
            if ($term && !is_wp_error($term)) {
                $poly_data = get_term_meta($term->term_id, 'location_polygon', true);
                if (!empty($poly_data)) {
                    $decoded = json_decode($poly_data, true);
                    if (is_array($decoded)) {
                        $polygons[] = $decoded;
                    }
                }
            }
        }
        
        if (!empty($polygons)) {
            echo "<script>window.rhSearchedLocationPolygons = " . json_encode($polygons) . ";</script>";
        }
    }
}


// =========================================================================
//  PRELOAD ALL MAP POLYGONS
// =========================================================================
add_action('wp_footer', 'rh_preload_all_location_polygons', 99);
function rh_preload_all_location_polygons() {
    $terms = get_terms([
        'taxonomy' => 'property-city',
        'hide_empty' => false,
        'meta_query' => [['key' => 'location_polygon', 'compare' => 'EXISTS']]
    ]);

    $all_polygons = [];
    if (!is_wp_error($terms) && !empty($terms)) {
        foreach ($terms as $term) {
            $poly_data = get_term_meta($term->term_id, 'location_polygon', true);
            if (!empty($poly_data)) {
                $decoded = json_decode($poly_data, true);
                if (is_array($decoded)) {
                    $all_polygons[$term->slug] = $decoded;
                }
            }
        }
    }
    
    if (!empty($all_polygons)) {
        echo "<script>window.rhAllLocationPolygons = " . json_encode($all_polygons) . ";</script>";
    }
}


// =========================================================================
//  CUSTOM SORTING OVERRIDES (PRICE PER SQM)
// =========================================================================
add_action('pre_get_posts', 'rh_custom_sorting_override', 9999);
function rh_custom_sorting_override($query) {
    if (is_admin() && !wp_doing_ajax()) return;

    // Apply only if this is a property query (Search or Archive)
    $post_type = $query->get('post_type');
    $is_property_query = ($post_type === 'property' || (is_array($post_type) && in_array('property', $post_type)));
    
    if (!$is_property_query) return;

    $sortby = isset($_GET['sortby']) ? sanitize_text_field($_GET['sortby']) : '';
    
    if ($sortby === 'price-sqm-asc') {
        $query->set('meta_key', 'REAL_HOMES_price_per_sqm');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'ASC');
    } elseif ($sortby === 'price-sqm-desc') {
        $query->set('meta_key', 'REAL_HOMES_price_per_sqm');
        $query->set('orderby', 'meta_value_num');
        $query->set('order', 'DESC');
    }
}

// =========================================================================
// SIMILAR PROPERTIES: ENFORCE MUNICIPALITY (PRE_GET_POSTS INTERCEPTOR)
// =========================================================================
add_action( 'pre_get_posts', 'rh_force_municipality_on_similar_properties', 9999 );
function rh_force_municipality_on_similar_properties( $query ) {
    // 1. Only run on the frontend for secondary property queries
    if ( is_admin() || $query->is_main_query() ) {
        return;
    }

    $post_type = $query->get('post_type');
    if ( $post_type !== 'property' && (is_array($post_type) && !in_array('property', $post_type)) ) {
        return;
    }

    // 2. Only apply this logic while viewing a Single Property page
    if ( ! is_singular('property') ) {
        return;
    }

    $property_id = get_queried_object_id();
    
    // 3. The Fingerprint: "Similar Properties" always excludes the current property ID
    $post_not_in = $query->get('post__not_in');
    if ( empty( $post_not_in ) || ! is_array( $post_not_in ) || ! in_array( $property_id, $post_not_in ) ) {
        return; // If it doesn't exclude the current property, abort.
    }

    // 4. Intercept the Location Query Array
    $tax_query = $query->get('tax_query');
    if ( empty( $tax_query ) || ! is_array( $tax_query ) ) {
        return;
    }

    $city_query_index = -1;
    foreach ( $tax_query as $index => $tq ) {
        if ( is_array( $tq ) && isset( $tq['taxonomy'] ) && $tq['taxonomy'] === 'property-city' ) {
            $city_query_index = $index;
            break;
        }
    }

    // 5. If it attempts to filter by city, overwrite it with Level 2 Municipality logic
    if ( $city_query_index !== -1 ) {
        $terms = wp_get_post_terms( $property_id, 'property-city' );
        $level_2_terms = [];

        if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
            foreach ( $terms as $term ) {
                // Find how deep the current location is
                $ancestors = get_ancestors( $term->term_id, 'property-city', 'taxonomy' );
                $depth = count( $ancestors );
                
                if ( $depth === 1 ) {
                    $level_2_terms[] = $term->term_id; // It is already a Municipality
                } elseif ( $depth > 1 ) {
                    $level_2_terms[] = $ancestors[ $depth - 2 ]; // Step back up to the Municipality
                }
            }
        }

        $level_2_terms = array_unique( $level_2_terms );

        if ( ! empty( $level_2_terms ) ) {
            // Aggressively overwrite the broad Elementor location array with our strict boundaries
            $tax_query[$city_query_index]['field']            = 'term_id';
            $tax_query[$city_query_index]['terms']            = $level_2_terms;
            $tax_query[$city_query_index]['include_children'] = true;
            $tax_query[$city_query_index]['operator']         = 'IN';

            // Apply the new rules to the database query
            $query->set( 'tax_query', $tax_query );
        }
    }
}