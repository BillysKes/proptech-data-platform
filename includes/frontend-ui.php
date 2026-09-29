<?php
// --- BILINGUAL ENERGY CLASSES ---
add_filter( 'inspiry_property_energy_classes', 'custom_bilingual_energy_classes' );
function custom_bilingual_energy_classes( $classes ) {
    $is_en = function_exists('pll_current_language') && pll_current_language() == 'en';
    if ($is_en) {
        return array( 'a+' => 'A+', 'a'  => 'A', 'b+' => 'B+', 'b'  => 'B', 'c'  => 'C', 'd'  => 'D', 'e'  => 'E', 'f'  => 'F', 'g'  => 'G' );
    }
    return array( 'a+' => 'Α+', 'a'  => 'Α', 'b+' => 'B+', 'b'  => 'Β', 'c'  => 'Γ', 'd'  => 'Δ', 'e'  => 'Ε', 'f'  => 'Ζ', 'g'  => 'Η' );
}

add_filter( 'gettext', 'translate_energy_performance_labels', 20, 3 );
function translate_energy_performance_labels( $translated_text, $text, $domain ) {
    $is_en = function_exists('pll_current_language') && pll_current_language() == 'en';
    if ( $is_en ) return $translated_text;

    if ( 'framework' === $domain || 'easy-real-estate' === $domain ) {
        switch ( $text ) {
            case 'Energy Class:': return 'Ενεργειακή Κλάση:';
            case 'Energy Performance:': return 'Ενεργειακή Απόδοση:';
        }
    }
    return $translated_text;
}

add_action('wp_footer', 'force_bilingual_energy_classes_js', 9999);
function force_bilingual_energy_classes_js() {
    if ( is_singular('property') ) {
        $raw_db_value = get_post_meta(get_the_ID(), 'REAL_HOMES_energy_class', true);
        if (empty($raw_db_value)) return;
        
        $is_en = function_exists('pll_current_language') && pll_current_language() == 'en';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                const isEn = <?php echo $is_en ? 'true' : 'false'; ?>;
                const targetMap = isEn ? 
                    { 'A+': 'A+', 'A': 'A', 'B+': 'B+', 'B': 'B', 'C': 'C', 'D': 'D', 'E': 'E', 'F': 'F', 'G': 'G' } : 
                    { 'A+': 'Α+', 'A':  'Α', 'B+': 'Β+', 'B':  'Β', 'C':  'Γ',  'D':  'Δ', 'E':  'Ε',  'F':  'Ζ', 'G':  'Η' };
                
                let dbVal = "<?php echo strtoupper(sanitize_text_field($raw_db_value)); ?>";
                let dbValLower = "<?php echo strtolower(sanitize_text_field($raw_db_value)); ?>";
                let displayVal = targetMap[dbVal] || dbVal;
                let cssClass = "<?php echo strtolower(sanitize_text_field($raw_db_value)); ?>";
                
                let $energyBar = $('.energy-class, .rh-property-energy-class-list');
                if ($energyBar.length > 0 && $energyBar.find('.b\\+').length === 0) {
                    let bPlusText = isEn ? 'B+' : 'Β+';
                    $('<li class="b+">' + bPlusText + '</li>').insertAfter($energyBar.find('.a'));
                }

                $('[class*="energy"], [class*="epc"]').find('*').each(function() {
                    if ($(this).children().length === 0) { 
                        let text = $(this).text().trim();
                        if (text.indexOf('ΕΝΕΡΓΕΙΑΚΗ ΚΛΑΣΗ') !== -1 && isEn) {
                            $(this).text(text.replace('ΕΝΕΡΓΕΙΑΚΗ ΚΛΑΣΗ', 'ENERGY CLASS'));
                        } else if (text === dbValLower || text === dbVal) {
                            $(this).text(displayVal);
                        }
                    }
                });

                $energyBar.find('li').each(function() {
                    let text = $(this).text().trim().toUpperCase();
                    if (targetMap[text]) $(this).text(targetMap[text]);
                });

                if (cssClass) {
                    let safeClass = cssClass.replace('+', '\\+');
                    let $activeLi = $energyBar.find('li.' + safeClass);
                    if ($activeLi.length) {
                        if (!$('#rh-custom-arrow-fix').length) {
                            $('head').append(`<style id="rh-custom-arrow-fix"> .rh-active-energy-box { position: relative; } .rh-active-energy-box::after { content: ""; position: absolute; top: -14px; left: 50%; transform: translateX(-50%); border-width: 14px 12px 0 12px; border-style: solid; border-color: #333 transparent transparent transparent; z-index: 99; } </style>`);
                        }
                        $activeLi.addClass('rh-active-energy-box');
                    }
                }
            });
        </script>
        <?php
    }
}

