<?php
//*********************************************************
// realhomes-functions.php
//*********************************************************

function RealHomes_Struct($CatID, $ad) {
    $category_map = [1 => 'Κατοικία', 2 => 'Επαγγελματικός Χώρος', 3 => 'Γη'];
    $category_map_en = [1 => 'Residential', 2 => 'Commercial', 3 => 'Land'];
    
    $FURNISHED = array(-1=>'Χωρίς επίπλωση', 0=>'', 1=>'Ημιεπιπλωμένο', 2=>'Επιπλωμένο');
    $FURNISHED_EN = array(-1=>'Unfurnished', 0=>'', 1=>'Semi-furnished', 2=>'Furnished');
    
    $EQUIPPED = array(-1=>'Χωρίς εξοπλισμό', 0=>'', 1=>'Ημιεξοπλισμένο', 2=>'Εξοπλισμένο');
    $EQUIPPED_EN = array(-1=>'Un-equipped', 0=>'', 1=>'Semi-equipped', 2=>'Equipped');

    $mid = $ad['MID'];
    
    // Statuses
    $status_el = []; $status_en = [];
    if ($ad['Purpose'] == 1) { $status_el[] = 'Πώληση'; $status_en[] = 'Sale'; }
    else if ($ad['Purpose'] == 2) { $status_el[] = 'Ενοικίαση'; $status_en[] = 'Rent'; }
    else { $status_el[] = 'Αντιπαροχή'; $status_en[] = 'Exchange'; }

    // Photos
    $photos_array = [];
    $raw_photos = [];
    if (!empty($ad['Photos']) && $ad['Photos'] !== '0') $raw_photos = array_merge($raw_photos, explode(',', trim($ad['Photos'], ',')));
    if (!empty($ad['BuildingPhotos']) && $ad['BuildingPhotos'] !== '0') $raw_photos = array_merge($raw_photos, explode(',', trim($ad['BuildingPhotos'], ',')));
    $raw_photos = array_unique(array_filter(array_map('trim', $raw_photos)));

    foreach ($raw_photos as $filename) {
        if (!empty($filename) && strpos($filename, '-') === false) { 
            $photo_path = FullPhotoPath($filename);                        
            $public_url = str_replace('legacy-crm.com', 'thessaloniki-realestate', $photo_path) . 'l.jpg';
            $photos_array[] = $public_url;
        }
    }

    $features_el = []; $features_en = [];
    $additional_details_el = []; $additional_details_en = [];
    $floor_value_el = ''; $floor_value_en = ''; 

    // Category Specifics
    if ($CatID != 3) {
        if (($ad['Floor']<0) && (!is_null($ad['Floors'])) && (strpos($ad['Floors'],',')!==false)) {$fl=explode(',',$ad['Floors']); $f=$fl[1];}
        else {$f=$ad['Floor'];}
        $f = number_format((float)$f, 1, '.', '');
        
        if (isset(FLOORS[$f])) $floor_value_el = FLOORS[$f];
        if (isset(FLOORS_EN[$f])) $floor_value_en = FLOORS_EN[$f];
        
        if ( ($ad['Purpose'] == 1) && isset($ad['Returnpc']) && $ad['Returnpc'] != 0) {
            $additional_details_el['Απόδοση %'] = $ad['Returnpc'];
            $additional_details_en['Return %'] = $ad['Returnpc'];
        }
        if (isset($ad['TStatusID']) && ($ad['TStatusID']>0)) {
            $additional_details_el['Διαθεσιμότητα'] = P_AVAILABILITY[$ad['TStatusID']];
            $additional_details_en['Availability'] = P_AVAILABILITY_EN[$ad['TStatusID']];
        }
        if ($ad['Tent']==1) { $additional_details_el['Τέντες']='Ναι'; $additional_details_en['Awnings']='Yes'; }
        if ($ad['Screens']==1) { $additional_details_el['Σίτες']='Ναι'; $additional_details_en['Insect Screens']='Yes'; }

        if ((isset($ad['LiftStop']) && $ad['LiftStop'] < 0) || (isset($ad['Lift']) && $ad['Lift'] <= 0 && (empty($ad['LiftStop']) || $ad['LiftStop'] == 0)) ) 
            {
            $additional_details_el['Ανελκυστήρας']='Όχι'; 
            $additional_details_en['Elevator']='No'; 
            } 
        else 
            {
            $additional_details_el['Ανελκυστήρας']='Ναι'; 
            $additional_details_en['Elevator']='Yes'; 
            }

        }
        
    // General Meta Mappings (Greek & English)
    if (!empty($ad['PCondition']) && isset(CONDITION[$ad['PCondition']])) { $additional_details_el['Κατάσταση'] = CONDITION[$ad['PCondition']]; $additional_details_en['Condition'] = CONDITION_EN[$ad['PCondition']]; }
    if (!empty($ad['HeatingID']) && isset(HEATING[$ad['HeatingID']]) && HEATING[$ad['HeatingID']] !== 'Όχι') { $additional_details_el['Τρόπος Θέρμανσης'] = HEATING[$ad['HeatingID']]; $additional_details_en['Heating System'] = HEATING_EN[$ad['HeatingID']]; }
    if (isset($ad['HeatingT']) && ($ad['HeatingT']>0)) { $additional_details_el['Εγκατ. Θέρμανσης']=HEATING_TYPE[$ad['HeatingT']]; $additional_details_en['Heating Type']=HEATING_TYPE_EN[$ad['HeatingT']]; }
    if (isset($ad['RadiatorID']) && ($ad['RadiatorID']==9)) { $additional_details_el['Σώματα']=RADIATOR[$ad['RadiatorID']]; $additional_details_en['Radiators']=RADIATOR_EN[$ad['RadiatorID']]; }
    if (isset($ad['SecurityDoor']) && $ad['SecurityDoor'] == 1) { $additional_details_el['Εξώπορτα'] = SECURITY_DOOR[$ad['SecurityDoor']]; $additional_details_en['Security Door'] = SECURITY_DOOR_EN[$ad['SecurityDoor']]; }
    if (!empty($ad['WindowFrames']) && isset(WINDOW_FRAMES[$ad['WindowFrames']])) { $additional_details_el['Κουφώματα'] = WINDOW_FRAMES[$ad['WindowFrames']]; $additional_details_en['Window Frames'] = WINDOW_FRAMES_EN[$ad['WindowFrames']]; }
    if (isset($ad['Glazing']) && $ad['Glazing']>0) { $additional_details_el['Υαλοπίνακες']=GLAZING[$ad['Glazing']]; $additional_details_en['Glazing']=GLAZING_EN[$ad['Glazing']]; }
    if (!empty($ad['FloorsK']) && isset(FLOORS_K[$ad['FloorsK']])) { $additional_details_el['Πατώματα'] = FLOORS_K[$ad['FloorsK']]; $additional_details_en['Floors'] = FLOORS_K_EN[$ad['FloorsK']]; }
    if (!empty($ad['Zone']) && isset(ZONE[$ad['Zone']])) { $additional_details_el['Ζώνη'] = ZONE[$ad['Zone']]; $additional_details_en['Zone'] = ZONE_EN[$ad['Zone']]; }
    if (!empty($ad['Slope']) && isset(SLOPE[$ad['Slope']])) { $additional_details_el['Κλίση εδάφους'] = SLOPE[$ad['Slope']]; $additional_details_en['Slope'] = SLOPE_EN[$ad['Slope']]; }
    if (!empty($ad['View']) && isset(VIEW[$ad['View']]) && VIEW[$ad['View']] !== 'Όχι') { $additional_details_el['Θέα'] = (VIEW[$ad['View']] === 'Ναι') ? 'Απεριόριστη' : VIEW[$ad['View']]; $additional_details_en['View'] = (VIEW_EN[$ad['View']] === 'Yes') ? 'Unrestricted' : VIEW_EN[$ad['View']]; }
    if (!empty($ad['RoadStatus']) && isset(ROAD_STATUS[$ad['RoadStatus']])) { $additional_details_el['Δρόμος'] = ROAD_STATUS[$ad['RoadStatus']]; $additional_details_en['Road'] = ROAD_STATUS_EN[$ad['RoadStatus']]; }
    if (isset($ad['BeachDist']) && (!is_null($ad['BeachDist']))) { $additional_details_el['Απόσταση από Θάλασσα']= $ad['BeachDist'].' μ.'; $additional_details_en['Distance from Sea']= $ad['BeachDist'].' m.'; }
    if (isset($ad['LastFloor']) && (!is_null($ad['LastFloor'])) && $ad['LastFloor'] == 1 ) { $additional_details_el['Τελευταίος Όροφος'] = 'Ναι'; $additional_details_en['Last Floor'] = 'Yes'; }
    else if ((isset($ad['LastFloor']) && (!is_null($ad['LastFloor'])) && $ad['LastFloor'] == -1 )) { $additional_details_el['Τελευταίος Όροφος'] = 'Όχι'; $additional_details_en['Last Floor'] = 'No'; }
    if (($ad['AvailabilityDate']!='0000-00-00') && ($ad['AvailabilityDate']>=date('Y-m-d'))) { $additional_details_el['Ελεύθερο από']=date(DATE_FORMAT,strtotime($ad['AvailabilityDate'])); $additional_details_en['Available from']=date(DATE_FORMAT,strtotime($ad['AvailabilityDate'])); }
    if ($ad['Zone']>0) { $additional_details_el['Σχέδιο πόλης']=CITY_PLAN[$ad['CityPlanID']]; $additional_details_en['City Plan']=CITY_PLAN_EN[$ad['CityPlanID']]; }
    
    if (isset($ad['Only1'])) {
        if ($ad['Only1']==1) { $additional_details_el['Οροφοδιαμέρισμα']='Ναι'; $additional_details_en['Whole-floor Apt']='Yes'; }
    } else {
        if (($ad['Only1']==-1)) { $additional_details_el['Οροφοδιαμέρισμα']='Όχι'; $additional_details_en['Whole-floor Apt']='No'; }
    }

    // Explicit Feature Flags
    if (!empty($ad['Corner'])) { $features_el[] = 'Γωνιακό'; $features_en[] = 'Corner'; }
    if (isset($ad['Fireplace']) &&$ad['Fireplace'] > 0) { $features_el[] = 'με τζάκι'; $features_en[] = 'with fireplace'; }
    if (isset($ad['Penthouse']) && $ad['Penthouse'] > 0 &&$ad['Penthouse'] < 9) { $features_el[] = 'ρετιρέ'; $features_en[] = 'penthouse'; }
    if (isset($ad['Bright']) &&$ad['Bright'] == 1) { $features_el[] = 'φωτεινό'; $features_en[] = 'bright'; }
    if (isset($ad['Diaberes']) &&$ad['Diaberes'] == 1) { $features_el[] = 'διαμπερές'; $features_en[] = 'airy'; }
    if ((isset($ad['Returnpc']) && ($ad['Returnpc']>=6)) || ((in_array(2,SUITABLE)))) { $features_el[]= 'επενδυτικό'; $features_en[]= 'investment'; }

    $suitable_ids = !empty($ad['Suitable']) ? array_map('intval', array_map('trim', explode(',', $ad['Suitable']))) : [];   

    if ((isset($ad['Returnpc']) && ($ad['Returnpc'] >= 6)) || in_array(2, $suitable_ids)) {$features_el[] = 'επενδυτικό'; 
        $features_en[] = 'investment'; 
    }

    foreach ($suitable_ids as$id) {
        if ($id === 2) {continue; }
        if (isset(SUITABLE[$id])) { 
            $features_el[] = SUITABLE[$id]; 
            $features_en[] = SUITABLE_EN[$id]; 
        }
    }

    if ($CatID == 1) {
        if (isset($ad['Pool']) &&$ad['Pool'] == 1) { $features_el[] = 'με πισίνα'; $features_en[] = 'with swimming Pool'; }
        if (!empty($ad['Parking']) &&$ad['Parking'] > 0) { $features_el[] = 'με χώρο στάθμευσης'; $features_en[] = 'Parking Space'; }
        if (isset($ad['Pets']) &&$ad['Pets'] == 1) { $features_el[] = 'επιτρ. κατοικίδια'; $features_en[] = 'pets Allowed'; }
        if (isset($ad['Balcony']) && ($ad['Balcony']>0)) { $additional_details_el['Μπαλκόνι']=BALCONY[$ad['Balcony']]; $additional_details_en['Balcony']=BALCONY_EN[$ad['Balcony']]; }
        if (isset($ad['Furnished']) && ($ad['Furnished'] != 0) ) { $additional_details_el['Επίπλωση']=$FURNISHED[$ad['Furnished']];$additional_details_en['Furnished']=$FURNISHED_EN[$ad['Furnished']]; }
        if (isset($ad['Equipped']) && ($ad['Equipped'] != 0) ) { $additional_details_el['Συσκευές']=$EQUIPPED[$ad['Equipped']];$additional_details_en['Equipment']=$EQUIPPED_EN[$ad['Equipped']]; }
        if (isset($ad['Garden']) && ($ad['Garden']==1)) { $features_el[]='με αυλή'; $features_en[]='Yard/Garden'; }
    }
    if ($CatID == 2) {
        if (isset($ad['HealthInterest']) &&$ad['HealthInterest'] == 1) { $features_el[] = 'υγειονομικού ενδιαφέροντος'; $features_en[] = 'Health Interest'; }
        if (isset($ad['Chimney']) &&$ad['Chimney'] == 1) { $features_el[] = 'με φουγάρο'; $features_en[] = 'with chimney'; }
        if (isset($ad['CommercialRoad']) &&$ad['CommercialRoad'] == 1) { $features_el[] = 'σε εμπορικό δρόμο'; $features_en[] = 'in commercial road'; }
        if (!empty($ad['WC']) &&$ad['WCType'] >0 ) { $additional_details_el['WC'] = WC_TYPE[$ad['WCType']]; $additional_details_en['WC'] = WC_TYPE_EN[$ad['WCType']]; }
        if (isset($ad['Nuisance']) && ($ad['Nuisance'] != 0) ) { $additional_details_el['Δραστηριότητες']=NUISANCE[$ad['Nuisance']]; $additional_details_en['Nuisance']=NUISANCE_EN[$ad['Nuisance']]; }
    }
    if ($CatID == 3) {
        if (isset($ad['Buildable']) &&$ad['Buildable'] == 1) { $features_el[] = 'οικοδομήσιμο'; $features_en[] = 'buildable'; }
        if (isset($ad['Electricity']) &&$ad['Electricity'] != 0) 
            {
            $additional_details_el['Ηλεκτρικό'] = ELECTRICITY[$ad['Electricity']]; $additional_details_en['Electric'] =  ELECTRICITY_EN[$ad['Electricity']]; 
            if($ad['Electricity'] > 0) { $features_el[] = 'με ηλεκτρικό ρεύμα'; $features_en[] = 'with electricity';}
            }

        if ((isset($ad['Water']) &&$ad['Water']>0)) { $additional_details_el['Ύδρευση/Άρδευση'] = WATER[$ad['Water']]; $additional_details_en['Water Supply'] = WATER_EN[$ad['Water']]; }
        if (in_array($ad['View'],array(1,2,3,4,5,6,7))) { $features_el[]='με Θέα'; $features_en[]='with view'; }
        if ((isset($ad['Fence']) &&$ad['Fence']!=0)) { $additional_details_el['Περίφραξη'] = FENCE[$ad['Fence']]; $additional_details_en['Fenced'] = FENCE_EN[$ad['Fence']]; }
        if (isset($ad['Availability']) && ($ad['Availability']>0)) { $additional_details_el['Διαθεσιμότητα']=L_AVAILABILITY[$ad['Availability']]; $additional_details_en['Availability']=L_AVAILABILITY_EN[$ad['Availability']]; }
        if ($ad['Domisi']>0) { $additional_details_el['Συντελ. Δόμησης']=(float) $ad['Domisi']; $additional_details_en['Building Coefficient']=(float)$ad['Domisi']; }
        if ($ad['FacadeLength']>0) { $additional_details_el['Μήκος πρόσοψης']=(int) $ad['FacadeLength'].' μ.'; $additional_details_en['Facade Length']=(int)$ad['FacadeLength'].' m.'; }
    }

    if (isset($ad['ConstructionType']) && ($ad['ConstructionType']>0)) { $additional_details_el['Είδος κατασκευής']=CONSTRUCTION_TYPE[$ad['ConstructionType']]; $additional_details_en['Construction Type']=CONSTRUCTION_TYPE_EN[$ad['ConstructionType']]; }
    if (isset($ad['Electric']) && ($ad['Electric']>0)) { $additional_details_el['Ηλεκτρικό']=ELECTRIC[$ad['Electric']]; $additional_details_en['Electricity']=ELECTRIC_EN[$ad['Electric']]; }
    if (isset($ad['Alarm']) && ($ad['Alarm']>0)) { $additional_details_el['Συναγερμός']=ALARM[$ad['Alarm']]; $additional_details_en['Alarm']=ALARM_EN[$ad['Alarm']]; }
    if (isset($ad['Cool']) && ($ad['Cool']>0)) { $additional_details_el['Ψύξη']=COOL[$ad['Cool']]; $additional_details_en['Cooling']=COOL_EN[$ad['Cool']]; }
    if (isset($ad['ForDisabled']) && ($ad['ForDisabled']>0)) { $additional_details_el['Προσβάσιμο για ΑΜΕΑ']='Ναι'; $additional_details_en['Wheelchair Accessible']='Yes'; }
    if (isset($ad['Shared']) && (!is_null($ad['Shared']))) { $additional_details_el['Κοινόχρηστα']=(int) $ad['Shared'].' €/μήνα'; $additional_details_en['Shared Expenses']=(int)$ad['Shared'].' €/month'; }
    if (isset($ad['RenovationY']) && ($ad['RenovationY']>1900)) { $additional_details_el['Έτος ανακαίνισης']=(int) $ad['RenovationY']; $additional_details_en['Renovation Year']=(int)$ad['RenovationY']; }

    $PS = Postcripts(1, 'OnWebsite',$ad['AdPS'], "\n");
    if (!empty($PS)) { $additional_details_el['Common_note'] =$PS; $additional_details_en['Common_note'] =$PS; }


    $matched_services_el = [];$matched_services_en = [];
    $Included = array_map('trim', explode(',',$ad['Included']));
    if (isset(INCLUDED_SERVICES[$ad['Purpose']])) {
        foreach ([7, 9, 11, 15] as $service_id) {
            if (in_array((string)$service_id,$Included, true) && isset(INCLUDED_SERVICES[$ad['Purpose']][$service_id])) {
                $matched_services_el[] = INCLUDED_SERVICES[$ad['Purpose']][$service_id];$matched_services_en[] = INCLUDED_SERVICES_EN[$ad['Purpose']][$service_id];
            }
        }
    }

    if (!empty($matched_services_el)) {
        $additional_details_el['Περιλαμβάνει έξοδα για'] = implode(', ', $matched_services_el);
        $additional_details_en['Includes expenses for'] = implode(', ',$matched_services_en);
    }

    if ($ad['Purpose'] != 4)
        $price = !empty($ad['Price']) ? $ad['Price'] : (!empty($ad['Rent']) ? $ad['Rent'] : null);$area = (int) round(max($ad['Area'],$ad['GrossArea']));
    
    // Dual Descriptions
    $description_el =$ad['Ad'];
    $description_en = !empty($ad['AdEN']) ? $ad['AdEN'] :$ad['Ad'];
    
    $clean_year = (isset($ad['BuildY']) && $ad['BuildY'] > 0) ?$ad['BuildY'] : '';

    $PseudoCoordinates = (!empty($ad['PseudoCoordinates'])) ? @unserialize($ad['PseudoCoordinates']) : [];
    $AddressGR = '';$AddressEN = ''; $Latitude = '';$Longitude = '';

    $actual_lat = (!empty($ad['Latitude'])) ? (float) $ad['Latitude'] : '';$actual_lng = (!empty($ad['Longitude'])) ? (float)$ad['Longitude'] : '';

    $display_mode = 'address';
    if (empty($ad['ShowAddr'])) {
        if (!empty($ad['ShowPlace']))$display_mode = 'place';
        else $display_mode = 'dimos';
    }

    if (!empty($ad['ShowAddr'])) {
        $AddressGR = trim(($ad['Address'] ?? '') . ' ' . ($ad['AddressNo'] ?? ''));$AddressEN = trim(($ad['AddressEN'] ?? '') . ' ' . ($ad['AddressNo'] ?? ''));
        if (empty($AddressEN)) $AddressEN =$AddressGR;
        
        $Latitude  =$ad['Latitude'] ?? '';
        $Longitude =$ad['Longitude'] ?? '';
    } elseif (!empty($ad['ShowPlace'])) {$AddressGR = (!empty($PseudoCoordinates['Title'])) ? strtok($PseudoCoordinates['Title'], ',') : trim($ad['PlaceEL'] ?? '');$AddressEN = trim($ad['PlaceEN'] ?? $AddressGR);
        
        if (!empty($ad['Longitude']) &&$ad['Longitude'] != 0) {
            if (!empty($PseudoCoordinates['Lng'])) {
                $Longitude =$PseudoCoordinates['Lng']; $Latitude  =$PseudoCoordinates['Lat'];
            } else {
                $Longitude = (float) round($ad['Longitude'] + (mt_rand(0,1)*2-1)*mt_rand(70,110)/100000, 6);$Latitude  = (float) round($ad['Latitude'] + (mt_rand(0,1)*2-1)*mt_rand(70,110)/100000, 6);
            }
        } elseif (!empty($ad['PlaceBounds'])) {
            $Bounds = explode(',',$ad['PlaceBounds']);
            if (count($Bounds) >= 4) {
                $Longitude = (float) round(($Bounds[1] + $Bounds[3]) / 2, 6);$Latitude  = (float) round(($Bounds[0] +$Bounds[2]) / 2, 6);
            }
        }
    } elseif (!empty($ad['ShowDimos'])) {$AddressGR = (!empty($ad['DimosEL'])) ? 'Δήμος ' . trim($ad['DimosEL']) : '';
        $AddressEN = (!empty($ad['DimosEN'])) ? 'Municipality of ' . trim($ad['DimosEN']) :$AddressGR;
        
        if (!empty($ad['Longitude']) &&$ad['Longitude'] != 0) {
            if (!empty($PseudoCoordinates['Lng'])) {
                $Longitude =$PseudoCoordinates['Lng']; $Latitude  =$PseudoCoordinates['Lat'];
            } else {
                $Longitude = (float) round($ad['Longitude'] + (mt_rand(0,1)*2-1)*mt_rand(350,500)/100000, 6);$Latitude  = (float) round($ad['Latitude'] + (mt_rand(0,1)*2-1)*mt_rand(350,500)/100000, 6);
            }
        }
    } else {$AddressGR = (!empty($ad['DimosEL'])) ? 'Δήμος ' . trim($ad['DimosEL']) : '';
        $AddressEN = (!empty($ad['DimosEN'])) ? 'Municipality of ' . trim($ad['DimosEN']) :$AddressGR;
        
        if (!empty($ad['Longitude']) &&$ad['Longitude'] != 0) {
            $Longitude = (float) round($ad['Longitude'] + (mt_rand(0,1)*2-1)*mt_rand(7000,10000)/100000, 6);
            $Latitude  = (float) round($ad['Latitude'] + (mt_rand(0,1)*2-1)*mt_rand(7000,10000)/100000, 6);
        }
    }

    // Locations Greek
    $location_hierarchy_el = [];
    if (!empty($ad['PeriferiaEL'])) $location_hierarchy_el[] = 'Περιφέρεια ' . trim($ad['PeriferiaEL']);
    $dimos_name_raw_el = trim($ad['DimosEL']);
    if (!empty($dimos_name_raw_el)) {
        $location_hierarchy_el[] = (strpos($dimos_name_raw_el, 'Δήμος') === false) ? 'Δήμος ' . $dimos_name_raw_el :$dimos_name_raw_el;
    }
    if (!empty($ad['PlaceEL'])) {
        $area_name_el = strip_tags(trim($ad['PlaceEL']));
        if (!empty($dimos_name_raw_el)) $area_name_el .= ', Δ. ' . str_replace('Δήμος ', '', $dimos_name_raw_el);
        $location_hierarchy_el[] =$area_name_el;
    }

    // Locations English
    $location_hierarchy_en = [];
    if (!empty($ad['PeriferiaEN'])) $location_hierarchy_en[] = trim($ad['PeriferiaEN']);
    $dimos_name_raw_en = trim($ad['DimosEN']);
    if (!empty($dimos_name_raw_en)) {
        $location_hierarchy_en[] = (strpos($dimos_name_raw_en, 'Municipality') === false) ? 'Municipality of ' . $dimos_name_raw_en :$dimos_name_raw_en;
    }
    if (!empty($ad['PlaceEN'])) {
        $area_name_en = strip_tags(trim($ad['PlaceEN']));
        if (!empty($dimos_name_raw_en))$area_name_en .= ', Mun. of ' . str_replace('Municipality of ', '', $dimos_name_raw_en);
        $location_hierarchy_en[] =$area_name_en;
    }

    // Types Greek
    $type_hierarchy_el = [];
    $type_hierarchy_el[] =$category_map[$CatID];$subcat_name_el = isset(SUB_CATEGORIES[$CatID][$ad['SubCatID']]) ? trim(SUB_CATEGORIES[$CatID][$ad['SubCatID']]) : '';
    if (!empty($subcat_name_el) &&$subcat_name_el !== $category_map[$CatID]) $type_hierarchy_el[] =$subcat_name_el;

    // Types English
    $type_hierarchy_en = [];
    $type_hierarchy_en[] =$category_map_en[$CatID];$subcat_name_en = isset(SUB_CATEGORIES_EN[$CatID][$ad['SubCatID']]) ? trim(SUB_CATEGORIES_EN[$CatID][$ad['SubCatID']]) : '';
    if (!empty($subcat_name_en)) $type_hierarchy_en[] =$subcat_name_en;

    $slug_id = $CatID.'-'.$mid;
    $custom_slug_el = trim($subcat_name_el . ' ' . strip_tags($ad['PlaceEL'] ?? '') . ' ' .$slug_id);
    $custom_slug_en = trim($subcat_name_en . ' ' . strip_tags($ad['PlaceEN'] ?? '') . ' ' .$slug_id);

    $ENERGY_CLASS_LATIN = [1 => 'a+', 2 => 'a', 3 => 'b+', 4 => 'b', 5 => 'c', 6 => 'd', 7 => 'e', 8 => 'f', 9 => 'g', 15 => '', 16 => ''];
    $energy_class = (isset($ad['EnergyClass']) && isset($ENERGY_CLASS_LATIN[$ad['EnergyClass']])) ? $ENERGY_CLASS_LATIN[$ad['EnergyClass']] : '';

    $video_url = isset($ad['YouTubeID']) ? 'https://youtu.be/'.$ad['YouTubeID'] : '';

    // =========================================================
    // Raw CRM IDs
    // =========================================================
    $crm_ids = [];$raw_keys = [
        'SubCatID', 'Furnished', 'Equipped', 'FloorsK','Water',  'LastFloor','Balcony','WCType','Lift'];
    foreach ($raw_keys as$key) {
        if (isset($ad[$key]) && $ad[$key] !== '') {
            $crm_ids[strtolower($key)] = $ad[$key];
        }
    }

    // Universal Data
    $universal_data = [
        'price'        => $price,
        'area'         => $area,
        'latitude'     => $Latitude,
        'longitude'    => $Longitude,
        'actual_lat'   => $actual_lat,
        'actual_lng'   => $actual_lng,
        'display_mode' => $display_mode,
        'year_built'   => $clean_year,
        'energy_class' => $energy_class,
        'photos'       => $photos_array,
        'video'        => $video_url
    ];

    if ($CatID == 2) {
       $universal_data['Rooms'] = max(1,intval($ad['Rooms']));
       if ($ad['WC']>0) $universal_data['wc'] = (int)$ad['WC'];
    }
    if ($CatID == 1) { 
        $universal_data['bedrooms'] = (int)$ad['Bedrooms']; 
        if ($ad['BathRooms']>0) $universal_data['bathrooms'] = (int)$ad['BathRooms'];
        if ($ad['WC']>0) $universal_data['wc'] = (int)$ad['WC'];
    }
    if ( SUB_CATEGORIES[$CatID][$ad['SubCatID']] == 'Μεζονέτα' && isset($ad['Levels']) ) {
        if ($ad['Levels'] > 0) $universal_data['Levels'] = (int)$ad['Levels'];
    }

    // Final Payload
    $property = [
        'oid'         => $ad['OID'],
        'property_id' => $CatID.'-'.$mid,
        'action'      => 'update',
        'universal'   => $universal_data,
        'crm_ids'     => $crm_ids,
        
        'el' => [
            'type'        => $type_hierarchy_el,
            'status'      => $status_el,
            'address'     => $AddressGR,
            'location'    => $location_hierarchy_el,
            'title'       => (!empty($subcat_name_el) ? $subcat_name_el :$category_map[$CatID]) . ', ' . number_format(round($area),0,',','.') . ' τ.μ.',
            'slug_base'   => $custom_slug_el,
            'description' => $description_el, 
            'features'    => $features_el,
            'additional_details' => $additional_details_el,
            'floor'       => $floor_value_el,
            'wc_type'     => (!empty($ad['WC']) && $ad['WCType'] > 0) ? WC_TYPE[$ad['WCType']] : ''
        ],

        'en' => [
            'type'        => $type_hierarchy_en,
            'status'      => $status_en,
            'address'     => $AddressEN,
            'location'    => $location_hierarchy_en,
            'title'       => (!empty($subcat_name_en) ? $subcat_name_en :$category_map_en[$CatID]) . ', ' . number_format(round($area),0,',','.') . ' sq.m.',
            'slug_base'   => $custom_slug_en,
            'description' => $description_en, 
            'features'    => $features_en,
            'additional_details' => $additional_details_en,
            'floor'       => $floor_value_en,
            'wc_type'     => (!empty($ad['WC']) && $ad['WCType'] > 0) ? WC_TYPE_EN[$ad['WCType']] : ''
        ]
    ];

    return $property;
}

