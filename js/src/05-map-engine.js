/* --- Module: 05-map-engine.js --- */
(function($) {
    if (window.rhMapMasterFixV9Active) return;
    window.rhMapMasterFixV9Active = true;

    window.rhCapturedMap = null;
    let lastHoveredId = null;
    let activeSearchPolygons = []; 

    let mapCaptureInterval = setInterval(function() {
        if (typeof window.L !== 'undefined' && window.L.Map) {
            
            // 1. Capture the Map Instance
            if (!window.L.Map.prototype._rhInitPatched) {
                let origInit = window.L.Map.prototype.initialize;
                window.L.Map.prototype.initialize = function() {
                    origInit.apply(this, arguments);
                    window.rhCapturedMap = this;
                };
                window.L.Map.prototype._rhInitPatched = true;
            }

            // 2. Hook into frequent methods to maintain capture
            if (!window.L.Map.prototype._rhFrequentPatched) {
                let frequentMethods = ['getSize', 'getCenter', 'invalidateSize', 'getZoom', 'fitBounds', 'flyTo', 'panTo'];
                frequentMethods.forEach(function(method) {
                    let orig = window.L.Map.prototype[method];
                    if (orig) {
                        window.L.Map.prototype[method] = function() {
                            window.rhCapturedMap = this; 
                            return orig.apply(this, arguments);
                        };
                    }
                });
                window.L.Map.prototype._rhFrequentPatched = true;
            }

            // ====================================================================
            // CLUSTER OVERRIDES
            // ====================================================================
            if (window.L.MarkerClusterGroup && !window.L.MarkerClusterGroup.prototype._rhSpiderGroupPatched) {
                window.L.MarkerClusterGroup.prototype.spiderfy = function() {
                    var bounds = this.getBounds();
                    if (bounds && this._map) {
                        this._map.fitBounds(bounds, { padding: [40, 40], maxZoom: 18 });
                    }
                };
                window.L.MarkerClusterGroup.prototype._rhSpiderGroupPatched = true;
            }

            if (window.L.MarkerCluster && !window.L.MarkerCluster.prototype._rhSpiderPatched) {
                window.L.MarkerCluster.prototype.spiderfy = function() {
                    if (this._group && this._group._map) {
                        this._group._map.flyTo(this.getLatLng(), 16, { animate: true, duration: 0.5 });
                    }
                };
                window.L.MarkerCluster.prototype._rhSpiderPatched = true;
            }
            
            if (window.L.MarkerClusterGroup && !window.L.MarkerClusterGroup.prototype._rhZoomPatched) {
                window.L.MarkerClusterGroup.prototype.zoomToShowLayer = function(marker, callback) {
                    if (this._map && marker && marker.getLatLng) {
                        this._map.flyTo(marker.getLatLng(), 16, { animate: true, duration: 0.5 });
                    }
                    if (callback) callback();
                };
                window.L.MarkerClusterGroup.prototype._rhZoomPatched = true;
            }

            // Force fake resize
            if (!window.rhCapturedMap) {
                window.dispatchEvent(new Event('resize'));
            } else {
                clearInterval(mapCaptureInterval);
            }
        }
    }, 100);

    // ====================================================================
    // PROPERTY CARD HOVER EFFECT
    // ====================================================================
    $(document).on('mouseenter', '.rh_list_card, .rh_prop_card, .rhea_property_card', function() {
        let $card = $(this);
        let rhId = $card.attr('data-rh-id');
        if (rhId && !$card.attr('data-property-id')) $card.attr('data-property-id', rhId.replace(/\D/g, ''));
        
        let propId = $card.attr('data-property-id');
        
        // Prevent spamming the animation if holding the mouse on the same card
        if (propId && propId !== lastHoveredId && window.rhCapturedMap && typeof propertiesMapData !== 'undefined') {
            lastHoveredId = propId;
            let propertyData = propertiesMapData.find(p => p.id == propId || p.property_id == propId || p.ID == propId);
            
            if (propertyData && propertyData.lat && propertyData.lng) {
                let latLng = [parseFloat(propertyData.lat), parseFloat(propertyData.lng)];
                let currentZoom = window.rhCapturedMap.getZoom();
                let targetZoom = Math.max(currentZoom, 15);
                
                // Flag to prevent double triggering
                let popupTriggered = false;
                
                let triggerNativePopup = function() {
                    if (!popupTriggered) {
                        popupTriggered = true;
                        // Add a tiny delay to let the map finish rendering the un-clustered pin
                        setTimeout(function() {
                            // Only trigger the popup if the user hasn't moved their mouse away
                            if ($card.is(':hover')) {
                                $card.trigger('mouseenter');
                            }
                        }, 50);
                    }
                };

                // Listen for the map finishing its flight to the marker
                window.rhCapturedMap.once('moveend', triggerNativePopup);
                
                window.rhCapturedMap.flyTo(latLng, targetZoom, { animate: true, duration: 0.5 });
                
                setTimeout(triggerNativePopup, 600);
            }
        }
    });

    $(document).on('mouseleave', '.rh_list_card, .rh_prop_card, .rhea_property_card', function() {
        lastHoveredId = null; // Reset on mouse leave so they can trigger it again
    });

    // ====================================================================
    // SINGLE PROPERTY POLYGON INJECTION
    // ====================================================================
    let polygonInterval = setInterval(function() {
        let mapObj = window.rhCapturedMap;
        
        if (!mapObj && window.L) {
            $('.leaflet-container').each(function() {
                if (window.propertyMap) mapObj = window.propertyMap;
            });
        }

        if (mapObj && window.L && window.rhActivePropertyPolygon) {
            mapObj.eachLayer(function(layer) {
                if (layer instanceof window.L.Circle || layer instanceof window.L.CircleMarker || layer instanceof window.L.Marker) {
                    mapObj.removeLayer(layer);
                }
            });

            try {
                let poly = window.L.polygon(window.rhActivePropertyPolygon, {
                    color: '#1ea69a', 
                    weight: 2,
                    fillColor: '#ea723d', 
                    fillOpacity: 0.3
                }).addTo(mapObj);

                mapObj.fitBounds(poly.getBounds(), { padding: [20, 20], maxZoom: 16 });
            } catch(e) {}

            clearInterval(polygonInterval);
        }
    }, 500);
    
    setTimeout(function() { clearInterval(polygonInterval); }, 10000);

    // ====================================================================
    // MULTIPLE POLYGONS
    // ====================================================================
    function drawSearchPolygons(polygonsArray, mapObj, flyToBounds = false) {
        if (!mapObj || !window.L || !polygonsArray || polygonsArray.length === 0) return;
        
        let boundsGroup = window.L.featureGroup();
        
        polygonsArray.forEach(function(polyData) {
            try {
                let polyLayer = window.L.polygon(polyData, {
                    color: '#1a73e8',
                    weight: 2,
                    fillColor: '#1a73e8',   
                    fillOpacity: 0.08,      
                    interactive: false
                });
                polyLayer.addTo(mapObj);
                activeSearchPolygons.push(polyLayer);
                boundsGroup.addLayer(polyLayer);
            } catch(e) {}
        });

        if (flyToBounds && boundsGroup.getLayers().length > 0) {
            mapObj.flyToBounds(boundsGroup.getBounds(), { padding: [30, 30], maxZoom: 15, duration: 0.6 });
        }
    }

    let searchPolyInterval = setInterval(function() {
        let mapObj = window.rhCapturedMap;
        if (!mapObj && window.L) {
            $('.leaflet-container').each(function() { if (window.propertyMap) mapObj = window.propertyMap; });
        }

        if (mapObj && window.L && window.rhSearchedLocationPolygons) {
            drawSearchPolygons(window.rhSearchedLocationPolygons, mapObj, false);
            clearInterval(searchPolyInterval);
        }
    }, 500);
    setTimeout(function() { clearInterval(searchPolyInterval); }, 10000);

    // Live Dropdown Listener
    $(document).on('change', 'select[name="location[]"], select[name="location"], select[name="property_city"]', function() {
        let mapObj = window.rhCapturedMap;
        if (!mapObj && window.propertyMap) mapObj = window.propertyMap;
        if (!mapObj || !window.L) return;

        let selectedSlugs = $(this).val();
        if (!Array.isArray(selectedSlugs)) selectedSlugs = selectedSlugs ? [selectedSlugs] : [];
        
        selectedSlugs = selectedSlugs.filter(slug => slug && slug !== 'any');

        activeSearchPolygons.forEach(layer => mapObj.removeLayer(layer));
        activeSearchPolygons = [];

        if (selectedSlugs.length === 0) return;

        let polygonsToDraw = [];
        
        if (window.rhAllLocationPolygons) {
            selectedSlugs.forEach(function(slug) {
                if (window.rhAllLocationPolygons[slug]) {
                    polygonsToDraw.push(window.rhAllLocationPolygons[slug]);
                }
            });
        }

        if (polygonsToDraw.length > 0) {
            drawSearchPolygons(polygonsToDraw, mapObj, true);
        }
    });

})(jQuery);