/* --- Module: 08-property-cards-ui.js (Optimized) --- */
jQuery(document).ready(function($) {
    let pageUrl = decodeURIComponent(window.location.href).toLowerCase();
    let isEn =  pageUrl.indexOf('/en/') !== -1 ;

    //  INJECT LOCATION UNDER SINGLE PROPERTY TITLE ---
    let $propertyTitleWrap = $('.rh_page__property_title');
    if ($propertyTitleWrap.length > 0 && $propertyTitleWrap.find('.rh_page__property_address').length === 0) {
        let locationText = $('.property-breadcrumbs ul li:last-child').text().trim();
        if (locationText) {
            let locationHtml = `
                <p class="rh_page__property_address" style="margin-top: 10px; display: flex; align-items: center; color: #808080; font-size: 15px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 6px; opacity: 0.6;">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13C19 5.13 15.87 2 12 2z M12 11.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5S13.38 11.5,12 11.5z"/>
                    </svg>
                    ${locationText}
                </p>
            `;
            $('.rh_page__title').after(locationHtml);
        }
    }

    // DYNAMIC ADDITIONAL DETAILS GROUPING ---
    let $detailsList = $('ul.rh_property__additional, ul.rh_property__additional_list, ul.property-additional-details-list').first();
    let $parentWrap = $detailsList.parent(); 

    if ($detailsList.length > 0 && !$parentWrap.hasClass('rh-details-sorted')) {
        $parentWrap.addClass('rh-details-sorted');
        
        let groups = isEn ? {
            'Building': { keywords: ['Condition', 'Construction Type', 'Renovation Year', 'Floors', 'Window Frames', 'Glazing','Elevators','WC'], items: [] },
            'Amenities': { keywords: ['Heating System', 'Heating Type', 'Balcony', 'Awnings','Insect Screens', 'Radiators', 'Security Door', 'Alarm', 'Cooling', 'Electricity','Water Supply','Fenced','Furnished','Equipment','Electric'], items: [] },
            'Other Details': { keywords: [], items: [] }
        } : {
            'Κτίσμα': { keywords: ['Κατάσταση', 'Είδος κατασκευής', 'Έτος ανακαίνισης', 'Πατώματα', 'Κουφώματα', 'Υαλοπίνακες','Ανελκυστήρας','WC'], items: [] },
            'Παροχές': { keywords: ['Τρόπος Θέρμανσης', 'Εγκατ. Θέρμανσης', 'Μπαλκόνι', 'Τέντες','Σίτες', 'Σώματα', 'Εξώπορτα', 'Συναγερμός', 'Ψύξη', 'Ηλεκτρικό','Ύδρευση/Άρδευση','Περίφραξη','Επίπλωση','Συσκευές'], items: [] },
            'Λοιπά Στοιχεία': { keywords: [], items: [] }
        };

        $detailsList.find('li').each(function() {
            let $item = $(this);
            let label = $item.find('.title, .label, strong').text().replace(':', '').trim() || $item.text().split(':')[0].trim();
            if (label.toLowerCase() === 'common_note') return true; 

            let placed = false;
            for (let groupName in groups) {
                if (groups[groupName].keywords.some(k => label.includes(k))) {
                    groups[groupName].items.push($item);
                    placed = true; break;
                }
            }
            if (!placed) {
                let defaultGroup = isEn ? 'Other Details' : 'Λοιπά Στοιχεία';
                groups[defaultGroup].items.push($item);
            }
        });

        let listClasses = $detailsList.attr('class'); 
        $detailsList.remove(); 

        for (let groupName in groups) {
            if (groups[groupName].items.length > 0) {
                $parentWrap.append('<h4 class="rh_property__heading" style="margin-top: 25px; margin-bottom: 15px; border-bottom: 1px solid #eaeaea; padding-bottom: 8px;">' + groupName + '</h4>');
                let $newList = $('<ul class="' + listClasses + '"></ul>');
                groups[groupName].items.forEach($li => $newList.append($li));
                $parentWrap.append($newList);
            }
        }
    }

    //  WC REORDERING ---
    function reorderWCMetaElements() {
        $('.rh_meta_titles, h4, .title').not('.rh-wc-processed').filter(function() {
            return $(this).text().trim().toLowerCase() === 'wc';
        }).each(function() {
            $(this).addClass('rh-wc-processed');
            let $wcBlock = $(this).closest('.rh_prop_card__meta, .rh_list_card__meta, .rh_property__meta');
            if ($wcBlock.length) {
                let $statsContainer = $wcBlock.parent();
                let $bathroomsBlock = null;
                $statsContainer.children('.rh_prop_card__meta, .rh_list_card__meta, .rh_property__meta').each(function() {
                    let titleText = $(this).find('.rh_meta_titles, h4, .title').text().trim().toLowerCase();
                    if (titleText.indexOf('μπάνια') !== -1 || titleText.indexOf('bathrooms') !== -1) {
                        $bathroomsBlock = $(this);
                    }
                });
                if ($bathroomsBlock && $bathroomsBlock.length) {
                    $wcBlock.insertAfter($bathroomsBlock);
                }
            }
        });
    }

    function reorderAvailabilityFields() {
        $('ul.rh_property__additional, ul.rh_property__additional_list, ul.property-additional-details-list').not('.rh-avail-processed').each(function() {
            let $list = $(this);
            $list.addClass('rh-avail-processed'); // Mark as processed
            let $avail = null;
            let $availFrom = null;

            $list.find('li').each(function() {
                let label = $(this).find('.title, .label, strong').text().replace(':', '').trim().toLowerCase();
                if (label === 'διαθεσιμότητα' || label === 'availability') {
                    $avail = $(this);
                } else if (label === 'ελεύθερο από' || label === 'available from') {
                    $availFrom = $(this);
                }
            });

            if ($avail && $availFrom) {
                $availFrom.insertAfter($avail);
            }
        });
    }

    // CUSTOM SVG ICON FIX ---
    function fixCustomSVGIcons() {
        $('.rh_property__meta i, .rh_prop_card__meta i, .rh_list_card__meta i, .rh_meta_titles i').not('.rh-svg-processed').each(function() {
            $(this).addClass('rh-svg-processed');
            let rawClass = $(this).attr('class');
            if (rawClass && (rawClass.indexOf('<img') !== -1 || rawClass.indexOf('<svg') !== -1)) {
                // Remove the processing class before replacing to avoid polluting the new HTML
                let cleanClass = rawClass.replace('rh-svg-processed', '').trim();
                $(this).replaceWith(cleanClass);
            }
        });
    }

    // LOCATION INJECTION ---
    function injectCardLocationsInstantly() {
        let $cards = $('.rh_prop_card, .rh_list_card, .property-card, .rh_prop_card_elementor').not('.location-injected');
        if ($cards.length === 0) return; 

        $cards.each(function() {
            let wpId = $(this).attr('data-rh-id'); 
            if (wpId && typeof wpId === 'string') wpId = wpId.replace('RH-', '');
            
            if (!wpId) {
                let classList = $(this).attr('class');
                if (classList) {
                    let match = classList.match(/post-(\d+)/);
                    if (match) wpId = match[1];
                }
            }
            
            let crmId = $(this).attr('data-property-id'); 
            if (crmId && typeof crmId === 'string') crmId = crmId.replace('RH-', '');
            
            let locationText = '';
            
            if (wpId && typeof window.rhAllPropertyLocations !== 'undefined' && window.rhAllPropertyLocations[wpId]) {
                locationText = window.rhAllPropertyLocations[wpId];
            } 
            else if (crmId && typeof window.rhAllPropertyLocations !== 'undefined' && window.rhAllPropertyLocations[crmId]) {
                locationText = window.rhAllPropertyLocations[crmId];
            }

            if (!locationText) {
                let excerpt = $(this).find('.rh_prop_card__excerpt, .rh_list_card__excerpt, .property-excerpt p').text().trim();
                if (excerpt && excerpt.indexOf(',') !== -1) {
                    let potentialLocation = excerpt.split(',')[0].trim();
                    if (potentialLocation.length > 2 && potentialLocation.length < 40) {
                        locationText = potentialLocation;
                    }
                }
            }

            if (locationText && locationText.trim() !== '') {
                let locationHtml = `
                    <div class="rh_card__property_address" style="margin: 5px 0 12px 0; display: flex; align-items: center; color: #808080; font-size: 14px; line-height: 1.4; width: 100%; clear: both;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="margin-right: 5px; opacity: 0.6; flex-shrink: 0;">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13C19 5.13 15.87 2 12 2z M12 11.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5s2.5 1.12 2.5 2.5S13.38 11.5,12 11.5z"/>
                        </svg>
                        ${locationText}
                    </div>
                `;
                let $h3 = $(this).find('h3').first();
                if($h3.length > 0) $h3.after(locationHtml);
                else $(this).find('.rh_list_card__map_details, .rh_prop_card__details').prepend(locationHtml);
            }
            $(this).addClass('location-injected'); 
        });
    }


    function addIconTooltips() {
        $('.rh_prop_card__meta, .rh_list_card__meta, .rh_property__meta').not('.rh-tooltip-processed').each(function() {
            $(this).addClass('rh-tooltip-processed'); // Mark as processed
            let metaLabel = $(this).find('.rhea_meta_titles, .rh_meta_titles, h4, .title').text().replace(':', '').trim();
            if (metaLabel === 'Υ/Δ' || metaLabel.toLowerCase() === 'bed') {
                metaLabel = isEn ? 'Bedrooms' : 'Υπνοδωμάτια';
            }
            if (metaLabel !== '') {
                let $target = $(this).find('.rhea_meta_icon_wrapper, .rh_meta_icon_wrapper');
                if ($target.length === 0) $target = $(this);
                $target.attr('title', metaLabel);
            }
        });
    }

    // PROPERTY ID INJECTION ---
    function injectPropertyIDs() {
        let $cards = $('.rh_prop_card, .rh_list_card, .property-card, .rh_prop_card_elementor').not('.id-injected');
        if ($cards.length === 0) return; 

        $cards.each(function() {
            let $card = $(this);
            let href = $card.find('h3 a').attr('href');
            let propId = '';
            
            if (href) {
                try {
                    let urlObj = new URL(href, window.location.origin);
                    let match = urlObj.pathname.match(/-(\d+-\d+)\/?$/);
                    if (match) propId = match[1];
                } catch(e) {}
            }

            if (propId) {
                let idLabel = isEn ? 'ID:' : 'Κωδ:';
                let idHtml = `
                    <span class="rh_card__property_id" title="Property ID" style="background: #f4f8fb; border: 1px solid #7cb3d8; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700; color: #1a73e8; letter-spacing: 0.5px; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,0.05); line-height: 1; font-family: inherit;">
                        ${idLabel} ${propId}
                    </span>`;
                
                if ($card.hasClass('rh_prop_card_elementor')) {
                    let $priceLabel = $card.find('.rh_prop_card__priceLabel').first();
                    if ($priceLabel.length > 0) {
                        $priceLabel.css({
                            'display': 'flex',
                            'justify-content': 'space-between',
                            'align-items': 'center',
                            'width': '100%',
                            'gap': '10px',
                            'flex-wrap': 'wrap'
                        });
                        $priceLabel.append(idHtml);
                    }
                } else {
                    let $h3 = $card.find('h3').first();
                    if ($h3.length > 0) {
                        $h3.css({
                            'display': 'flex',
                            'justify-content': 'space-between',
                            'align-items': 'center',
                            'width': '100%',
                            'gap': '10px',
                            'flex-wrap': 'wrap'
                        });
                        $h3.append(idHtml);
                    }
                }
            }
            $card.addClass('id-injected');
        });
    }

    // ====================================================================
    // COMMON NOTES LIST
    // ====================================================================
    function formatCommonNotes() {
        $('.rh_property__common_note .rh-property-note-inner-wrap p').not('.formatted-note').each(function() {
            let htmlContent = $(this).html();
            if (!htmlContent) return;
            
            let lines = htmlContent.split(/<br\s*\/?>|\n/i);
            let listItems = '';
            
            lines.forEach(function(line) {
                let cleanLine = line.trim();
                if (cleanLine.length > 0) {
                    listItems += '<li>' + cleanLine + '</li>';
                }
            });
            
            if (listItems !== '') {
                $(this).replaceWith('<ul class="rh-custom-notes-list formatted-note">' + listItems + '</ul>');
            } else {
                $(this).addClass('formatted-note'); 
            }
        });
    }

    // ====================================================================
    // SINGLE PROPERTY PHOTO COUNTER
    // ====================================================================
    function injectPhotoCounter() {
        let $sliderWrap = $('.property-detail-slider-wrapper').first();
        if ($sliderWrap.length === 0) $sliderWrap = $('.inspiry_property_portrait_slider').first();
        if ($sliderWrap.length === 0) return;

        if ($sliderWrap.data('counter-injected')) return;
        $sliderWrap.data('counter-injected', true);

        $sliderWrap.find('.rh-custom-slider-counter').remove();

        let $slides = $sliderWrap.find('.slides > li');
        let totalSlides = $slides.length;

        if (totalSlides <= 1) return;

        let $counterBox = $('<div class="rh-custom-slider-counter"><span class="current-slide">1</span> / <span class="total-slides">' + totalSlides + '</span> <i class="fas fa-camera" style="margin-left: 5px; opacity: 0.8;"></i></div>');
        $sliderWrap.append($counterBox);


        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.attributeName === 'class') {
                    let $target = $(mutation.target);
                    if ($target.hasClass('flex-active-slide')) {
                        let slideNumber = $target.index() + 1;
                        $counterBox.find('.current-slide').text(slideNumber);
                    }
                }
            });
        });

        $slides.each(function() {
            observer.observe(this, { attributes: true, attributeFilter: ['class'] });
        });
    }

    // ====================================================================
    // RUNNER
    // ====================================================================
    function runCardVisualEnhancements() {
        fixCustomSVGIcons();
        reorderWCMetaElements();
        reorderAvailabilityFields();
        injectCardLocationsInstantly(); 
        injectPropertyIDs();
        addIconTooltips();
        formatCommonNotes();
        injectPhotoCounter();
    }

    // Run once immediately on page load
    runCardVisualEnhancements();

    // Run whenever an AJAX request completes (e.g. Load More, Pagination, Search)
    $(document).ajaxComplete(function(event, xhr, settings) {
        setTimeout(function() {
            runCardVisualEnhancements();
        }, 150);
    });

});