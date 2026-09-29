<?php
//*********************************************************
// export-realhomes.php
// Public endpoint for Bulk WordPress Synchronization 
//*********************************************************

if (!isset($_GET['key']) || $_GET['key'] !== '[SECRET_API_KEY]') {
    exit('Unauthorized access.');
}

ob_start();

require '/path/to/crm/includes/mes-constants.php';
require '/path/to/crm/includes/f_common.php';
require '/path/to/crm/includes/ajax-Functions.php';
require_once '/path/to/crm/includes/common-constants-el.php';
require_once '/path/to/crm/includes/common-constants-en.php';
require '/path/to/crm/includes/realhomes-functions.php';

ob_end_clean();

header('Content-Type: application/json; charset=utf-8');

if (!$mysqli = OpenDB()) {
    exit(json_encode(["error" => "ERROR opening DB"]));
}

// --- THE ACKNOWLEDGMENT LISTENER ---
if (isset($_GET['action']) && $_GET['action'] === 'ack') {
    $input = json_decode(file_get_contents('php://input'), true);
    $affected_rows = 0;
    
    if (!empty($input['synced_oids']) && is_array($input['synced_oids'])) {
        $oids = array_map('intval', $input['synced_oids']); 
        $oid_list = implode(',', $oids);
        $mysqli->query("UPDATE Listings_Master SET PortalUpdated = 1 WHERE OID IN ($oid_list)");
        $affected_rows += $mysqli->affected_rows;
    }
    
    if (!empty($input['deleted_oids']) && is_array($input['deleted_oids'])) {
        $oids = array_map('intval', $input['deleted_oids']); 
        $oid_list = implode(',', $oids);
        $mysqli->query("UPDATE Listings_Master SET PortalUpdated = 0 WHERE OID IN ($oid_list)");
        $affected_rows += $mysqli->affected_rows;
    }
    
    exit(json_encode(['success' => true, 'updated_records' => $affected_rows]));
}

$export_data = [];

for ($CatID = 1; $CatID <= 3; $CatID++) {
    
    $Type = CAT_TABLE[$CatID]; 
    
    if ($CatID < 3) {
        $BuildingSQL1 = ', Buildings_Media.Photos AS BuildingPhotos'; 
        $BuildingSQL2 = "LEFT JOIN Buildings_Media ON Properties_$Type.BuildingID=Buildings_Media.ID";
    } else {
        $BuildingSQL1 = ''; 
        $BuildingSQL2 = '';
    }

    $query = "SELECT Listings_Master.*, Properties_$Type.*, Locations_Area.Name_EL, Locations_Area.Name_EN, Locations_Geo.Bounds AS PlaceBounds, Locations_Municipality.Name_EL AS DimosEL, Locations_Municipality.Name_EN AS DimosEN, Locations_Region.Name_EL AS PeriferiaEL, Locations_Region.Name_EN AS PeriferiaEN, Media_Videos.YouTubeID $BuildingSQL1 
              FROM Listings_Master 
              LEFT JOIN Properties_$Type ON Listings_Master.PropertyID=Properties_$Type.ID 
              $BuildingSQL2 
              LEFT JOIN Locations_Area ON Properties_$Type.Place=Locations_Area.ID 
              LEFT JOIN Locations_Geo ON Properties_$Type.Place=Locations_Geo.ID 
              LEFT JOIN Locations_Municipality ON Properties_$Type.DimosID=Locations_Municipality.ID
              LEFT JOIN Locations_Region ON Locations_Municipality.RegionID=Locations_Region.ID 
              LEFT JOIN Media_Videos ON Properties_$Type.VideoID=Media_Videos.VID
              WHERE Listings_Master.Agency_ID = [AGENCY_ID] 
              AND Listings_Master.Category = $CatID 
              AND (
                    (Listings_Master.PortalActive = 1 AND Listings_Master.Active >= 0 AND Listings_Master.PortalUpdated = 0)
                    OR
                    ((Listings_Master.PortalActive = 0 OR Listings_Master.Active < 0) AND Listings_Master.PortalUpdated = 1)
                )";

    if (($result = $mysqli->query($query)) !== FALSE) {
        while ($ad = $result->fetch_assoc()) {
            
            $mid = $ad['MID'];
            
            $is_published = (
                isset($ad['PortalActive']) && $ad['PortalActive'] == 1 && 
                isset($ad['Active']) && (int)$ad['Active'] >= 0
            );
            
            $action = $is_published ? 'update' : 'delete';

            if ($action === 'delete') {
                $export_data[] = [
                    'oid'         => $ad['OID'], 
                    'action'      => 'delete',
                    'property_id' => $CatID.'-'.$mid
                ];
                continue; 
            }

            $export_data[] = RealHomes_Struct($CatID, $ad);
        }
        $result->free_result();
    }
}

echo json_encode($export_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

?>