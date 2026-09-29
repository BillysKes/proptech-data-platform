/* --- Module: 07-search-dropdowns.js (Optimized) --- */
jQuery(document).ready(function($) {
    let pageUrl = decodeURIComponent(window.location.href).toLowerCase();
    let isDemandPageStrict = pageUrl.indexOf('/property-request') !== -1 || pageUrl.indexOf('/ζήτηση') !== -1 ;
    let isEn =  pageUrl.indexOf('/en/') !== -1;

    // DYNAMIC LOCATION DROPDOWN REBUILDER
    function rebuildSearchLocations() {
        let $searchLocSelect = $('select.rhea_multi_select_picker_location, select[name="location[]"]').not('.rhea-inquiry-form select');
        
        if ($searchLocSelect.length > 0 && window.rhCustomLocationTree) {
            let tree = window.rhCustomLocationTree;
            
            $searchLocSelect.each(function() {
                let $select = $(this);
                if ($select.data('rebuilt-by-rh')) return;
                $select.data('rebuilt-by-rh', true);
                
                let currentVals = $select.val() || [];
                $select.empty();
                
                function buildOptions(parentId, level) {
                    let html = '';
                    let children = tree.filter(t => t.parent == parentId);
                    
                    if (level === 1) {
                        let allowedEnRegions = [
                            'East Macedonia & Thraki', 'Central Macedonia', 'West Macedonia', 
                            'Epirus', 'Thessalia', 'Ionian Islands', 'West Greece', 
                            'Sterea Hellas', 'Attiki', 'Peloponnese', 'North Aegean', 
                            'South Aegean', 'Crete'
                        ];
                        
                        children = children.filter(child => {
                            let childName = child.name.trim();
                            if (isEn) {
                                return allowedEnRegions.includes(childName);
                            } else {
                                return childName.startsWith('Περιφέρεια');
                            }
                        });
                    }
                    
                    children.forEach(child => {
                        let prefix = '';
                        if (level === 2) prefix = '- ';
                        if (level >= 3) prefix = '- - ';
                        
                        let unaccentedText = child.name.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
                        let tokens = `${unaccentedText} ${child.name}`;
                        
                        html += `<option value="${child.slug}" class="rh-loc-lvl-${level}" title="${child.name}" data-tokens="${tokens}">${prefix}${child.name}</option>`;
                        html += buildOptions(child.id, level + 1);
                    });
                    return html;
                }
                
                $select.append(buildOptions(0, 1));
                
                if (currentVals && currentVals.length > 0) {
                    let newVals = [];
                    let currentValsArray = Array.isArray(currentVals) ? currentVals : [currentVals];
                    currentValsArray.forEach(v => {
                        let baseSlug = v.replace(/-el$/, '').replace(/-en$/, '');
                        let match = tree.find(t => t.slug.replace(/-el$/, '').replace(/-en$/, '') === baseSlug);
                        if (match) newVals.push(match.slug);
                    });
                    $select.val(newVals);
                }
                
                if ($.fn.selectpicker && $select.closest('.bootstrap-select').length) {
                    $select.selectpicker('refresh');
                }
            });
        }
    }

    function enforceMultiSelects() {
        $('select[name^="inspiry_Ύδρευση/Άρδευση"]').not('.rh-multi-enforced').each(function() {
            let $waterSelect = $(this);
            $waterSelect.addClass('rh-multi-enforced');
            if (!$waterSelect.prop('multiple')) {
                let currentVal = $waterSelect.val();
                let shouldBeEmpty = (!currentVal || currentVal === 'any' || currentVal === '');
                let textAny = isEn ? 'Any' : 'Αδιάφορο';
                let textAll = isEn ? 'All' : 'Όλα';
                let textNone = isEn ? 'None' : 'Κανένα';

                if ($.fn.selectpicker && $waterSelect.closest('.bootstrap-select').length) {
                    $waterSelect.selectpicker('destroy');
                }

                let $newSelect = $waterSelect.clone();
                $newSelect.prop('multiple', true);
                $newSelect.find('option[value="any"], option[value=""], option.bs-title-option').remove();
                $newSelect.attr({
                    'data-selected-text-format': 'count > 1',
                    'data-actions-box': 'true',
                    'data-select-all-text': textAll,
                    'data-deselect-all-text': textNone,
                    'title': textAny
                });

                if (shouldBeEmpty) {
                    $newSelect.find('option').prop('selected', false).removeAttr('selected');
                    $newSelect.val([]);
                    $newSelect.attr('data-force-empty', 'true');
                }

                $waterSelect.replaceWith($newSelect);
                let $activeSelect = $('select[name^="inspiry_Ύδρευση/Άρδευση"]');

                if ($.fn.selectpicker) {
                    $activeSelect.addClass('rhea_multi_select_picker show-tick').selectpicker({ iconBase: 'fas', tickIcon: 'fa-check', noneSelectedText: textAny });
                    if (shouldBeEmpty) $activeSelect.selectpicker('val', []);
                }

                $activeSelect.on('changed.bs.select', function (e, clickedIndex) {
                    let $el = $(this);
                    let isHuman = (typeof clickedIndex !== 'undefined' && clickedIndex !== null);
                    let checkVal = $el.val();
                    let hasValue = false;
                    if (checkVal) {
                        if (Array.isArray(checkVal) && checkVal.length > 0 && checkVal[0] !== '') hasValue = true;
                        else if (typeof checkVal === 'string' && checkVal !== '') hasValue = true;
                    }
                    if (!isHuman && $el.attr('data-force-empty') === 'true') {
                        if (hasValue) {
                            $el.val([]);
                            setTimeout(function() { $el.selectpicker('val', []); }, 5);
                        }
                    } else if (isHuman) {
                        $el.removeAttr('data-force-empty');
                    }
                });
                
                if (shouldBeEmpty) {
                    setTimeout(function() {
                        if ($activeSelect.attr('data-force-empty') === 'true') {
                            let checkVal = $activeSelect.val();
                            if (checkVal && checkVal.length > 0) $activeSelect.selectpicker('val', []);
                        }
                    }, 1200);
                }
            }
        });
    }

    // --- GROUP PROPERTY TYPES INTO OPTGROUPS ---
    function groupPropertyTypes() {
        $('select[name="property_type"], select[name="type"], select[id="property_type"]').each(function() {
            let $select = $(this);
            if ($select.find('optgroup').length > 0 || $select.data('grouped-by-js')) return;
            $select.data('grouped-by-js', true);

            let missingOptions = isEn ? [
                { val: 'ekthesiakos-xwros', text: '- Gallery' },
                { val: 'katalima', text: '- Accommodation' },
                { val: 'ktirio-epaggelmatikos-xwros', text: '- Commercial Building' }
            ] : [
                { val: 'agrotemaxio', text: '- Αγροτεμάχιο' },
                { val: 'ekthesiakos-xwros', text: '- Εκθεσιακός Χώρος' },
                { val: 'katalima', text: '- Κατάλυμα' },
                { val: 'ktirio-epaggelmatikos-xwros', text: '- Κτίριο' }
            ];

            missingOptions.forEach(opt => {
                if ($select.find(`option[value="${opt.val}"]`).length === 0) {
                    $select.append(`<option value="${opt.val}">${opt.text}</option>`);
                }
            });

            let groupRes = isEn ? 'Residential' : 'Κατοικία';
            let groupCom = isEn ? 'Commercial' : 'Επαγγελματικός Χώρος';
            let groupLan = isEn ? 'Land' : 'Γη';
            let groupOth = isEn ? 'Other' : 'Άλλο';

            let mappingRules = {};
            mappingRules[groupRes] = ['διαμέρισμα', 'γκαρσονιέρα', 'στούντιο', 'μεζονέτα', 'μονοκατοικία', 'συγκρότημα κατοικιών', 'apartment', 'studio', 'maisonette', 'detached house', 'residential complex', 'residential', 'flatlet', 'residence complex','building'];
            mappingRules[groupCom] = ['επαγγελματικός χώρος', 'γραφείο', 'κατάστημα', 'αποθήκη', 'βιομηχανικός χώρος', 'βιοτεχνικός χώρος', 'ξενοδοχείο', 'εκθεσιακός χώρος', 'κτίριο', 'στάθμευσης χώρος', 'κατάλυμα', 'commercial property', 'building', 'commercial building', 'commercial space', 'office', 'shop', 'warehouse', 'industrial space', 'industrial property', 'small industrial property', 'craft space', 'hotel', 'accommodation',  'gallery', 'garage'];
            mappingRules[groupLan] = ['οικόπεδο', 'αγροτεμάχιο', 'κτήμα', 'plot', 'land', 'domaine'];

            let groupedOptions = { [groupRes]: [], [groupCom]: [], [groupLan]: [], [groupOth]: [] };
            let $options = $select.find('option');
            let $emptyOption = $options.filter(function() { return $(this).val() === '' || $(this).val() === 'any'; });
            let seenTexts = new Set(); 

            $options.each(function() {
                let $opt = $(this);
                let val = $opt.val();
                
                if (val === '' || val === 'any' || val === 'gi' || val === 'katoikia' || val === 'epaggelmatikos-xwros' || val === 'commercial' || val === 'land' || val === 'residential') return;

                let rawText = $opt.text();
                let cleanText = rawText.replace(/^[-\s]+/, '').trim();
                let lowerText = cleanText.toLowerCase();
                let targetGroup = null;

                if (val === 'ktirio') targetGroup = groupRes;
                else if (val === 'ktirio-epaggelmatikos-xwros') targetGroup = groupCom;
                else {
                    if (seenTexts.has(lowerText)) return; 
                    seenTexts.add(lowerText);
                    for (let groupName in mappingRules) {
                        if (mappingRules[groupName].some(k => k.toLowerCase() === lowerText)) {
                            targetGroup = groupName; break;
                        }
                    }
                }
                
                $opt.text(cleanText);
                if (targetGroup) groupedOptions[targetGroup].push($opt);
                else groupedOptions[groupOth].push($opt);
            });

            let currentVal = $select.val();
            let isBS = $.fn.selectpicker && $select.closest('.bootstrap-select').length > 0;
            if (isBS) $select.selectpicker('destroy');
            $select.empty();
            if ($emptyOption.length > 0) $select.append($emptyOption);

            for (let groupName in groupedOptions) {
                if (groupedOptions[groupName].length > 0) {
                    let $optgroup = $('<optgroup label="' + groupName + '"></optgroup>');
                    groupedOptions[groupName].forEach(function($opt) { $optgroup.append($opt); });
                    $select.append($optgroup);
                }
            }

            $select.val(currentVal);
            if (isBS) { $select.selectpicker({ iconBase: 'fas', tickIcon: 'fa-check' }); $select.selectpicker('refresh'); }
        });
    }

    // GREEK ACCENT-INSENSITIVE SEARCH FOR DROPDOWNS ---
    function enableAccentInsensitiveSearch() {
        $('select[data-live-search="true"]').not('.rh-accent-processed').each(function() {
            let $select = $(this);
            $select.addClass('rh-accent-processed');
            let modified = false;
            $select.find('option').each(function() {
                let $opt = $(this);
                let originalText = $opt.text();
                if (!originalText) return;
                let unaccentedText = originalText.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
                let currentTokens = $opt.attr('data-tokens') || '';
                if (currentTokens.indexOf(unaccentedText) === -1) {
                    $opt.attr('data-tokens', currentTokens + ' ' + unaccentedText + ' ' + originalText);
                    modified = true;
                }
            });
            if (modified && $.fn.selectpicker && $select.parent().hasClass('bootstrap-select')) {
                $select.selectpicker('refresh');
            }
        });
    }

    // HIGHLIGHT PARENT PROPERTY TYPES IN SEARCH ---
    function highlightParentSearchTypes() {
        let $selects = $('select[name="type[]"], select[name="type"]').not('.rh-parent-processed');
        let modified = false;
        $selects.each(function() {
            $(this).addClass('rh-parent-processed');
            $(this).find('option').each(function() {
                let val = $(this).val();
                if (val === 'gi' || val === 'katoikia' || val === 'epaggelmatikos-xwros' || val === 'land' || val === 'residential' || val === 'commercial') {
                    if (!$(this).hasClass('rh-parent-type')) { $(this).addClass('rh-parent-type'); modified = true; }
                }
            });
            if (modified && $.fn.selectpicker) { $(this).selectpicker('refresh'); }
        });
    }

    // HIGHLIGHT LOCATION LEVELS IN SEARCH ---
    function highlightLocationLevels() {
        let $selects = $('select.rhea_multi_select_picker_location, select[name="location[]"]').not('.rh-loc-lvl-processed');
        let modified = false;
        $selects.each(function() {
            $(this).addClass('rh-loc-lvl-processed');
            $(this).find('option').each(function() {
                let $opt = $(this);
                let val = $opt.val();
                if (!val || val === 'any' || val === '' || $opt.data('dashes-stripped')) return;

                let text = $opt.text();
                let prefixMatch = text.match(/^([-\s]+)/);
                let dashCount = prefixMatch ? (prefixMatch[0].match(/-/g) || []).length : 0;
                
                if (dashCount === 0) $opt.addClass('rh-loc-lvl-1');
                else if (dashCount === 1) $opt.addClass('rh-loc-lvl-2');
                else if (dashCount >= 2) $opt.addClass('rh-loc-lvl-3');

                let cleanText = text.replace(/^[-\s]+/, '').trim();
                $opt.text(cleanText);
                $opt.data('dashes-stripped', true);
                modified = true;
            });
            if (modified && $.fn.selectpicker) $(this).selectpicker('refresh');
        });
    }

    // REORDER PROPERTY TYPES IN SEARCH ENGINE ---
    function reorderSearchPropertyTypes() {
        let $selects = $('select[name="type[]"].rhea_multi_select_picker, select[name="type"].rhea_multi_select_picker').not('.rh-reorder-processed');
        let modified = false;
        $selects.each(function() {
            let $select = $(this);
            $select.addClass('rh-reorder-processed');
            if ($select.find('optgroup').length > 0) return;
            let $options = $select.find('option');
            if ($options.length === 0) return;

            let currentVal = $select.val();
            let groups = { 'any': [], 'katoikia': [], 'epaggelmatikos': [], 'gi': [], 'other': [] };
            let currentGroup = 'other';

            $options.each(function() {
                let $opt = $(this);
                let val = $opt.val();
                let text = $opt.text().trim();

                if (val === 'any' || val === '') currentGroup = 'any';
                else if (val === 'katoikia' || val === 'residential') currentGroup = 'katoikia';
                else if (val === 'epaggelmatikos-xwros' || val === 'commercial') currentGroup = 'epaggelmatikos';
                else if (val === 'gi' || val === 'land') currentGroup = 'gi';
                else if (!text.startsWith('-') && val !== 'any' && val !== '') currentGroup = 'other';

                groups[currentGroup].push($opt);
            });

            $options.detach();
            ['any', 'katoikia', 'epaggelmatikos', 'gi', 'other'].forEach(groupKey => {
                groups[groupKey].forEach($opt => $select.append($opt));
            });

            $select.val(currentVal);
            modified = true;
        });
        if (modified && $.fn.selectpicker) $selects.selectpicker('refresh');
    }


    function stripDuplicateDropdowns() {
        $('.rhea_prop_search__select').not('.rh-stripped').each(function() {
            $(this).addClass('rh-stripped'); // Mark as processed
            let $nestedDropdowns = $(this).find('.bootstrap-select .bootstrap-select');
            if ($nestedDropdowns.length) {
                let $select = $(this).find('select');
                $(this).find('.bootstrap-select').remove();
                $(this).find('.rhea_prop_search__selectwrap').append($select);
                if ($.fn.selectpicker) {
                    $select.removeClass('selectpicker').selectpicker({ iconBase: 'fas', tickIcon: 'fa-check' }).selectpicker('refresh');
                }
            }
        });
    }

    function patchMultiselectNatively() {
        $('.rhea_prop_search__select select[multiple]').not('.rh-multiselect-patched').each(function() {
            let $select = $(this);
            $select.addClass('rh-multiselect-patched');
            let picker = $select.data('selectpicker');
            let needsRefresh = false;
            if (picker && picker.options && picker.options.selectedTextFormat !== 'values') {
                picker.options.selectedTextFormat = 'values';
                needsRefresh = true;
            }
            $select.find('option').each(function() {
                let currentTitle = $(this).attr('title');
                let cleanText = $(this).text().replace(/^[-\s]+/, '').trim();
                if (cleanText && currentTitle !== cleanText) {
                    $(this).attr('title', cleanText);
                    needsRefresh = true;
                }
            });
            if (needsRefresh && picker) $select.selectpicker('refresh');
        });
    }

    function updateTooltipWithNewlines($select) {
        let selectedTexts = [];
        $select.find('option:selected').each(function() {
            let cleanText = $(this).text().replace(/^[-\s]+/, '').trim();
            if (cleanText && cleanText !== '') selectedTexts.push(cleanText);
        });
        let $btn = $select.siblings('.btn.dropdown-toggle');
        if ($btn.length) {
            if (selectedTexts.length > 0) $btn.attr('title', selectedTexts.join('\n'));
            else {
                let placeholder = $select.attr('title') || $select.data('none-selected-text') || (isEn ? 'Any' : 'Οποιοδήποτε');
                $btn.attr('title', placeholder);
            }
        }
    }

    $(document).on('rendered.bs.select changed.bs.select', '.rhea_prop_search__select select[multiple]', function() {
        let $select = $(this);
        setTimeout(function() { updateTooltipWithNewlines($select); }, 10);
    });

    // ====================================================================
    // INJECT CUSTOM SORT OPTIONS
    // ====================================================================
    function injectCustomSortOptions() {
        let $sortSelect = $('select[name="sort-properties"], select#sort-properties');
        if ($sortSelect.length > 0 && !$sortSelect.data('custom-sort-added')) {
            $sortSelect.data('custom-sort-added', true);
            $sortSelect.find('option[value="title-asc"], option[value="title-desc"]').remove();
            let ascText = isEn ? 'Price per sq.m. - Low to High' : 'Τιμή ανά τ.μ. - Φθηνότερα πρώτα';
            let descText = isEn ? 'Price per sq.m. - High to Low' : 'Τιμή ανά τ.μ. - Ακριβότερα πρώτα';
            
            if ($sortSelect.find('option[value="price-sqm-asc"]').length === 0) {
                $sortSelect.append(`<option value="price-sqm-asc">${ascText}</option>`);
            }
            if ($sortSelect.find('option[value="price-sqm-desc"]').length === 0) {
                $sortSelect.append(`<option value="price-sqm-desc">${descText}</option>`);
            }
            
            let urlParams = new URLSearchParams(window.location.search);
            let currentSort = urlParams.get('sortby');
            if (currentSort === 'price-sqm-asc' || currentSort === 'price-sqm-desc') {
                $sortSelect.val(currentSort);
            }
            
            if ($.fn.selectpicker && $sortSelect.parent().hasClass('bootstrap-select')) {
                $sortSelect.selectpicker('refresh');
                
                $sortSelect.closest('.rh_sort_controls').css({
                    'display': 'flex',
                    'align-items': 'stretch',
                    'background': 'transparent',
                    'border': 'none',
                    'box-shadow': 'none',
                    'padding': '0'
                });

                $sortSelect.closest('.bootstrap-select').css({
                    'background': 'transparent',
                    'box-shadow': 'none',
                    'border': 'none',
                    'padding': '0',
                    'height': '100%',
                    'width': '100%'
                });
                
                $sortSelect.siblings('.btn.dropdown-toggle').css({
                    'height': '100%',
                    'min-height': '48px',
                    'background-color': '#ffffff',
                    'border': '1px solid #eaeaea', 
                    'outline': 'none'
                });

                if (currentSort === 'price-sqm-asc' || currentSort === 'price-sqm-desc') {
                    let text = currentSort === 'price-sqm-asc' ? ascText : descText;
                    $sortSelect.parent().find('.filter-option-inner-inner').text(text);
                    $sortSelect.parent().find('.btn.dropdown-toggle').attr('title', text);
                }
            }
        }
    }

    $('.rhea-inquiry-form select').each(function() {
        let firstOpt = $(this).find('option:first');
        if (firstOpt.length && firstOpt.val() === '' && firstOpt.text().trim() === 'None') firstOpt.text('');
    });

    if (isDemandPageStrict && !isEn) {
        $('.rhea-inquiry-form select[name="property_status"] option').each(function() { if ($(this).val() === 'gia-polisi' || $(this).text().trim() === 'Πώληση') $(this).text('Αγορά'); });
    } else if (isDemandPageStrict && isEn) {
        $('.rhea-inquiry-form select[name="property_status"] option').each(function() { if ($(this).val() === 'gia-polisi' || $(this).text().trim() === 'For Sale') $(this).text('Buy'); });
    }

    let locSelects = $('.rhea-inquiry-form .rhea-field-type-property_city select');
    if (locSelects.length > 0 && window.rhCustomLocationTree) {
        let tree = window.rhCustomLocationTree;
        locSelects.each(function(index) {
            let $select = $(this);
            let isLast = (index === locSelects.length - 1); 
            let placeholder = $select.attr('title') || $select.find('option:first').text() || '';
            
            if (placeholder.replace(/\s+/g, '') === 'None' || placeholder.trim() === '' || placeholder.trim() === 'Περιφέρεια' || placeholder.trim() === 'Δήμος' || placeholder.trim() === 'Region' || placeholder.trim() === 'Municipality') {
                if (index === 0) placeholder = isEn ? 'Select Region...' : 'Διαλέξτε Περιφέρεια...';
                else if (index === 1) placeholder = isEn ? 'Select Municipality...' : 'Διαλέξτε Δήμο...';
                else if (index === 2) placeholder = isEn ? 'Select Area...' : 'Διαλέξτε Περιοχή...';
                else placeholder = isEn ? 'Select Location...' : 'Διαλέξτε τοποθεσία...';
            }
            $select.data('original-placeholder', placeholder);
            let textAll = isEn ? 'All' : 'Όλα';
            let textNone = isEn ? 'None' : 'Κανένα';

            if (isLast && isDemandPageStrict) {
                $select.attr('name', 'property_city_lvl_' + (index + 1) + '[]').prop('multiple', true).attr({ 'data-selected-text-format': 'count > 2', 'data-actions-box': 'true', 'data-select-all-text': textAll, 'data-deselect-all-text': textNone, 'title': placeholder, 'data-size': '8', 'data-dropup-auto': 'false' });
            } else {
                $select.attr('name', 'property_city_lvl_' + (index + 1)).removeAttr('multiple').attr({ 'data-size': '8', 'data-dropup-auto': 'false' });
            }
        });

        let lvl1 = $(locSelects[0]), lvl2 = locSelects.length > 1 ? $(locSelects[1]) : null, lvl3 = locSelects.length > 2 ? $(locSelects[2]) : null;
        
        function populateSelect(selectEl, parentId, levelIndex) {
            if (!selectEl) return;
            let isMulti = selectEl.prop('multiple');
            let placeholder = selectEl.data('original-placeholder');
            
            let currentVal = selectEl.val() || [];
            if (!Array.isArray(currentVal)) currentVal = [currentVal];

            if (levelIndex === 2 && parentId !== 0 && parentId !== -1) {
                if (!isDemandPageStrict) placeholder = isEn ? "From all Municipalities" : "Απ' όλους τους Δήμους";
            }
            
            selectEl.empty();
            if (!isMulti) selectEl.append('<option class="bs-title-option" value=""></option><option value="">' + placeholder + '</option>');
            
            let optionsToRender = tree.filter(t => t.parent == parentId);

            if (levelIndex === 1) {
                let allowedEnRegions = [
                    'East Macedonia & Thraki', 'Central Macedonia', 'West Macedonia', 
                    'Epirus', 'Thessalia', 'Ionian Islands', 'West Greece', 
                    'Sterea Hellas', 'Attiki', 'Peloponnese', 'North Aegean', 
                    'South Aegean', 'Crete'
                ];
                
                optionsToRender = optionsToRender.filter(child => {
                    let childName = child.name.trim();
                    if (!isEn) {
                        return childName.startsWith('Περιφέρεια');
                    } else {
                        return allowedEnRegions.includes(childName);
                    }
                });
            }
            
            if (levelIndex === 3 && parentId > 0) {
                let parentTerm = tree.find(t => t.id == parentId);
                if (parentTerm) {
                    optionsToRender.unshift(parentTerm); 
                }
            }
            
            if (isMulti && currentVal.length > 0) {
                currentVal.forEach(val => {
                    if (!optionsToRender.find(t => t.slug === val)) {
                        let selectedTerm = tree.find(t => t.slug === val);
                        if (selectedTerm) {
                            optionsToRender.push(selectedTerm);
                        }
                    }
                });
            }

            optionsToRender.forEach(child => {
                if (child.name.trim().toLowerCase() === 'περιφέρεια' || child.name.trim().toLowerCase() === 'region') return;
                
                let displayName = child.name;
                if (levelIndex === 3 && child.id == parentId) {
                    displayName = isEn ? child.name + ' (All)' : child.name + ' (Όλος)';
                }
                
                selectEl.append('<option value="' + child.slug + '" data-id="' + child.id + '">' + displayName + '</option>');
            });
            
            if (currentVal.length > 0) selectEl.val(currentVal); else if (!isMulti) selectEl.val(""); 
            
            if ($.fn.selectpicker) {
                let hasWrapper = selectEl.parent().hasClass('bootstrap-select');
                if (isMulti) {
                    if (!hasWrapper) { 
                        selectEl.selectpicker({ iconBase: 'fas', tickIcon: 'fa-check', noneSelectedText: placeholder }); 
                        selectEl.parent('.bootstrap-select').addClass('rhea-inquiry-form-field bs3 show-tick').removeClass('btn-group'); 
                    } else { 
                        selectEl.data('selectpicker').options.noneSelectedText = placeholder; 
                        selectEl.selectpicker('refresh'); 
                    }
                } else if (hasWrapper) selectEl.selectpicker('refresh');
            }
        }

        populateSelect(lvl1, 0, 1);
        let initLvl1Val = lvl1.val();
        let initLvl1Term = initLvl1Val ? tree.find(t => t.slug === initLvl1Val) : null;
        let initLvl1Id = initLvl1Term ? initLvl1Term.id : -1;
        if (lvl2) {
            populateSelect(lvl2, initLvl1Id, 2);
            let initLvl2Val = lvl2.val();
            let initLvl2Term = initLvl2Val ? tree.find(t => t.slug === initLvl2Val) : null;
            let initLvl2Id = initLvl2Term ? initLvl2Term.id : -1;
            if (lvl3) populateSelect(lvl3, initLvl2Id, 3);
        }
        lvl1.on('change', function() { let selectedTerm = tree.find(t => t.slug === $(this).val()); if (lvl2) { populateSelect(lvl2, selectedTerm ? selectedTerm.id : -1, 2); if (lvl3) populateSelect(lvl3, -1, 3); } });
        if (lvl2 && lvl3) { lvl2.on('change', function() { let selectedTerm = tree.find(t => t.slug === $(this).val()); populateSelect(lvl3, selectedTerm ? selectedTerm.id : -1, 3); }); }
    }

    // ====================================================================
    // RUNNER
    // ====================================================================
    function runDropdownEnhancements() {
        rebuildSearchLocations();
        enforceMultiSelects();
        groupPropertyTypes();
        reorderSearchPropertyTypes();
        enableAccentInsensitiveSearch();
        highlightParentSearchTypes();
        highlightLocationLevels();
        stripDuplicateDropdowns();
        patchMultiselectNatively();
        injectCustomSortOptions();
    }

    // Run once on initial page load
    runDropdownEnhancements();

    // Run whenever an AJAX request completes (e.g., dynamically loading search forms)
    $(document).ajaxComplete(function(event, xhr, settings) {
        setTimeout(function() {
            runDropdownEnhancements();
        }, 150);
    });

});

/* --- Module: Address Map Link Injector --- */
jQuery(document).ready(function($) {
    let mapsUrl = 'https://maps.app.goo.gl/vFf1JBqonKTE7ncE9';
    let addressRegex = /Ολύμπου\s*129|Olympou\s*129/i;

    $('.rh_contact_widget__item p.content').each(function() {
        if (addressRegex.test($(this).text())) {
            if ($(this).find('a.rh-custom-address-link').length === 0) {
                let addressHtml = $(this).html().trim();
                $(this).html(`<a href="${mapsUrl}" target="_blank" rel="noopener noreferrer" class="rh-custom-address-link">${addressHtml}</a>`);
            }
        }
    });

    $('.rh_contact__item p.content').each(function() {
        if (addressRegex.test($(this).text())) {
            if ($(this).find('a.rh-custom-address-link').length === 0) {
                let $label = $(this).find('.label');
                let labelHtml = $label.length ? $label.prop('outerHTML') : '';
                let addressHtml = $(this).html().replace(labelHtml, '').trim();
                $(this).html(`${labelHtml} <a href="${mapsUrl}" target="_blank" rel="noopener noreferrer" class="rh-custom-address-link">${addressHtml}</a>`);
            }
        }
    });
});