// --- LOCATION HIERARCHY TREE INJECTION ---
add_action('wp_footer', 'inject_custom_location_tree_for_forms', 10);
function inject_custom_location_tree_for_forms() {
    if ( ! is_singular('page') ) return;
    
    global $post;
    $slug = isset($post->post_name) ? urldecode($post->post_name) : '';
    
    $is_form_page = (strpos($slug, 'property-request') !== false || strpos($slug, 'ζήτηση') !== false || strpos($slug, 'ανάθεση') !== false || strpos($slug, 'list-your-property') !== false);
    
    if ($is_form_page) {
        remove_filter( 'get_terms_args', 'protect_frontend_cities', 10 );
        $terms = get_terms([ 'taxonomy' => 'property-city', 'hide_empty' => false ]);
        add_filter( 'get_terms_args', 'protect_frontend_cities', 10, 2 );
    } else {
        $terms = get_terms([ 'taxonomy' => 'property-city', 'hide_empty' => true ]);
    }
    
    if ( empty($terms) || is_wp_error($terms) ) return;
    
    $tree = [];
    foreach ($terms as $term) {
        $term_name = isset($term->name) ? (string) $term->name : '';
        
        $tree[] = [ 
            'slug'   => $term->slug, 
            'name'   => html_entity_decode($term_name, ENT_QUOTES, 'UTF-8'), 
            'parent' => $term->parent, 
            'id'     => $term->term_id 
        ];
    }
    ?>
    <script> window.rhCustomLocationTree = <?php echo json_encode($tree); ?>; </script>
    <?php
}

/*
// --- CUSTOM SVG FIX ---
add_action('wp_footer', 'fix_realhomes_svg_custom_icons', 9999);
function fix_realhomes_svg_custom_icons() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('.rh_property__meta i, .rh_prop_card__meta i, .rh_meta_titles i').each(function() {
                let rawClass = $(this).attr('class');
                if (rawClass && rawClass.indexOf('<img') !== -1) $(this).replaceWith(rawClass);
            });
        });
    </script>
    <?php
}*/

// --- OVERRIDE REALHOMES GLOBAL COMMON NOTE WITH DYNAMIC NOTE ---
add_filter('option_theme_common_note', 'dynamic_property_common_note_override', 10, 2);

function dynamic_property_common_note_override($value, $option) {
    if (is_singular('property')) {
        global $post;
        $dynamic_note = get_post_meta($post->ID, 'common_note', true);
        if (!empty($dynamic_note)) {
            return $dynamic_note; 
        }
    }
    return $value; 
}

// =========================================================
// CACHEABLE LOCATION DICTIONARY (STATIC JS FILE GENERATOR)
// =========================================================

