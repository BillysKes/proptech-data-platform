/* --- Module: 06-search-translations.js --- */
jQuery(document).ready(function($) {
    let pageUrl = decodeURIComponent(window.location.href).toLowerCase();
    let isEn = pageUrl.indexOf('/en/') !== -1;

    function translateCustomSearchFields() {
        if (!isEn) return; // Only process DOM sweeps if on the English page

        let fieldTranslations = {
            "Ελάχ. όροφος": "Min Floor", "Μέγ. όροφος": "Max Floor", "Ανελκυστήρας": "Elevator",
            "Μπαλκόνι": "Balcony", "Έτος κατασκευής μετά το": "Built after year", "Τελευταίος όροφος": "Top Floor",
            "WC": "WC", "Ύδρευση/Άρδευση": "Water Supply", "Μέγιστη απόσταση από παραλία": "Max distance from sea",
            "Μέγιστη τιμή αγοράς ανά τ.μ.": "Max price per sq.m.", "Απόδοση πάνω από": "Return over %",
            "Ελάχ. επίπλωση": "Min Furnishing", "Ελάχ. εξοπλισμός": "Min Equipment", "Εμβαδόν από τ.μ": "Min Area (sq.m.)",
            "Έως τ.μ": "Max Area (sq.m.)", "Σκοπός": "Status", "Κατηγορία": "Type", "Τοποθεσίες": "Locations",
            "Ελάχ. Τιμή": "Min Price", "Μέγ. Τιμή": "Max Price", "Ελάχ. Υπνοδωμάτια": "Min Beds",
            "Ελάχ. μπάνια/WC": "Min Baths", "Αναζήτηση": "Search", "Περισσότερα Φίλτρα": "More Filters",
            "2ο υπόγειο": "2nd Basement", "Υπόγειο": "Basement", "Ημιυπόγειο": "Semi-basement", "Ανώγειο": "Upper Ground",
            "Πατάρι": "Loft", "Ημιόροφος": "Mezzanine", "Ισόγειο": "Ground Floor", "1ος υπερυψ. όρ.": "Elevated 1st Floor",
            "1ος όρ.": "1st Floor", "2ος όρ.": "2nd Floor", "3ος όρ.": "3rd Floor", "4ος όρ.": "4th Floor",
            "5ος όρ.": "5th Floor", "6ος όρ.": "6th Floor", "7ος όρ.": "7th Floor", "8ος όρ.": "8th Floor",
            "9ος όρ.": "9th Floor", "10ος όρ.": "10th Floor", "11ος όρ.": "11th Floor", "12ος όρ.": "12th Floor",
            "13ος όρ.": "13th Floor", "14ος όρ.": "14th Floor", "15ος όρ.": "15th Floor", "σοφίτα": "Attic",
            "με ανελκ. από τον 1ο όρ.": "Elevator from 1st fl.", "με ανελκ. από τον 2ο όρ.": "Elevator from 2nd fl.",
            "με ανελκ. από τον 3ο όρ.": "Elevator from 3rd fl.", "με ανελκ. από τον 4ο όρ.": "Elevator from 4th fl.",
            "με ανελκ. από τον 5ο όρ.": "Elevator from 5th fl.", "Ναι": "Yes", "Όχι": "No", "Μεγάλο": "Large",
            "Ατομικό": "Private", "Ατομικό ή Κοινόχρηστο": "Private or Shared", "με αρδευτικό δίκτυο": "Irrigation network",
            "με γεώτρηση": "Borehole", "με ύδρευση": "Water supply", "Χωρίς επίπλωση": "Unfurnished",
            "Ημιεπιπλωμένο": "Semi-furnished", "Επιπλωμένο": "Furnished", "Χωρίς εξοπλισμό": "Un-equipped",
            "Ημιεξοπλισμένο": "Semi-equipped", "Εξοπλισμένο": "Equipped",
            "- Αγροτεμάχιο": "- Land",
            "- Εκθεσιακός Χώρος": "- Gallery",
            "- Κατάλυμα": "- Accommodation" , "- Συγκρότημα Κατοικιών" : "- Residence complex", "Επαγγελματικός Χώρος" : "Commercial property", "- Επαγγελματικός χώρος" : "- Commercial property"
        };

        $('.rhea_fields_labels, .rhea_fields_labels span.label, .rhea-option-bar label').each(function() {
            let $el = $(this);
            let text = $el.text().replace(/\s*\(\d+\)/g, '').trim(); 
            if (fieldTranslations[text]) {
                if ($el.children().length > 0) {
                    $el.contents().filter(function() { return this.nodeType === 3; }).each(function() { 
                        let cleanNodeText = this.nodeValue.trim();
                        if (cleanNodeText === text) {
                            this.nodeValue = " " + fieldTranslations[text] + " ";
                        }
                    });
                } else {
                    $el.text(fieldTranslations[text]);
                }
            }
        });

        // Translate Select Tags & Refresh UI
        $('select').each(function() {
            let $select = $(this);
            let selectModified = false;

            let title = $select.attr('title');
            if (title) {
                let cleanTitle = title.trim();
                if (fieldTranslations[cleanTitle]) {
                    $select.attr('title', fieldTranslations[cleanTitle]);
                    selectModified = true;
                } else if (cleanTitle === 'Αδιάφορο' || cleanTitle === 'Οποιοδήποτε') {
                    $select.attr('title', 'Any');
                    selectModified = true;
                } 
            }

            $select.find('option').each(function() {
                let optText = $(this).text().trim();
                if (fieldTranslations[optText]) {
                    $(this).text(fieldTranslations[optText]);
                    selectModified = true;
                } else if (optText === 'Αδιάφορο' || optText === 'Οποιοδήποτε') {
                    $(this).text('Any');
                    selectModified = true;
                }
            });

            if ($select.attr('data-count-selected-text') && $select.attr('data-count-selected-text').indexOf('Επιλεγμένες Τοποθεσίες') !== -1) {
                $select.attr('data-count-selected-text', '{0} Locations Selected');
                selectModified = true;
            }

            if (selectModified && $.fn.selectpicker && $select.closest('.bootstrap-select').length) {
                $select.selectpicker('refresh');
            }
        });
        
        $('.bootstrap-select .btn.dropdown-toggle').each(function() {
            let $btn = $(this);
            let btnTitle = $btn.attr('title');
            
            if (btnTitle) {
                let cleanBtnTitle = btnTitle.trim();
                if (fieldTranslations[cleanBtnTitle]) {
                    $btn.attr('title', fieldTranslations[cleanBtnTitle]);
                } else if (cleanBtnTitle === 'Αδιάφορο' || cleanBtnTitle === 'Οποιοδήποτε') {
                    $btn.attr('title', 'Any');
                }
            }

            let $innerSpan = $btn.find('.filter-option-inner-inner');
            if ($innerSpan.length > 0) {
                let innerText = $innerSpan.text().trim();
                if (fieldTranslations[innerText]) {
                    $innerSpan.text(fieldTranslations[innerText]);
                } else if (innerText === 'Αδιάφορο' || innerText === 'Οποιοδήποτε') {
                    $innerSpan.text('Any');
                } 
            }
        });

        // Translate Text Placeholders
        $('.rhea-text-field-wrapper input[type="text"]').each(function() {
            let placeholder = $(this).attr('placeholder');
            if (placeholder) {
                let cleanPlaceholder = placeholder.trim();
                if (fieldTranslations[cleanPlaceholder]) {
                    $(this).attr('placeholder', fieldTranslations[cleanPlaceholder]);
                } else if (cleanPlaceholder === 'Αδιάφορο') {
                    $(this).attr('placeholder', 'Any');
                }
            }
        });

        // Translate Expanders & Buttons
        $('.rhea_advanced_expander span, .rhea_search_form_button span').each(function() {
            let text = $(this).text().trim();
            if (fieldTranslations[text] && $(this).children().length === 0) {
                $(this).text(fieldTranslations[text]);
            }
        });

        let $moreFeatures = $('.rhea_open_more_features');
        if ($moreFeatures.length && $moreFeatures.text().indexOf('Ψάχνετε για συγκεκριμένα χαρακτηριστικά') !== -1) {
            $moreFeatures.html('<span></span>Looking for specific features?');
        }
    }

    if (isEn) {
        translateCustomSearchFields();
        setTimeout(translateCustomSearchFields, 100);
        setTimeout(translateCustomSearchFields, 500);
        setTimeout(translateCustomSearchFields, 1500);
        $(document).ajaxComplete(function() { setTimeout(translateCustomSearchFields, 50); });
    }

    // --- Search Not Found Notification ---
    if (window.location.search.indexOf('not_found=1') !== -1) {
        let msg = isEn ? 'No property found with this code. Please check the code and try again.' : 'Δεν βρέθηκε ακίνητο με αυτόν τον κωδικό. Παρακαλώ ελέγξτε τον κωδικό και δοκιμάστε ξανά.';
        alert(msg);
        let cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
        window.history.replaceState({path: cleanUrl}, '', cleanUrl);
    }
});