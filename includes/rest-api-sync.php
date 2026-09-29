<?php
// =========================================================================
// 1. REST API SETUP & MAIN IMPORTER
// =========================================================================

add_action('init', 'enable_acf_rest_api_for_properties', 99);
function enable_acf_rest_api_for_properties() {add_post_type_support('property', 'custom-fields'); }
add_action('rest_api_init', function () {
    register_rest_route('crm/v1', '/import', array(
        'methods'  => 'POST',
        'callback' => 'handle_crm_property_import',
        'permission_callback' => function (\WP_REST_Request $request) {
            return $request->get_param('key') === [SECRET_API_KEY]; 
        }
    ));

    register_rest_route('crm/v1', '/published', array(
        'methods'  => 'GET',
        'callback' => 'rh_get_published_properties',
        'permission_callback' => function (\WP_REST_Request $request) {
            return $request->get_param('key') === [SECRET_API_KEY]; 
        }
    ));
});

function handle_crm_property_import(\WP_REST_Request $request) {
    set_time_limit(600);
    $properties = $request->get_json_params();

    if (empty($properties) || !is_array($properties)) {
        return new \WP_REST_Response('No valid JSON data received.', 400);
    }

    $imported_count = 0; $updated_count = 0; $deleted_count = 0;

    foreach ($properties as $prop) {
        if (empty($prop['property_id'])) continue; 
        
        $crm_id = sanitize_text_field($prop['property_id']);
        $action = isset($prop['action']) ? sanitize_text_field($prop['action']) : 'update';

        $existing_posts = get_posts([ 'post_type' => 'property', 'posts_per_page' => -1, 'meta_key' => 'REAL_HOMES_property_id', 'meta_value' => $crm_id, 'post_status' => 'any' ]);

        if ($action === 'delete') {
            foreach ($existing_posts as $ep) {
                $gallery_string = get_post_meta($ep->ID, 'REAL_HOMES_gallery', true);
                if (!empty($gallery_string)) {
                    $gallery_ids = explode(',', $gallery_string);
                    foreach ($gallery_ids as $g_id) wp_delete_post($g_id, true); 
                }
                wp_delete_post($ep->ID, true);
                $deleted_count++;
            }
            continue; 
        }

        // Identify existing EL and EN posts
        $post_id_el = 0; $post_id_en = 0;
        foreach ($existing_posts as $ep) {
            if (!function_exists('pll_get_post_language')) { $post_id_el = $ep->ID; break; }
            $lang = pll_get_post_language($ep->ID);
            if ($lang === 'el') $post_id_el = $ep->ID;
            if ($lang === 'en') $post_id_en = $ep->ID;
        }

        $translations_map = [];
        $shared_polygon = ''; 

        // Dual Processing Loop
        $langs_to_process = ['el', 'en'];
        foreach ($langs_to_process as $lang) {
            if (empty($prop[$lang])) continue;

            $lang_data = $prop[$lang];
            $universal = isset($prop['universal']) ? $prop['universal'] : [];
            
            $post_id = ($lang === 'el') ? $post_id_el : $post_id_en;

            $post_data = [
                'post_title'   => sanitize_text_field($lang_data['title']), 
                'post_content' => wp_kses_post($lang_data['description']), 
                'post_status'  => 'publish', 
                'post_type'    => 'property' 
            ];

            if (!empty($lang_data['slug_base'])) $post_data['post_name'] = sanitize_title($lang_data['slug_base']);

            if ($post_id > 0) {
                $post_data['ID'] = $post_id;
                wp_update_post($post_data);
                $updated_count++;
            } else {
                $post_id = wp_insert_post($post_data);
                $imported_count++;
            }

            if (is_wp_error($post_id)) continue;
            
            if (function_exists('pll_set_post_language')) pll_set_post_language($post_id, $lang);
            $translations_map[$lang] = $post_id;

            // Universal Meta
            update_post_meta($post_id, 'REAL_HOMES_property_id', $crm_id);
            update_post_meta($post_id, 'REAL_HOMES_property_price', sanitize_text_field($universal['price']));
            update_post_meta($post_id, 'REAL_HOMES_property_size', sanitize_text_field($universal['area']));
            
            if (!empty($universal['latitude']) && !empty($universal['longitude'])) {
                update_post_meta($post_id, 'REAL_HOMES_property_location', floatval($universal['latitude']) . ',' . floatval($universal['longitude']));
            }
            if (!empty($universal['energy_class'])) update_post_meta($post_id, 'REAL_HOMES_energy_class', sanitize_text_field($universal['energy_class'])); else delete_post_meta($post_id, 'REAL_HOMES_energy_class');
            if (!empty($universal['bedrooms'])) update_post_meta($post_id, 'REAL_HOMES_property_bedrooms', sanitize_text_field($universal['bedrooms'])); else delete_post_meta($post_id, 'REAL_HOMES_property_bedrooms');
            if (!empty($universal['bathrooms'])) update_post_meta($post_id, 'REAL_HOMES_property_bathrooms', sanitize_text_field($universal['bathrooms'])); else delete_post_meta($post_id, 'REAL_HOMES_property_bathrooms');
            if (!empty($universal['wc'])) update_post_meta($post_id, 'inspiry_wc', sanitize_text_field($universal['wc'])); else delete_post_meta($post_id, 'inspiry_wc');
            if (!empty($universal['Rooms'])) update_post_meta($post_id, 'inspiry_rooms', sanitize_text_field($universal['Rooms'])); else delete_post_meta($post_id, 'inspiry_rooms');
            if (!empty($universal['Levels'])) update_post_meta($post_id, 'inspiry_levels', sanitize_text_field($universal['Levels'])); else delete_post_meta($post_id, 'inspiry_levels');
            if (!empty($universal['year_built'])) update_post_meta($post_id, 'REAL_HOMES_property_year_built', intval($universal['year_built'])); else delete_post_meta($post_id, 'REAL_HOMES_property_year_built');

            $price_clean = floatval(preg_replace('/[^0-9.]/', '', $universal['price']));
            $area_clean = floatval(preg_replace('/[^0-9.]/', '', $universal['area']));
            if ($area_clean > 0 && $price_clean > 0) update_post_meta($post_id, 'REAL_HOMES_price_per_sqm', round($price_clean / $area_clean)); else delete_post_meta($post_id, 'REAL_HOMES_price_per_sqm');

            if (!empty($universal['video'])) {
                update_post_meta($post_id, 'REAL_HOMES_tour_video_url', esc_url_raw($universal['video']));
                update_post_meta($post_id, 'REAL_HOMES_tour_video_title', 'Video Tour');
            } else {
                delete_post_meta($post_id, 'REAL_HOMES_tour_video_url');
                delete_post_meta($post_id, 'REAL_HOMES_tour_video_title');
                delete_post_meta($post_id, 'REAL_HOMES_tour_video_image');
                delete_post_meta($post_id, 'inspiry_video_group');
            }

            // --- CRM IDs ---
            $crm_ids = isset($prop['crm_ids']) ? $prop['crm_ids'] : [];
            foreach ($crm_ids as $key => $value) {
                if ($value !== '') {
                    update_post_meta($post_id, '_crm_' . strtolower($key), sanitize_text_field($value));
                } else {
                    delete_post_meta($post_id, '_crm_' . strtolower($key));
                }
            }

            // BILINGUAL SEARCH ENGINE COMPATIBILITY PATCH
            $el_details = isset($prop['el']['additional_details']) ? $prop['el']['additional_details'] : [];
            if (!empty($el_details['Τελευταίος Όροφος'])) update_post_meta($post_id, 'inspiry_Τελευταίος_όροφος', sanitize_text_field($el_details['Τελευταίος Όροφος'])); else delete_post_meta($post_id, 'inspiry_Τελευταίος_όροφος');
            if (!empty($el_details['Συσκευές'])) update_post_meta($post_id, 'inspiry_Ελάχ._εξοπλισμός', sanitize_text_field($el_details['Συσκευές'])); else delete_post_meta($post_id, 'inspiry_Ελάχ._εξοπλισμός');
            if (!empty($el_details['Επίπλωση'])) update_post_meta($post_id, 'inspiry_Ελάχ._επίπλωση', sanitize_text_field($el_details['Επίπλωση'])); else delete_post_meta($post_id, 'inspiry_Ελάχ._επίπλωση');
            if (!empty($el_details['Ύδρευση/Άρδευση'])) update_post_meta($post_id, 'inspiry_Ύδρευση/Άρδευση', sanitize_text_field($el_details['Ύδρευση/Άρδευση'])); else delete_post_meta($post_id, 'inspiry_Ύδρευση/Άρδευση');
            if (!empty($el_details['Μπαλκόνι'])) update_post_meta($post_id, 'inspiry_Μπαλκόνι', sanitize_text_field($el_details['Μπαλκόνι'])); else delete_post_meta($post_id, 'inspiry_Μπαλκόνι');
            if (!empty($el_details['Δραστηριότητες'])) update_post_meta($post_id, 'inspiry_Όχληση', sanitize_text_field($el_details['Δραστηριότητες'])); else delete_post_meta($post_id, 'inspiry_Όχληση');
            if (!empty($el_details['Ανελκυστήρες'])) update_post_meta($post_id, 'REAL_HOMES_elevators', sanitize_text_field($el_details['Ανελκυστήρες'])); else delete_post_meta($post_id, 'REAL_HOMES_elevators');

            // Language Specific Meta
            if (!empty($lang_data['address'])) update_post_meta($post_id, 'REAL_HOMES_property_address', sanitize_text_field($lang_data['address']));
            if (!empty($lang_data['wc_type'])) update_post_meta($post_id, 'inspiry_wc_type', sanitize_text_field($lang_data['wc_type'])); else delete_post_meta($post_id, 'inspiry_wc_type');
            
            if (!empty($lang_data['floor'])) {
                $floor_str = mb_strtolower(sanitize_text_field($lang_data['floor']), 'UTF-8'); $floor_num = 0;
                if (strpos($floor_str, 'ημιώροφος') !== false || strpos($floor_str, 'ημιοροφος') !== false || strpos($floor_str, 'mezzanine') !== false) $floor_num = 0.5;
                elseif (strpos($floor_str, 'ημιυπόγειο') !== false || strpos($floor_str, 'semi-basement') !== false) $floor_num = -0.5;
                elseif (strpos($floor_str, 'ισόγειο') !== false || strpos($floor_str, 'ground') !== false) $floor_num = 0;
                elseif (strpos($floor_str, 'υπόγειο') !== false || strpos($floor_str, 'basement') !== false) $floor_num = -1;
                else $floor_num = intval(preg_replace('/[^0-9-]/', '', $floor_str));
                update_post_meta($post_id, 'REAL_HOMES_floor_number', (float)$floor_num);
                update_post_meta($post_id, 'inspiry_floor', sanitize_text_field($lang_data['floor']));
            } else { 
                delete_post_meta($post_id, 'REAL_HOMES_floor_number'); delete_post_meta($post_id, 'inspiry_floor'); 
            }

            if (isset($lang_data['additional_details']) && is_array($lang_data['additional_details'])) {
                $formatted_additional_details = [];
                foreach ($lang_data['additional_details'] as $detail_title => $detail_value) {
                    if (!empty($detail_value)) {
                        if (is_array($detail_value)) {
                            $target_key = ($lang === 'en') ? 'EN' : 'GR';
                            $detail_value = isset($detail_value[$target_key]) ? $detail_value[$target_key] : reset($detail_value);
                        }
                        $formatted_additional_details[sanitize_text_field($detail_title)] = sanitize_textarea_field($detail_value);
                    }
                }
                if (!empty($formatted_additional_details)) update_post_meta($post_id, 'REAL_HOMES_additional_details', $formatted_additional_details);
                else delete_post_meta($post_id, 'REAL_HOMES_additional_details');
            
                if (isset($formatted_additional_details['Common_note'])) {
                    update_post_meta($post_id, 'REAL_HOMES_property_note', $formatted_additional_details['Common_note']);
                    update_post_meta($post_id, 'common_note', $formatted_additional_details['Common_note']);
                } else {
                    delete_post_meta($post_id, 'REAL_HOMES_property_note');
                    delete_post_meta($post_id, 'common_note');
                }
            }

            // --- Taxonomy Assignments ---
            if (!empty($lang_data['type'])) {
                $type_ids = []; $current_parent = 0; 
                foreach ($lang_data['type'] as $type_name) {
                    if (empty(trim($type_name))) continue;
                    $id = rh_get_term_id_bypassing_polylang($type_name, 'property-type', $current_parent, $lang);
                    if ($id) { $type_ids[] = (int)$id; $current_parent = $id; }
                }
                if (!empty($type_ids)) rh_force_assign_terms($post_id, $type_ids, 'property-type');
            }

            if (!empty($lang_data['status'])) {
                $status_ids = [];
                foreach ($lang_data['status'] as $status_name) {
                    $id = rh_get_term_id_bypassing_polylang($status_name, 'property-status', null, $lang);
                    if ($id) $status_ids[] = (int)$id;
                }
                if (!empty($status_ids)) rh_force_assign_terms($post_id, $status_ids, 'property-status');
            }

            if (!empty($lang_data['features'])) {
                $feature_ids = [];
                foreach ($lang_data['features'] as $feature_name) {
                    $id = rh_get_term_id_bypassing_polylang($feature_name, 'property-feature', null, $lang);
                    if ($id) $feature_ids[] = (int)$id;
                }
                if (!empty($feature_ids)) rh_force_assign_terms($post_id, $feature_ids, 'property-feature');
            }

            // --- Location Geofencing Assignment ---
            $explicit_location_ids = []; $geofenced_location_ids = []; $primary_term_id = null;
            if (!empty($lang_data['location']) && is_array($lang_data['location'])) {
                $current_parent = 0; 
                foreach ($lang_data['location'] as $location_name) {
                    if (empty(trim($location_name))) continue;
                    $id = rh_get_term_id_bypassing_polylang($location_name, 'property-city', $current_parent, $lang);
                    if ($id) {
                        $explicit_location_ids[] = (int)$id;
                        $current_parent = $id; $primary_term_id = $id; 
                    }
                }
            }

            if (!empty($universal['actual_lat']) && !empty($universal['actual_lng'])) {
                $actual_lat = floatval($universal['actual_lat']); $actual_lng = floatval($universal['actual_lng']);
                $boundary_terms = get_terms(['taxonomy' => 'property-city', 'hide_empty' => false, 'lang' => $lang, 'meta_query' => [['key' => 'location_bounds', 'compare' => 'EXISTS']]]);

                if (!is_wp_error($boundary_terms) && !empty($boundary_terms)) {
                    foreach ($boundary_terms as $b_term) {
                        if (in_array($b_term->term_id, $explicit_location_ids)) continue;

                        $bounds_string = get_term_meta($b_term->term_id, 'location_bounds', true);
                        if (!empty($bounds_string)) {
                            $b_arr = explode(',', $bounds_string);
                            if (count($b_arr) == 4) {
                                if ($actual_lat >= floatval($b_arr[0]) && $actual_lat <= floatval($b_arr[2]) && $actual_lng >= floatval($b_arr[1]) && $actual_lng <= floatval($b_arr[3])) {
                                    $is_inside = true; 
                                    $polygon_string = get_term_meta($b_term->term_id, 'location_polygon', true);
                                    if (!empty($polygon_string)) {
                                        $poly_data = json_decode($polygon_string, true);
                                        if (is_array($poly_data)) {
                                            $is_inside = false;
                                            foreach ($poly_data as $poly) {
                                                if (isset($poly[0]) && is_array($poly[0]) && !is_array($poly[0][0])) {
                                                    if (rh_point_in_polygon([$actual_lat, $actual_lng], $poly)) { $is_inside = true; break; }
                                                } elseif (isset($poly[0][0]) && is_array($poly[0][0])) {
                                                    foreach ($poly as $sub_poly) {
                                                        if (rh_point_in_polygon([$actual_lat, $actual_lng], $sub_poly)) { $is_inside = true; break 2; }
                                                    }
                                                }
                                            }
                                        }
                                    }

                                    if ($is_inside) {
                                        $geofenced_location_ids[] = (int) $b_term->term_id;
                                        $parent_id = $b_term->parent;
                                        while ($parent_id > 0) {
                                            if (!in_array($parent_id, $explicit_location_ids)) $geofenced_location_ids[] = (int) $parent_id;
                                            $parent_term = get_term($parent_id, 'property-city');
                                            $parent_id = (!is_wp_error($parent_term) && $parent_term) ? $parent_term->parent : 0;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            $all_location_ids = array_merge($explicit_location_ids, $geofenced_location_ids);
            if (!empty($all_location_ids)) {
                rh_force_assign_terms($post_id, $all_location_ids, 'property-city');
                if ($primary_term_id) update_post_meta($post_id, '_yoast_wpseo_primary_property-city', $primary_term_id);
                
                $display_mode = isset($universal['display_mode']) ? sanitize_text_field($universal['display_mode']) : 'address';
                update_post_meta($post_id, 'rh_display_mode', $display_mode);

                if ($lang === 'el') {
                    $polygon_string = $primary_term_id ? get_term_meta($primary_term_id, 'location_polygon', true) : '';
                    $shared_polygon = $polygon_string;
                } else {
                    $polygon_string = $shared_polygon;
                }
                
                if (($display_mode === 'place' || $display_mode === 'dimos') && !empty($polygon_string)) {
                    update_post_meta($post_id, 'rh_property_polygon', $polygon_string);
                } else {
                    delete_post_meta($post_id, 'rh_property_polygon');
                }
            } else {
                 delete_post_meta($post_id, 'rh_property_polygon');
            }

            // --- Sync Photos & Media --- 
            if (!function_exists('media_sideload_image')) {
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }

            if (isset($universal['photos']) && is_array($universal['photos'])) {
                $old_gallery_string = get_post_meta($post_id, 'REAL_HOMES_gallery', true);
                $old_gallery_ids = !empty($old_gallery_string) ? explode(',', $old_gallery_string) : [];
                delete_post_meta($post_id, 'REAL_HOMES_property_images'); 
                delete_post_meta($post_id, 'REAL_HOMES_gallery');
                
                $is_first_image = true; $gallery_ids = [];
                foreach ($universal['photos'] as $image_url) {
                    global $wpdb;
                    $attachment_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_external_aws_url' AND meta_value = %s LIMIT 1", $image_url));

                    if (!$attachment_id) {
                        $attachment = [ 
                            'post_type'      => 'attachment',
                            'post_mime_type' => 'image/jpeg', 
                            'post_title'     => sanitize_title($lang_data['title'] . '-' . uniqid()), 
                            'post_content'   => '', 
                            'post_status'    => 'inherit', 
                            'post_parent'    => $post_id, 
                            'guid'           => $image_url 
                        ];
                        $attachment_id = wp_insert_post($attachment);
                        if (!is_wp_error($attachment_id)) {
                            update_post_meta($attachment_id, '_external_aws_url', $image_url); 
                            update_post_meta($attachment_id, '_wp_attached_file', $image_url);
                        }
                    } else {
                        $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts} SET post_type = 'attachment' WHERE ID = %d AND post_type != 'attachment'", $attachment_id));
                    }

                    if (!is_wp_error($attachment_id) && $attachment_id) {
                        $gallery_ids[] = $attachment_id;
                        if ($is_first_image) { set_post_thumbnail($post_id, $attachment_id); $is_first_image = false; }
                        add_post_meta($post_id, 'REAL_HOMES_property_images', $attachment_id, false);
                    }
                }
                
                $orphaned_ids = array_diff($old_gallery_ids, $gallery_ids);
                foreach ($orphaned_ids as $orphan_id) {
                    if (!empty($orphan_id)) { wp_delete_post($orphan_id, true); }
                }
                
                if (!empty($gallery_ids)) {
                    update_post_meta($post_id, 'REAL_HOMES_gallery', implode(',', $gallery_ids));
                    if (!empty($universal['video'])) {
                        update_post_meta($post_id, 'REAL_HOMES_tour_video_image', $gallery_ids[0]);
                        
                        $video_group = [ 
                            [ 
                                'inspiry_video_group_title' => 'Video Tour', 
                                'inspiry_video_group_url'   => esc_url_raw($universal['video']), 
                                'inspiry_video_group_image' => [ $gallery_ids[0] ] 
                            ] 
                        ];
                        
                        update_post_meta($post_id, 'inspiry_video_group', $video_group);
                    }
                } else {
                    delete_post_thumbnail($post_id);
                }
            }
        } // End Language Loop

        if (count($translations_map) == 2 && function_exists('pll_save_post_translations')) {
            pll_save_post_translations($translations_map);
        }
    }

    return new \WP_REST_Response([ 'message' => 'Dual Sync completed successfully.', 'created' => $imported_count, 'updated' => $updated_count, 'deleted' => $deleted_count ], 200);
}

// =========================================================================
// 2. TAXONOMY & GEOLOCATION HELPERS
// =========================================================================

function rh_point_in_polygon($point, $polygon) {
    $c = 0;
    $p1 = $polygon[0];
    $n = count($polygon);
    for ($i = 1; $i <= $n; $i++) {
        $p2 = $polygon[$i % $n];
        if ($point[1] > min($p1[1], $p2[1]) && $point[1] <= max($p1[1], $p2[1]) && $point[0] <= max($p1[0], $p2[0]) && $p1[1] != $p2[1]) {
            $xinters = ($point[1] - $p1[1]) * ($p2[0] - $p1[0]) / ($p2[1] - $p1[1]) + $p1[0];
            if ($p1[0] == $p2[0] || $point[0] <= $xinters) { $c++; }
        }
        $p1 = $p2;
    }
    return ($c % 2 != 0);
}

function rh_force_polylang_term($term_id, $taxonomy, $lang) {
    if (function_exists('pll_set_term_language')) pll_set_term_language($term_id, $lang);
    global $wpdb;
    $lang_tt_id = $wpdb->get_var($wpdb->prepare("SELECT tt.term_taxonomy_id FROM {$wpdb->term_taxonomy} tt INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id WHERE tt.taxonomy = 'term_language' AND t.slug = %s LIMIT 1", $lang));
    $term_tt_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d LIMIT 1", $term_id));
    if ($lang_tt_id && $term_tt_id) {
        $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES (%d, %d)", $term_id, $lang_tt_id));
        clean_term_cache($term_id, $taxonomy);
    }
}

function rh_get_term_id_bypassing_polylang($term_name, $taxonomy, $parent_id, $lang) {
    global $wpdb; 
    $term_name = trim($term_name); $term_name_encoded = str_replace('&', '&amp;', $term_name);
    
    $parent_sql = "";
    if ($parent_id !== null) {
        $parent_sql = $wpdb->prepare(" AND tt.parent = %d", $parent_id);
    }
    
    $query = "SELECT t.term_id FROM {$wpdb->terms} t 
              INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id 
              INNER JOIN {$wpdb->term_relationships} tr ON tt.term_taxonomy_id = tr.object_id 
              INNER JOIN {$wpdb->term_taxonomy} tt_lang ON tr.term_taxonomy_id = tt_lang.term_taxonomy_id 
              INNER JOIN {$wpdb->terms} t_lang ON tt_lang.term_id = t_lang.term_id 
              WHERE (t.name = %s OR t.name = %s) AND tt.taxonomy = %s 
              AND tt_lang.taxonomy = 'term_language' AND t_lang.slug = %s {$parent_sql} LIMIT 1";
              
    $term_id = $wpdb->get_var($wpdb->prepare($query, $term_name, $term_name_encoded, $taxonomy, $lang));
    if ($term_id) return (int) $term_id;
    
    $fallback_query = "SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE (t.name = %s OR t.name = %s) AND tt.taxonomy = %s {$parent_sql} LIMIT 1";
    $term_id_fallback = $wpdb->get_var($wpdb->prepare($fallback_query, $term_name, $term_name_encoded, $taxonomy));
    
    if ($term_id_fallback) {
        rh_force_polylang_term((int)$term_id_fallback, $taxonomy, $lang);
        return (int) $term_id_fallback;
    }
    
    $term_args = []; if ($parent_id !== null) $term_args['parent'] = $parent_id;
    $new_term = wp_insert_term($term_name_encoded, $taxonomy, $term_args);
    
    if (is_wp_error($new_term)) {
        if ($new_term->get_error_code() === 'term_exists') {
            $existing_id = $new_term->get_error_data('term_exists');
            rh_force_polylang_term((int)$existing_id, $taxonomy, $lang);
            return (int) $existing_id;
        }
        return false;
    } else {
        rh_force_polylang_term((int)$new_term['term_id'], $taxonomy, $lang);
        return (int) $new_term['term_id'];
    }
}

function rh_force_assign_terms($post_id, $term_ids, $taxonomy) {
    if (empty($term_ids)) return;
    $term_ids = array_unique(array_map('intval', $term_ids));
    wp_set_object_terms($post_id, $term_ids, $taxonomy, false);
    global $wpdb;
    foreach ($term_ids as $tid) {
        $tt_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = %s", $tid, $taxonomy));
        if ($tt_id) $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES (%d, %d)", $post_id, $tt_id));
    }
    wp_update_term_count($term_ids, $taxonomy);
}

// =========================================================================
// 3. ACF & TERM OVERRIDES
// =========================================================================

add_action( 'rest_after_insert_property', 'force_save_acf_data_from_rest', 10, 3 );
function force_save_acf_data_from_rest( $post, $request, $creating ) {
    $acf_data = $request->get_param( 'acf' );
    if ( ! empty( $acf_data ) && is_array( $acf_data ) ) {
        foreach ( $acf_data as $key => $value ) { update_field( $key, $value, $post->ID ); }
    }
}

add_action('acf/save_post', 'auto_tag_property_from_alias', 20);
function auto_tag_property_from_alias( $post_id ) {
    if ( get_post_type($post_id) !== 'property' ) return;
    $input = get_field('location_alias_input', $post_id);
    if ( empty($input) ) return;
    $matched_term = false;
    $term = get_term_by('name', $input, 'property-city');
    if ( $term ) $matched_term = $term;
    if ( ! $matched_term ) {
        $args = [ 'taxonomy' => 'property-city', 'hide_empty' => false, 'meta_query' => [ [ 'key' => 'search_synonyms', 'value' => $input, 'compare' => 'LIKE' ] ] ];
        $terms = get_terms($args);
        if ( ! empty($terms) && ! is_wp_error($terms) ) $matched_term = $terms[0]; 
    }
    if ( $matched_term ) {
        wp_set_object_terms( $post_id, $matched_term->term_id, 'property-city', false );
        update_field('location_alias_input', '', $post_id);
    }
}

add_filter( 'get_the_terms', 'rh_prioritize_explicit_location_term', 10, 3 );
function rh_prioritize_explicit_location_term( $terms, $post_id, $taxonomy ) {
    if ( $taxonomy !== 'property-city' || empty( $terms ) || is_wp_error( $terms ) ) return $terms;
    $primary_id = get_post_meta( $post_id, '_yoast_wpseo_primary_property-city', true );
    if ( empty($primary_id) ) return $terms;
    $primary_term_index = false;
    foreach ( $terms as $index => $term ) {
        if ( $term->term_id == $primary_id ) {
            $primary_term_index = $index;
            break;
        }
    }
    if ( $primary_term_index !== false && $primary_term_index > 0 ) {
        $primary_term = $terms[ $primary_term_index ];
        unset( $terms[ $primary_term_index ] );
        array_unshift( $terms, $primary_term ); 
    }
    return $terms;
}

// =========================================================================
// 4. AWS GHOST ATTACHMENTS (CORE WORDPRESS TRICKS)
// =========================================================================

add_filter('wp_get_attachment_url', 'serve_ghost_attachment_url', 99, 2);
function serve_ghost_attachment_url($url, $attachment_id) {
    $aws_url = get_post_meta($attachment_id, '_external_aws_url', true); 
return !empty($aws_url) ? $aws_url : $url;
}

add_filter('wp_get_attachment_image_src', 'serve_ghost_attachment_image_src', 99, 4);
function serve_ghost_attachment_image_src($image, $attachment_id, $size, $icon) {
    $aws_url = get_post_meta($attachment_id, '_external_aws_url', true);
    if (!empty($aws_url)) return [$aws_url, 800, 600, false]; 
    return $image;
}

add_filter('image_downsize', 'serve_ghost_image_downsize', 99, 3);
function serve_ghost_image_downsize($downsize, $attachment_id, $size) {
    $aws_url = get_post_meta($attachment_id, '_external_aws_url', true);
    if (!empty($aws_url)) return [$aws_url, 800, 600, false];
    return $downsize;
}

add_filter('wp_get_attachment_metadata', 'serve_ghost_attachment_metadata', 99, 2);
function serve_ghost_attachment_metadata($data, $attachment_id) {
    $aws_url = get_post_meta($attachment_id, '_external_aws_url', true);
    if (!empty($aws_url)) {
        return ['width' => 800, 'height' => 600, 'file' => $aws_url, 'sizes' => []];
    }
    return $data;
}

add_filter('get_attached_file', 'serve_ghost_attached_file', 99, 2);
function serve_ghost_attached_file($file, $attachment_id) {
    $aws_url = get_post_meta($attachment_id, '_external_aws_url', true);
    if (!empty($aws_url)) return $aws_url;
    return $file;
}

add_filter('wp_calculate_image_srcset', 'disable_ghost_srcset', 99, 5);
function disable_ghost_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
$aws_url = get_post_meta($attachment_id, '_external_aws_url', true); return !empty($aws_url) ? false : $sources;
}

add_filter('rwmb_image_info', 'rh_fix_metabox_ghost_urls', 99, 2);
add_filter('rwmb_file_info', 'rh_fix_metabox_ghost_urls', 99, 2);
function rh_fix_metabox_ghost_urls($info, $attachment_id) {
    if (!is_array($info)) $info = [];
    $aws_url = get_post_meta($attachment_id, '_external_aws_url', true);
    if (!empty($aws_url)) {
        $info['url'] = $aws_url;
        $info['full_url'] = $aws_url;
        $info['path'] = $aws_url;
        if (empty($info['alt'])) {
            $parent_id = wp_get_post_parent_id($attachment_id);
            $info['alt'] = $parent_id ? get_the_title($parent_id) : 'Property Image';
        }
    }
    return $info;
}

// =========================================================================
// 5. FRONTEND UI FIXES (POLYGON JS & SLIDER BUFFER)
// =========================================================================

add_action('wp_footer', 'rh_inject_property_polygon_js', 99);
function rh_inject_property_polygon_js() {
    if (is_singular('property')) {
        $post_id = get_the_ID();
        $polygon = get_post_meta($post_id, 'rh_property_polygon', true);
        if (!empty($polygon)) {
            echo "<script>window.rhActivePropertyPolygon = {$polygon};</script>";
        }
    }
}

add_action('template_redirect', 'rh_force_single_image_html_fix', 999);
function rh_force_single_image_html_fix() {
    if ( is_singular('property') ) {
        ob_start('rh_replace_broken_single_image');
    }
}

function rh_replace_broken_single_image( $html ) {
    if ( strpos($html, '<img src=""') !== false ) {
        global $post;
        if ( $post ) {
            $thumb_id = get_post_thumbnail_id($post->ID);
            if ( ! $thumb_id ) {
                $gallery = get_post_meta($post->ID, 'REAL_HOMES_property_images', false);
                if (!empty($gallery)) {
                    $thumb_id = $gallery[0];
                }
            }
            if ( $thumb_id ) {
                $aws_url = get_post_meta($thumb_id, '_external_aws_url', true);
                if ( $aws_url ) {
                    $html = preg_replace('/<img src=""([^>]*)>/', '<img src="' . $aws_url . '"$1>', $html);
                }
            }
        }
    }
    $html = preg_replace('/<a href=""([^>]*data-fancybox="gallery"[^>]*)>\s*<img([^>]*)src="([^"]+)"/', '<a href="$3"$1><img$2src="$3"', $html);

    return $html;
}

// =========================================================================
// 7. CRM LOCATION TARGETED SYNC WEBHOOK WITH BACKGROUND RECALCULATION
// =========================================================================
add_action('rest_api_init', function () {
    register_rest_route('crm/v1', '/sync-locations', array(
        'methods'  => ['GET', 'POST'],
        'callback' => 'rh_master_location_sync',
        'permission_callback' => function (\WP_REST_Request $request) {
            return $request->get_param('key') === [SECRET_API_KEY]; 
        }
    ));
});

function rh_master_location_sync(\WP_REST_Request $request) {
    // Keep script running in the background after the 500ms CRM timeout disconnects
    ignore_user_abort(true);
    set_time_limit(0); 

    $action = $request->get_param('action');
    
    // ----------------------------------------------------
    // TARGETED DELETE
    // ----------------------------------------------------
    if ($action === 'delete') {
        $slug_el = sanitize_title($request->get_param('slug_el'));
        $slug_en = sanitize_title($request->get_param('slug_en'));
        
        foreach ([$slug_el, $slug_en] as $slug) {
            if (!empty($slug)) {
                $term = get_term_by('slug', $slug, 'property-city');
                if ($term && !is_wp_error($term)) {
                    wp_delete_term($term->term_id, 'property-city');
                }
            }
        }
        delete_transient('rh_all_loc_polys_v4');
        return new \WP_REST_Response(['message' => 'Targeted deletion complete.'], 200);
    }

    // ----------------------------------------------------
    // TARGETED UPDATE / INSERT VIA PAYLOAD PUSH
    // ----------------------------------------------------
    if ($action === 'update') {
        $kind = $request->get_param('kind');
        $name_el = sanitize_text_field($request->get_param('name_el'));
        $name_en = sanitize_text_field($request->get_param('name_en'));
        $bounds = sanitize_text_field($request->get_param('bounds'));
        $coords = $request->get_param('coords'); 
        $parent_en = sanitize_text_field($request->get_param('parent_en'));

        if (!$name_el || !$name_en || $kind === 'Enotita') {
            return new \WP_REST_Response(['message' => 'Skipped or invalid data.'], 200);
        }

        if (!function_exists('rh_fast_sync_location_term')) {
            function rh_fast_sync_location_term($name, $slug, $parent_id, $lang, $bounds, $polygon = '') {
                global $wpdb;
                $clean_slug = sanitize_title($slug);
                
                $term_id = $wpdb->get_var($wpdb->prepare("SELECT t.term_id FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE t.slug = %s AND tt.taxonomy = 'property-city' LIMIT 1", $clean_slug));
                
                if (!$term_id) {
                    $term_data = wp_insert_term($name, 'property-city', ['parent' => $parent_id, 'slug' => $clean_slug]);
                    if (!is_wp_error($term_data)) $term_id = $term_data['term_id'];
                    if ($term_id && function_exists('pll_set_term_language')) pll_set_term_language($term_id, $lang);
                }
                
                if ($term_id) {
                    if (!empty($bounds)) {
                        $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->termmeta} (term_id, meta_key, meta_value) VALUES (%d, 'location_bounds', %s) ON DUPLICATE KEY UPDATE meta_value = %s", $term_id, $bounds, $bounds));
                    }
                    if (!empty($polygon)) {
                        $wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->termmeta} (term_id, meta_key, meta_value) VALUES (%d, 'location_polygon', %s) ON DUPLICATE KEY UPDATE meta_value = %s", $term_id, wp_slash($polygon), wp_slash($polygon)));
                    }
                }
                return $term_id;
            }
        }

        $p_id_el = 0; $p_id_en = 0;
        if (!empty($parent_en)) {
            $parent_term_el = get_term_by('slug', sanitize_title($parent_en . '-el'), 'property-city');
            $parent_term_en = get_term_by('slug', sanitize_title($parent_en . '-en'), 'property-city');
            if ($parent_term_el) $p_id_el = $parent_term_el->term_id;
            if ($parent_term_en) $p_id_en = $parent_term_en->term_id;
        }

        rh_fast_sync_location_term($name_el, $name_en . '-el', $p_id_el, 'el', $bounds, $coords);
        rh_fast_sync_location_term($name_en, $name_en . '-en', $p_id_en, 'en', $bounds, $coords);

        delete_transient('rh_all_loc_polys_v4');

        rh_run_background_geofencing();

        return new \WP_REST_Response(['message' => 'Targeted update and geofencing complete via Payload Push.'], 200);
    }
}

// ----------------------------------------------------
// GEOFENCING RECALCULATION HELPER
// ----------------------------------------------------
function rh_run_background_geofencing() {
    wp_suspend_cache_invalidation(true);
    wp_defer_term_counting(true);
    global $wpdb;

    // 1. Build a RAM Cache of all property-city parents to avoid DB queries in the loop
    $term_parents = [];
    $tt_results = $wpdb->get_results("SELECT term_id, parent FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'property-city'");
    foreach ($tt_results as $tt) {
        $term_parents[$tt->term_id] = $tt->parent;
    }

    // 2. Fetch all location polygons
    $polygon_terms = [];
    $results = $wpdb->get_results("
        SELECT t.term_id, tm_bounds.meta_value as bounds, tm_poly.meta_value as polygon, tt.parent
        FROM {$wpdb->terms} t
        INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
        INNER JOIN {$wpdb->termmeta} tm_poly ON t.term_id = tm_poly.term_id AND tm_poly.meta_key = 'location_polygon'
        LEFT JOIN {$wpdb->termmeta} tm_bounds ON t.term_id = tm_bounds.term_id AND tm_bounds.meta_key = 'location_bounds'
        WHERE tt.taxonomy = 'property-city' AND tm_poly.meta_value != ''
    ");

    foreach ($results as $r) {
        $polygon_terms[] = [
            'term_id' => (int)$r->term_id,
            'parent'  => (int)$r->parent,
            'bounds'  => explode(',', $r->bounds),
            'polygon' => json_decode($r->polygon, true)
        ];
    }

    // 3. Fetch all properties that have coordinates
    $properties = $wpdb->get_results("
        SELECT p.ID, pm.meta_value as coords
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'REAL_HOMES_property_location'
        WHERE p.post_type = 'property' AND pm.meta_value != ''
    ");

    foreach ($properties as $prop) {
        $coords = explode(',', $prop->coords);
        if (count($coords) !== 2) continue;
        $lat = floatval($coords[0]);
        $lng = floatval($coords[1]);

        $matched_term_ids = [];

        foreach ($polygon_terms as $pt) {
            $b_arr = $pt['bounds'];
            if (count($b_arr) == 4) {
                // Check if the pin is inside the raw bounds
                if ($lat >= floatval($b_arr[0]) && $lat <= floatval($b_arr[2]) && $lng >= floatval($b_arr[1]) && $lng <= floatval($b_arr[3])) {
                    $is_inside = false;
                    $poly_data = $pt['polygon'];
                    
                    // Verify exact geometric inclusion
                    if (is_array($poly_data)) {
                        foreach ($poly_data as $poly) {
                            if (isset($poly[0]) && is_array($poly[0]) && !is_array($poly[0][0])) {
                                if (rh_point_in_polygon([$lat, $lng], $poly)) { $is_inside = true; break; }
                            } elseif (isset($poly[0][0]) && is_array($poly[0][0])) {
                                foreach ($poly as $sub_poly) {
                                    if (rh_point_in_polygon([$lat, $lng], $sub_poly)) { $is_inside = true; break 2; }
                                }
                            }
                        }
                    }
                    
                    // If matched, gather this term and all its ancestors instantly via RAM
                    if ($is_inside) {
                        $matched_term_ids[] = $pt['term_id'];
                        $parent_id = $pt['parent'];
                        while ($parent_id > 0) {
                            $matched_term_ids[] = $parent_id;
                            $parent_id = isset($term_parents[$parent_id]) ? $term_parents[$parent_id] : 0;
                        }
                    }
                }
            }
        }

        $matched_term_ids = array_unique($matched_term_ids);

        // Fetch current term assignments
        $current_terms = wp_get_object_terms($prop->ID, 'property-city', ['fields' => 'ids']);
        if (is_wp_error($current_terms)) $current_terms = [];

        sort($matched_term_ids);
        sort($current_terms);
        
        // If the location data is out of sync, replace it via Raw SQL
        if ($matched_term_ids !== $current_terms && !empty($matched_term_ids)) {
            $tt_ids_to_add = [];
            foreach ($matched_term_ids as $tid) {
                $tt_id = $wpdb->get_var($wpdb->prepare("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id = %d AND taxonomy = 'property-city'", $tid));
                if ($tt_id) $tt_ids_to_add[] = $tt_id;
            }
            
            $wpdb->query($wpdb->prepare("
                DELETE tr FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tr.object_id = %d AND tt.taxonomy = 'property-city'
            ", $prop->ID));
            
            foreach ($tt_ids_to_add as $tt_id) {
                $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id) VALUES (%d, %d)", $prop->ID, $tt_id));
            }
        }
    }

    wp_suspend_cache_invalidation(false);
    wp_defer_term_counting(false);
    
    // Repair the counts across all location tags at the very end
    $all_city_terms = $wpdb->get_col("SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'property-city'");
    if (!empty($all_city_terms)) {
        wp_update_term_count($all_city_terms, 'property-city');
    }
}


// =========================================================================
// 8. CRM SYNC VERIFICATION ENDPOINT
// =========================================================================
function rh_get_published_properties(\WP_REST_Request $request) {
    global $wpdb;
    
    // Query directly from the DB for maximum performance, grouping by CRM ID 
    // so we don't double-count translated English/Greek variants.
    $query = "SELECT pm.meta_value as crm_id, p.ID as wp_id 
              FROM {$wpdb->posts} p 
              INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id 
              WHERE p.post_type = 'property' 
              AND p.post_status = 'publish' 
              AND pm.meta_key = 'REAL_HOMES_property_id' 
              GROUP BY pm.meta_value";
              
    $results = $wpdb->get_results($query, ARRAY_A);
    
    $id_array = [];
    if (!empty($results)) {
        foreach ($results as $row) {
            if (!empty($row['crm_id'])) {
                $id_array[] = [
                    'RefId' => $row['crm_id'],
                    'SiteID' => $row['wp_id']
                ];
            }
        }
    }
    
    return new \WP_REST_Response([
        'total' => count($id_array),
        'listings' => $id_array
    ], 200);
}