function rh_build_location_dictionary_js_file( $lang = 'el' ) {
    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['basedir'] . '/rh_property_locations_' . $lang . '.js';
    
    $locations = [];
    $properties = get_posts([
        'post_type'        => 'property',
        'post_status'      => 'publish',
        'posts_per_page'   => -1,
        'fields'           => 'ids', 
        'lang'             => $lang,
        'suppress_filters' => false
    ]);
    
    foreach ($properties as $id) {
        $address = get_post_meta($id, 'REAL_HOMES_property_address', true);
        $final_display_text = '';
        
        $primary_id = get_post_meta($id, '_yoast_wpseo_primary_property-city', true);
        if (!empty($primary_id)) {
            $primary_term = get_term($primary_id, 'property-city');
            if (!is_wp_error($primary_term) && $primary_term) {
                $final_display_text = $primary_term->name;
            }
        }
        
        if (empty($final_display_text)) {
            $terms = wp_get_post_terms($id, 'property-city');
            $deepest_term_name = '';
            $max_depth = -1;
            
            if (!is_wp_error($terms) && !empty($terms)) {
                foreach ($terms as $term) {
                    $depth = 0;
                    $current_parent = $term->parent;
                    
                    while ($current_parent > 0) {
                        $depth++;
                        $parent_obj = get_term($current_parent, 'property-city');
                        if (is_wp_error($parent_obj) || empty($parent_obj)) break;
                        $current_parent = $parent_obj->parent;
                    }
                    
                    if ($depth > $max_depth) {
                        $max_depth = $depth;
                        $deepest_term_name = $term->name;
                    }
                }
            }
            $final_display_text = !empty($deepest_term_name) ? $deepest_term_name : $address;
        }
        
        if (!empty($final_display_text)) {
            $locations[$id] = html_entity_decode((string) $final_display_text, ENT_QUOTES, 'UTF-8');
        }
    }
    
    // Write the dictionary directly into a static JS file
    $js_content = 'window.rhAllPropertyLocations = ' . json_encode($locations) . ';';
    file_put_contents($file_path, $js_content);
    
    // Save a timestamp version to force browsers to update their cache only when data changes
    update_option('rh_loc_js_version_' . $lang, time());
}

// Generate the files automatically in the background when properties are added/updated/deleted
add_action('save_post_property', 'rh_regenerate_location_files_on_update');
add_action('deleted_post', 'rh_regenerate_location_files_on_update');
function rh_regenerate_location_files_on_update() {
    rh_build_location_dictionary_js_file('el');
    rh_build_location_dictionary_js_file('en');
}

// Enqueue the static file on the frontend instead of dumping raw HTML
add_action('wp_enqueue_scripts', 'rh_enqueue_location_dictionary_js', 99);
function rh_enqueue_location_dictionary_js() {
    if (!is_singular('property')) {
        $lang = function_exists('pll_current_language') ? pll_current_language() : 'el';
        if (empty($lang)) $lang = 'el';
        
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/rh_property_locations_' . $lang . '.js';
        $file_url = $upload_dir['baseurl'] . '/rh_property_locations_' . $lang . '.js';
        
        // Failsafe: Create the file instantly if it doesn't exist yet
        if (!file_exists($file_path)) {
            rh_build_location_dictionary_js_file($lang);
        }
        
        $version = get_option('rh_loc_js_version_' . $lang, '1.0');
        
        // Enqueue the file in the footer (true) to ensure it doesn't block rendering
        wp_enqueue_script('rh-all-locations-dict', $file_url, [], $version, true);
    }
}

// --- HIDE TAXONOMY PREFIX IN BANNER TITLES ---
add_action('wp_head', 'rh_hide_banner_tax_title');
function rh_hide_banner_tax_title() {
    echo '<style type="text/css">.rh_banner__title .tax-title { display: none !important; }</style>';
}


// --- INJECT BANNER ON SEARCH-BY-ID PAGES ---
add_action('wp_footer', 'rh_inject_search_by_id_banner', 99);
function rh_inject_search_by_id_banner() {
    $uri = urldecode($_SERVER['REQUEST_URI']);
    if (strpos($uri, '/με-κωδικο') !== false || strpos($uri, '/search-by-id') !== false) {
        $is_en = function_exists('pll_current_language') && pll_current_language() == 'en';
        ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                let newBanner = `
                    <section id="rh-banner-attachment-default" class="rh_banner rh_banner__image" style="background-image: url('https://www.thessaloniki-realestate.gr/wp-content/themes/realhomes/assets/modern/images/banner.jpg');">
                        <div class="rh_banner__cover"></div>
                    </section>
                `;
                $('.rh_banner.rh_banner__default_hide').replaceWith(newBanner);
            });
        </script>
        <?php
    }
}