function pushToRealHomes($payload,$url) {
    $ch = curl_init($url);
    $json_payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS,$json_payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($json_payload)
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if ($http_code === 200) {
        return [0, 'Επιτυχής Συγχρονισμός!'];
    } else {
        return [1, 'Σφάλμα κατά την αποστολή στο RealHomes (HTTP ' . $http_code . '): ' .$response];
    }
}

//**************************************************
// Επιστρέφει όλα τα δημοσιευμένα ακίνητα
// Επιστρέφει [0, array_of_ads] (ή [0, count] if OnlyCount is true) στην επιτυχία, ή [ErrorCode, ErrorMessage] στην αποτυχίσ
function Site_GetPublishedAds($OnlyCount = FALSE) {$url = REALTOR_WEB . '/wp-json/crm/v1/published?key=[SECRET_API_KEY]';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    
    if ($http_code === 200 &&$response) {
        $data = json_decode($response, true);
        
        if (isset($data['total']) && isset($data['listings'])) {
            if ($OnlyCount) {
                return [0, (int)$data['total']];
            }
            return [0, $data['listings']];
        }
        return [999, 'Invalid JSON structure returned from site.'];
    }
    
    return [$http_code, 'Failed to connect to site. ' .$curl_error];
}

//**************************************************
// Αποστολή Διαγραφής Τοποθεσίας στο RealHomes
//**************************************************
function RealHomes_DeleteLocation($name_en) {$slug_el = urlencode(trim($name_en) . '-el');$slug_en = urlencode(trim($name_en) . '-en');$url = REALTOR_WEB . '/wp-json/crm/v1/sync-locations?key=[SECRET_API_KEY]&action=delete&slug_el=' . $slug_el . '&slug_en=' .$slug_en;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); // Fire-and-forget timeout
    curl_exec($ch);
    curl_close($ch);
}

