/* --- Module: 02-inquiry-toggles.js --- */
jQuery(document).ready(function($) {
    let pageUrl = decodeURIComponent(window.location.href).toLowerCase();
    
    let isDemandPage = pageUrl.indexOf('/property-request') !== -1 || 
                       (pageUrl.indexOf('ζήτηση') !== -1 && pageUrl.indexOf('αναζήτηση') === -1);
                       
    if (!isDemandPage) return;

    let isEn = pageUrl.indexOf('/en/') !== -1;

    // Dynamic Translations
    let textDesired = isEn ? 'Desired' : 'Επιθυμητό';
    let textRequired = isEn ? 'Required' : 'Απαραίτητο';

    if (!$('#rh-req-toggle-css').length) {
        $('head').append(`
            <style id="rh-req-toggle-css">
                .rh-req-toggle { display: inline-block; margin-right: 6px; padding: 3px 8px; font-size: 11px !important; font-weight: 600; border-radius: 4px; background: #f4f8fb; border: 1px solid #7cb3d8; color: #1a73e8; cursor: pointer; transition: all 0.2s ease; line-height: 1.2; user-select: none; flex-shrink: 0; }
                .rh-req-toggle[data-state="required"] { background: #fff8e1; border: 1px solid #ffb300; color: #e67e22; }
                .rh-req-toggle:hover { filter: brightness(0.95); }
                .rhea-inquiry-field label, .rhea-option-bar label { display: flex !important; align-items: center; flex-wrap: wrap; gap: 6px; width: 100%; }
                .rhea-inquiry-field input[type="checkbox"], .rhea-inquiry-field input[type="radio"] { margin: 0 !important; flex-shrink: 0; }
            </style>
        `);
    }

    function injectToggles() {
        $('.rhea-inquiry-form .rhea-inquiry-field, .rhea-inquiry-form .rhea-option-bar').each(function() {
            let $field = $(this);
            let excludeNames = ['name', 'email', 'number', 'message', 'gdpr_accept', 'property_type', 'property_status','property_city','property_city_lvl_1','property_city_lvl_2','property_city_lvl_3[]'];
            let shouldExclude = false;
            
            excludeNames.forEach(name => {
                if ($field.find(`input[name="${name}"], textarea[name="${name}"], select[name="${name}"]`).length > 0) shouldExclude = true;
            });

            if (shouldExclude || $field.find('.rh-req-toggle').length > 0) return; 

            let $btn = $('<button type="button" class="rh-req-toggle" data-state="desired">' + textDesired + '</button>');
            let $label = $field.find('label.rhea-custom-field-label').length ? $field.find('label.rhea-custom-field-label') : $field.find('label').first();
            
            if ($label.length > 0) {
                $label.prepend($btn);
                
                $btn.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if ($(this).attr('data-state') === 'desired') {
                        $(this).attr('data-state', 'required').text(textRequired);
                    } else {
                        $(this).attr('data-state', 'desired').text(textDesired);
                    }
                });
            }
        });
    }

    injectToggles();
});
