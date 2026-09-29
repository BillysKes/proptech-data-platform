/* --- Module: 04-ajax-interceptor.js --- */
jQuery(document).ready(function($) {
    let pageUrl = decodeURIComponent(window.location.href).toLowerCase();
    let isEn = pageUrl.indexOf('/en/') !== -1;
    let currentLang = isEn ? 'en' : 'el';

    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        if (options.url && options.url.indexOf('admin-ajax.php') !== -1) {
            if (options.url.indexOf('lang=') === -1 && options.url.indexOf('wpml_lang=') === -1) {
                let separator = options.url.indexOf('?') !== -1 ? '&' : '?';
                options.url = options.url + separator + 'lang=' + currentLang + '&wpml_lang=' + currentLang;
            }
            if (options.data && typeof options.data === 'string') {
                if (options.data.indexOf('lang=') === -1 && options.data.indexOf('wpml_lang=') === -1) {
                    options.data += '&lang=' + currentLang + '&wpml_lang=' + currentLang;
                }
            }
        }
    });

    $.ajaxPrefilter(function(options, originalOptions, jqXHR) {
        if (options.data && typeof options.data === 'string' && (options.data.indexOf('action=rhea_inquiry_send_message') !== -1 || options.data.indexOf('rhea_custom_field') !== -1)) {
            let pairs = options.data.split('&');
            let newPairs = [];
            
            for (let i = 0; i < pairs.length; i++) {
                let pairArr = pairs[i].split('=');
                let key = decodeURIComponent(pairArr[0]);

                let nativeFields = ['property_type', 'property_type[]', 'property_status', 'property_status[]', 'min_price', 'max_price','min_beds', 'min_area', 'max_area','price_range', 'bedrooms', 'bathrooms','property_zipcode'];
                let isNative = nativeFields.includes(key) || key.indexOf('property_city') !== -1 || key.indexOf('rhea_custom_label') !== -1 || key.indexOf('rhea_custom_field') !== -1;

                if (!isNative) {
                    newPairs.push(pairs[i]);
                }
            }
            
            let finalLabels = [];
            let finalValues = [];
            let form = $('.rhea-inquiry-form').first();

            // 1. Page Title / Type
            let pageTitle = $('h1.rh_banner__title').text().trim();
            if (!pageTitle) { pageTitle = document.title.split('-')[0].trim(); }
            if (pageTitle) {
                finalLabels.push('Τύπος εντολής');
                finalValues.push(pageTitle);
            }

            // 2. Property Status
            let statusText = form.find('select[name="property_status"] option:selected').text();
            if (statusText && statusText !== 'None' && statusText !== 'Διαλέξτε') {
                finalLabels.push('Σκοπός');
                finalValues.push(statusText.trim());
            }

            // 3. Property Type
            let typeVals = [];
            form.find('select[name^="property_type"] option:selected').each(function() {
                let t = $(this).text().replace(/^[-\s]+/, '').trim();
                if (t && t !== 'None' && t !== 'Διαλέξτε' && t !== 'Όλα τα είδη') typeVals.push(t);
            });
            if (typeVals.length > 0) {
                let typeField = form.find('select[name^="property_type"]').closest('.rhea-inquiry-field');
                let reqToggle = typeField.find('.rh-req-toggle');
                let toggleText = reqToggle.length > 0 ? ' [' + (reqToggle.attr('data-state') === 'required' ? 'Απαραίτητο' : 'Επιθυμητό') + ']' : '';
                finalLabels.push('Κατηγορία' + toggleText);
                finalValues.push(typeVals.join(', '));
            }

            // 4. Locations
            let tree = window.rhCustomLocationTree;
            if (tree) {
                let locValsLevel3 = [];
                let locValsLevel2 = [];
                let locValsLevel1 = [];

                let $lvl3 = form.find('select[name="property_city_lvl_3[]"]');
                let $lvl2 = form.find('select[name="property_city_lvl_2"]');
                let $lvl1 = form.find('select[name="property_city_lvl_1"]');

                let processSlug = function(slug) {
                    if(!slug || slug === 'any') return;
                    let node = tree.find(t => t.slug === slug);
                    if (node) {
                        if (!locValsLevel3.includes(node.name)) locValsLevel3.push(node.name);
                        if (node.parent > 0) {
                            let parent = tree.find(t => t.id == node.parent);
                            if (parent) {
                                if (!locValsLevel2.includes(parent.name)) locValsLevel2.push(parent.name);
                                if (parent.parent > 0) {
                                    let grand = tree.find(t => t.id == parent.parent);
                                    if (grand && !locValsLevel1.includes(grand.name)) locValsLevel1.push(grand.name);
                                }
                            }
                        }
                    }
                };

                // Analyze selected child locations and map their ancestors
                if ($lvl3.length && $lvl3.val() && $lvl3.val().length > 0) {
                    let slugs = Array.isArray($lvl3.val()) ? $lvl3.val() : [$lvl3.val()];
                    slugs.forEach(processSlug);
                } else if ($lvl2.length && $lvl2.val() && $lvl2.val() !== 'any') {
                    processSlug($lvl2.val());
                    locValsLevel2 = locValsLevel3; // Shift level 3 text to level 2
                    locValsLevel3 = [];
                    locValsLevel1 = [];
                    let node = tree.find(t => t.slug === $lvl2.val());
                    if(node && node.parent > 0) {
                         let parent = tree.find(t => t.id == node.parent);
                         if (parent && !locValsLevel1.includes(parent.name)) locValsLevel1.push(parent.name);
                    }
                } else if ($lvl1.length && $lvl1.val() && $lvl1.val() !== 'any') {
                    let node = tree.find(t => t.slug === $lvl1.val());
                    if (node) locValsLevel1.push(node.name);
                }

                // Append location arrays
                if (locValsLevel1.length > 0) {
                    finalLabels.push('Περιφέρεια');
                    finalValues.push(locValsLevel1.join(', '));
                }
                if (locValsLevel2.length > 0) {
                    finalLabels.push('Δήμος');
                    finalValues.push(locValsLevel2.join(', '));
                }
                if (locValsLevel3.length > 0) {
                    let reqToggle = $lvl3.closest('.rhea-inquiry-field').find('.rh-req-toggle');
                    let toggleText = reqToggle.length > 0 ? ' [' + (reqToggle.attr('data-state') === 'required' ? 'Απαραίτητο' : 'Επιθυμητό') + ']' : '';
                    finalLabels.push('Περιοχή' + toggleText);
                    finalValues.push(locValsLevel3.join(', '));
                }
            } else {
                // Fallback if tree is missing
                form.find('select[name^="property_city"]').each(function() {
                    if (!$(this).closest('.rh-force-hide').length) {
                        let vals = [];
                        $(this).find('option:selected').each(function() {
                            let txt = $(this).text().replace(/^[-\s]+/, '').trim();
                            if (txt && txt !== 'None' && txt !== 'Διαλέξτε' && txt !== 'Οποιοδήποτε' && txt !== 'Any') vals.push(txt);
                        });
                        if (vals.length > 0) {
                            let locField = $(this).closest('.rhea-inquiry-field');
                            let cloneLabel = locField.find('label').first().clone();
                            cloneLabel.find('.rh-req-toggle').remove(); 
                            let labelText = cloneLabel.text().trim() || 'Τοποθεσία';
                            let reqToggle = locField.find('.rh-req-toggle');
                            let toggleText = reqToggle.length > 0 ? ' [' + (reqToggle.attr('data-state') === 'required' ? 'Απαραίτητο' : 'Επιθυμητό') + ']' : '';
                            finalLabels.push(labelText + toggleText);
                            finalValues.push(vals.join(', '));
                        }
                    }
                });
            }

            // 5. Form inputs (Min Area, Max Price, Details)
            form.find('.rhea-inquiry-field:not(.rh-force-hide), .rhea-option-bar:not(.rh-force-hide)').each(function() {
                if ($(this).is('.rhea-field-type-property_status, .rhea-field-type-property_type, .rhea-field-type-property_city, .rhea_status_field, .rhea_types_field, .rhea_prop_locations_field')) return; 

                if ($(this).find('input[name="name"], input[name="email"], input[name="number"], textarea[name="message"], input[name="gdpr_accept"]').length > 0) return;

                let labelText = '';
                let labelInput = $(this).find('input[name="rhea_custom_label[]"]');
                
                if (labelInput.length > 0) {
                    labelText = labelInput.val().trim();
                } else {
                    let clone = $(this).find('label').first().clone();
                    clone.find('.rh-req-toggle').remove(); 
                    clone.contents().filter(function() { return this.nodeType === 3; }).each(function() { this.nodeValue = this.nodeValue.replace(/\s*\(\d+\)/g, ''); });
                    labelText = clone.text().trim();
                    if (!labelText) labelText = $(this).find('input, select').attr('placeholder') || $(this).find('input, select').attr('name') || 'Πεδίο';
                }
                
                let $toggle = $(this).find('.rh-req-toggle');
                if ($toggle.length > 0) {
                    let reqText = $toggle.attr('data-state') === 'required' ? 'Απαραίτητο' : 'Επιθυμητό';
                    labelText = labelText + ' [' + reqText + ']';
                }
                
                let vals = [];
                $(this).find('input, select, textarea').not(labelInput).not('[type="hidden"]').each(function() {
                    if ($(this).is(':checkbox') || $(this).is(':radio')) {
                        if ($(this).is(':checked')) {
                            let clone = $(this).parent().clone();
                            clone.find('.rh-req-toggle').remove(); 
                            let textVal = clone.text().replace(/\s*\(\d+\)/g, '').trim();
                            if (!textVal || textVal.toLowerCase() === 'on' || textVal === '1') textVal = 'Ναι';
                            vals.push(textVal);
                        }
                    } else if ($(this).is('select')) {
                        $(this).find('option:selected').each(function() {
                            let txt = $(this).text().replace(/^[-\s]+/, '').trim();
                            if (txt && txt !== 'None' && txt !== 'Διαλέξτε' && txt !== 'Αδιάφορο' && txt !== 'Any' && txt !== '') vals.push(txt);
                        });
                    } else {
                        let val = $(this).val().trim();
                        if (val) vals.push(val);
                    }
                });

                let flatValue = vals.join(', ').trim();
                if (flatValue !== '') { 
                    finalLabels.push(labelText);
                    finalValues.push(flatValue);
                }
            });

            for (let i = 0; i < finalLabels.length; i++) {
                newPairs.push(encodeURIComponent('rhea_custom_label[]') + '=' + encodeURIComponent(finalLabels[i]));
                newPairs.push(encodeURIComponent('rhea_custom_field[]') + '=' + encodeURIComponent(finalValues[i]));
            }

            options.data = newPairs.join('&');
        }
    });
});