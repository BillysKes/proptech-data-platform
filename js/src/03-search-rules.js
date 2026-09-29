/* --- Module: 03-search-rules.js (Optimized) --- */
jQuery(document).ready(function($) {
    const pageUrl = decodeURIComponent(window.location.href).toLowerCase();
    const isEn = pageUrl.indexOf('/en/') !== -1;
    const isAssignmentPage = pageUrl.indexOf('ανάθεση') !== -1 || pageUrl.indexOf('αναθεση') !== -1 || pageUrl.indexOf('submit') !== -1;
    
    const anyText = isAssignmentPage ? '' : (isEn ? 'Any' : 'Αδιάφορο');

    // ==========================================================================
    // 1. CONFIGURATION & MAPPING DICTIONARIES
    // =========================================================================
    const landTypes = ['gi', 'agrotemaxio', 'oikopedo', 'ktima', 'land', 'land-land', 'plot', 'domaine']; 
    const commercialTypes = ['epaggelmatikos-xwros', 'epaggelmatika-akinita' , 'apothiki', 'viomixanikos-xwros', 'viotexnikos-xwros', 'grafeio', 'ekthesiakos-xwros', 'katalima', 'katastima', 'ktirio-epaggelmatikos-xwros', 'ksenodoxeio', 'stathmeusis-xwros', 'commercial', 'commercial-property', 'office', 'shop', 'warehouse', 'industrial-property', 'small-industrial-property', 'hotel', 'garage']; 

    const featureRules = {
        'any': {
            'base': [],
            'rent': [], 'sale': []
        },
        'katoikia': {
            'base': ['area','garden', 'pool', 'parking', 'front', 'view', 'pets', 'bedrooms', 'bathrooms', 'balcony', 'floor','last_floor', 'lift', 'year_built', 'heating','window_frames','energy_class','for_pensioners','for_short_term_rentals','beach_dist'],
            'rent': ['furniture', 'equipment','for_students'],
            'sale': ['split', 'golden_visa','for_investment', 'for_students', 'price_per_sqm', 'yield']
        },
        'epaggelmatikos-xwros': {
            'base': ['area','health', 'chimney', 'commercial_road', 'corner', 'lift', 'floor', 'wc', 'wc_type', 'heating', 'year_built'],
            'rent': [],
            'sale': ['program', 'price_per_sqm', 'yield','golden_visa','for_investment']
        },
        'gi': {
            'base': ['area','buildable', 'electricity', 'view', 'water', 'beach_dist'],
            'rent': [], 'sale': ['price_per_sqm']
        }
    };

    const dynamicFieldsMap = {
        'bedrooms': ['bedrooms', 'υπνοδωμάτια', 'Υ/Δ', 'beds', 'min beds', 'min bedrooms'],
        'bathrooms': ['bathrooms', 'μπάνια', 'baths', 'min baths', 'min baths/wc'],
        'balcony': ['μπαλκόνι', 'balcony'],
        'floor': ['όροφος', 'floor', 'min floor', 'max floor'], 
        'last_floor' : ['τελευταίος', 'last'],
        'lift': ['ανελκυστήρας', 'ανελκυστήρες', 'elevator', 'elevators'],
        'year_built': ['έτος κατασκευής', 'year built', 'built after year', 'renovation year', 'construction year'],
        'furniture': ['επίπλωση', 'furnishing', 'min furnishing'],
        'equipment': ['εξοπλισμός', 'συσκευές', 'equipment', 'min equipment'],
        'price_per_sqm': ['τιμή αγοράς ανά τ.μ.', 'price per sq.m.', 'max price per sq.m.'],
        'yield': ['απόδοση πάνω από','return over %'],
        'beach_dist': ['απόσταση από παραλία', 'απόσταση από θάλασσα', 'distance from sea', 'max distance from sea'],
        'nuisance': ['όχληση', 'nuisance'],
        'wc': ['wc_count_identifier'], 
        'wc_type': ['wctype_identifier'], 
        'area' : ['εμβαδόν', 'area', 'min area (sq.m.)', 'max area (sq.m.)'],
        'water': ['ύδρευση', 'άρδευση', 'water supply', 'water supply/irrigation'],
        'heating': ['θέρμανση', 'τρόπος θέρμανσης', 'heating system', 'heating type', 'heating'],
        'garden': ['κήπος', 'αυλή', 'yard', 'garden', 'yard/garden'],
        'pool': ['πισίνα', 'swimming pool'],
        'parking': ['χώρο στάθμευσης', 'with parking', 'parking space'],
        'view': ['με θέα', 'with view'],
        'pets': ['κατοικίδια', 'pets allowed'],
        'split': ['διαχωρισμό','for subdivision potential'],
        'golden_visa': ['για golden visa', 'for golden visa'],
        'health': ['υγειονομικού', 'health interest'],
        'chimney': ['με φουγάρο', 'chimney'],
        'commercial_road': ['σε εμπορικό δρόμο', 'commercial road'],
        'corner': ['γωνιακό', 'corner'],
        'program': ['πρόγραμμα', 'program'],
        'buildable': ['οικοδομήσιμο', 'buildable'],
        'electricity': ['με ηλεκτρικό ρεύμα', 'with electricity'],
        'window_frames' : ['κουφώματα', 'window frames'],
        'energy_class' : ['ενεργειακή κατηγορία', 'energy class'],
        'for_pensioners' : ['για συνταξιούχους', 'for retirees'],
        'for_investment' : ['επενδυτικό', 'investment'],
        'for_professional_use' : ['για επαγγελματική χρήση', 'for professional use'],
        'for_holiday_home' : ['παραθεριστική κατοικία', 'for holiday home'],
        'for_employees' : ['για εργαζόμενους', 'for employees'],
        'for_short_term_rentals' :['για βραχυχρόνιες μισθώσεις', 'for short-term rentals'],
        'for_couples' : ['για ζευγάρια', 'for couples'],
        'for_families' :['για οικογένειες', 'for families'],
        'for_large_families' : ['για πολυμελείς οικογένειες', 'for large families'],
        'for_students' : ['για φοιτητές', 'for students'],
        'fireplace' : ['τζάκι', 'fireplace'],
        'furnished' : ['επιπλωμένο', 'furnished'],
        'penthouse' : ['ρετιρέ', 'penthouse'],
        'bright' : ['φωτεινό', 'bright'],
        'dual_aspect' : ['διαμπερές', 'airy']
    };

    // ==========================================================================
    //  MAIN LOGIC
    // =========================================================================
    function applyDynamicSearchRules() {
        // --- Determine Current Type & Group ---
        let rawType = $('select[name="type[]"]').val() || $('select[name="property_type"]').val(); 
        let selectedTypes = Array.isArray(rawType) ? rawType : (rawType ? [rawType] : []);
        let isAnyType = selectedTypes.length === 0 || selectedTypes.includes('any') || selectedTypes.includes('');
        
        let isLand = selectedTypes.some(r => landTypes.includes(r));
        let isCommercial = selectedTypes.some(r => commercialTypes.includes(r));
        let catGroup = isAnyType ? 'any' : (isLand ? 'gi' : (isCommercial ? 'epaggelmatikos-xwros' : 'katoikia'));

        let $statusSelects = $('select[name="status[]"], select[name="property_status"]');
        let statusChanged = false;

        $statusSelects.each(function() {
            let $select = $(this);
            
            $select.find('option').each(function() {
                let $opt = $(this);
                let val = ($opt.val() || '').toLowerCase();
                let isExchangeOpt = val.includes('gia-antiparoxi') || val.includes('exchange') || val.includes('antiparoxi');

                if (isExchangeOpt) {
                    if (!isLand && !isAnyType) {
                        if (!$opt.prop('disabled')) {
                            $opt.prop('disabled', true);
                            statusChanged = true;
                            if ($opt.is(':selected')) {
                                $opt.prop('selected', false);
                                $select.val(''); 
                            }
                        }
                    } else {
                        if ($opt.prop('disabled')) {
                            $opt.prop('disabled', false);
                            statusChanged = true;
                        }
                    }
                }
            });

            if (statusChanged && $.fn.selectpicker && $select.parent().hasClass('bootstrap-select')) {
                $select.selectpicker('refresh');
            }
        });

        // --- Determine Current Status (Rent/Sale) ---
        let rawStatus = $('select[name="status[]"]').val() || $('select[name="property_status"]').val();
        let selectedStatuses = Array.isArray(rawStatus) ? rawStatus : (rawStatus ? [rawStatus] : []);
        let isRent = selectedStatuses.some(s => s.includes('%ce%b5%ce%bd%ce%bf%ce%b9%ce%ba%ce%af%ce%b1%cf%83%ce%b7') || s.includes('rent') || s === 'gia-enoikiasi');
        let isSale = selectedStatuses.some(s => s.includes('%cf%80%cf%8e%ce%bb%ce%b7%cf%83%ce%b7') || s.includes('sale') || s === 'gia-polisi');

        // Build allowed keys for the current configuration
        let allowedKeys = [...featureRules[catGroup].base];
        if (isRent) allowedKeys.push(...featureRules[catGroup].rent);
        if (isSale) allowedKeys.push(...featureRules[catGroup].sale);

        // --- TOGGLE CUSTOM FIELDS VISIBILITY ---
        $('.rhea-option-bar, .rhea_prop_search__option, .rhea-inquiry-field').each(function() {
            let wrapper = $(this);
            if (wrapper.is('.rhea_types_field, .rhea_status_field, .rhea_prop_locations_field, .rhea_price_slider_field, .rhea_property_id_field, .rhea_min_area_field, .rhea_max_area_field, .rhea-field-type-property_status, .rhea-field-type-property_type, .rhea-field-type-property_city')) return;

            // OPTIMIZATION: Check cache for field text to avoid expensive DOM cloning
            let fieldText = wrapper.data('rh-field-name');
            if (!fieldText) {
                let labelNode = wrapper.find('label').first();
                if (labelNode.length) {
                    let clone = labelNode.clone();
                    clone.find('.rh-req-toggle').remove(); 
                    clone.contents().filter(function() { return this.nodeType === 3; }).each(function() { this.nodeValue = this.nodeValue.replace(/\s*\(\d+\)/g, ''); });
                    fieldText = clone.text().trim();
                } 
                if (!fieldText) {
                    fieldText = wrapper.find('input[name="rhea_custom_label[]"]').val() || wrapper.find('select').attr('title') || wrapper.find('input').attr('placeholder') || wrapper.find('input, select').attr('name') || '';
                }
                wrapper.data('rh-field-name', fieldText); // Cache it for future runs
            }

            let searchStr = fieldText.toLowerCase();
            let fieldName = (wrapper.find('input, select').attr('name') || '').toLowerCase();

            if (searchStr.indexOf('μπάνια') !== -1 && searchStr.indexOf('wc') !== -1) searchStr = searchStr.replace('/wc', '').replace(' wc', '').trim();
            if (searchStr.indexOf('baths') !== -1 && searchStr.indexOf('wc') !== -1) searchStr = searchStr.replace('/wc', '').replace(' wc', '').trim();
            if (searchStr.indexOf('τελευταίος') !== -1 && searchStr.indexOf('όροφος') !== -1) searchStr = searchStr.replace('όροφος', '').trim();
            if (searchStr.indexOf('top') !== -1 && searchStr.indexOf('floor') !== -1) searchStr = searchStr.replace('floor', '').trim();

            if (fieldName === 'inspiry_wc_type' || fieldName === 'inspiry_wc_type[]') {
                searchStr = 'wctype_identifier';
            } else if (fieldName === 'inspiry_wc' || fieldName === 'inspiry_wc[]') {
                searchStr = 'wc_count_identifier';
            } else {
                if (searchStr.indexOf('wc type') !== -1) {
                    searchStr = 'wctype_identifier';
                } else if (searchStr === 'wc') {
                    let hasTypeOptions = wrapper.find('select option').filter(function() { let t = $(this).text().toLowerCase(); return t.indexOf('ατομικό') !== -1 || t.indexOf('κοινόχρηστο') !== -1 || t.indexOf('private') !== -1 || t.indexOf('shared') !== -1; }).length > 0;
                    searchStr = hasTypeOptions ? 'wctype_identifier' : 'wc_count_identifier';
                }
            }

            let matchedKeys = Object.keys(dynamicFieldsMap).filter(key => dynamicFieldsMap[key].some(keyword => searchStr.indexOf(keyword) !== -1));

            if (matchedKeys.length > 0) {
                let isAllowed = matchedKeys.some(key => allowedKeys.includes(key));
                
                let selects = wrapper.find('select');
                let textInputs = wrapper.find('input[type="text"], input[type="number"]').not('input[name="rhea_custom_label[]"]');
                
                if (selects.length > 0) {
                    selects.attr('title', anyText).attr('data-none-selected-text', anyText);
                    let firstOpt = selects.find('option:first');
                    if (firstOpt.length > 0 && (firstOpt.val() === '' || firstOpt.val() === 'any')) {
                        firstOpt.text(anyText).attr('title', anyText);
                    }
                }
                if (textInputs.length > 0) {
                    textInputs.attr('placeholder', anyText);
                }

                if (isAllowed) {
                    let wasHidden = wrapper.hasClass('rh-force-hide');
                    if (wasHidden) {
                        wrapper.removeClass('rh-force-hide');
                        let elId = wrapper.find('input, select').attr('id');
                        if (elId) $('label[for="' + elId + '"]').removeClass('rh-force-hide');
                    }
                    
                    wrapper.find('input, select').prop('disabled', false).each(function() {
                        let name = $(this).attr('name');
                        if (name) {
                            $(this).closest('form').find('input[type="hidden"][name="' + name + '"]').prop('disabled', false);
                        }
                    });

                    if (wasHidden && selects.length > 0 && $.fn.selectpicker && selects.parent().hasClass('bootstrap-select')) {
                        selects.selectpicker('refresh');
                        let currentVal = selects.val();
                        if (!currentVal || currentVal === '' || currentVal === 'any') {
                            selects.parent().find('.filter-option-inner-inner').text(anyText);
                            selects.parent().find('.btn.dropdown-toggle').attr('title', anyText);
                        }
                    }
                } else {
                    let wasHidden = wrapper.hasClass('rh-force-hide');
                    if (!wasHidden) {
                        wrapper.addClass('rh-force-hide');
                        let elId = wrapper.find('input, select').attr('id');
                        if (elId) $('label[for="' + elId + '"]').addClass('rh-force-hide');
                        wrapper.find('input[type="checkbox"]').prop('checked', false);
                        textInputs.val('');
                        
                        if (selects.length > 0) {
                            let firstVal = selects.find('option:first').val() || '';
                            if (selects.val() !== firstVal) selects.val(firstVal).trigger('change');
                            
                            if ($.fn.selectpicker && selects.parent().hasClass('bootstrap-select')) {
                                selects.selectpicker('refresh'); 
                                selects.parent().find('.filter-option-inner-inner').text(anyText);
                                selects.parent().find('.btn.dropdown-toggle').attr('title', anyText);
                            } 
                        }
                    }
                    
                    wrapper.find('input, select').prop('disabled', true).each(function() {
                        let name = $(this).attr('name');
                        if (name) {
                            $(this).closest('form').find('input[type="hidden"][name="' + name + '"]').prop('disabled', true);
                        }
                    });
                }
            }
        });
    }

    // ==========================================================================
    //  EVENT HOOKS
    // =========================================================================
    $(document).on('change', 'select[name="type[]"], select[name="status[]"], select[name="property_type"], select[name="property_status"]', applyDynamicSearchRules);

    // OPTIMIZATION: Run once immediately, and rely on AJAX complete for dynamically loaded forms
    // instead of arbitrary heavy timeouts.
    applyDynamicSearchRules();
    $(document).ajaxComplete(function() {
        setTimeout(applyDynamicSearchRules, 50); 
    });
});