//**************************************************
// Αποστολή Ενημέρωσης Ορίων Τοποθεσίας στο RealHomes
//**************************************************
function RealHomes_SyncLocationBounds($kind,$sync_id) {
    global $mysqli;
    
    $sync_data = [
        'key' => '[SECRET_API_KEY]',
        'action' => 'update',
        'kind' => $kind
    ];
    
    if ($sync_data['kind'] === 'Place' ||$sync_data['kind'] === 'NewPlace') {
        $q =$mysqli->query("SELECT a.AreaFull, a.AreaFullEN, a.Bounds, a.Coords, d.DimosEN FROM `Locations_Area` a LEFT JOIN `Locations_Municipality` d ON a.DID = d.ID WHERE a.ID = $sync_id");
        if ($q && $row =$q->fetch_assoc()) {
            $sync_data['name_el'] = strip_tags($row['AreaFull']);
            $sync_data['name_en'] = !empty(trim($row['AreaFullEN'])) ? strip_tags($row['AreaFullEN']) :$sync_data['name_el'];
            $sync_data['bounds'] =$row['Bounds'];
            $sync_data['coords'] =$row['Coords'];
            $sync_data['parent_en'] =$row['DimosEN'];
        }
    } elseif ($sync_data['kind'] === 'Dimos') {
        $q =$mysqli->query("SELECT d.DimosEL, d.DimosEN, d.Bounds, d.Coords, p.PeriferiaEN FROM `Locations_Municipality` d LEFT JOIN `Locations_Region` p ON d.PeriferiaID = p.ID WHERE d.ID = $sync_id");
        if ($q && $row =$q->fetch_assoc()) {
            $sync_data['name_el'] = (strpos($row['DimosEL'], 'Δήμος') === false) ? 'Δήμος ' . $row['DimosEL'] :$row['DimosEL'];
            $sync_data['name_en'] = (strpos($row['DimosEN'], 'Municipality') === false) ? 'Municipality of ' . $row['DimosEN'] :$row['DimosEN'];
            $sync_data['bounds'] =$row['Bounds'];
            $sync_data['coords'] =$row['Coords'];
            $sync_data['parent_en'] =$row['PeriferiaEN'];
        }
    } elseif ($sync_data['kind'] === 'Periferia') {
        $q =$mysqli->query("SELECT PeriferiaEL, PeriferiaEN, Bounds, ZipCoords FROM `Locations_Region` WHERE ID = $sync_id");
        if ($q &&$row = $q->fetch_assoc()) {$sync_data['name_el'] = (strpos($row['PeriferiaEL'], 'Περιφέρεια') === false) ? 'Περιφέρεια ' . $row['PeriferiaEL'] : $row['PeriferiaEL'];$sync_data['name_en'] = $row['PeriferiaEN'];$sync_data['bounds'] = $row['Bounds'];$sync_data['coords'] = $row['ZipCoords'];$sync_data['parent_en'] = '';
        }
    }

    if (!empty($sync_data['name_el'])) {$ch = curl_init(REALTOR_WEB . '/wp-json/crm/v1/sync-locations');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, 500); // Fire-and-forget timeout
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($sync_data));
        curl_exec($ch);
        curl_close($ch);
    }
}

?>
