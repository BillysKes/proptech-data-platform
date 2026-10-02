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
        $mysqli->query("UPDATE OMandates SET SiteUpdated = 1 WHERE OID IN ($oid_list)");
        $affected_rows += $mysqli->affected_rows;
    }
    
    if (!empty($input['deleted_oids']) && is_array($input['deleted_oids'])) {
        $oids = array_map('intval', $input['deleted_oids']); 
        $oid_list = implode(',', $oids);
        $mysqli->query("UPDATE OMandates SET SiteUpdated = 0 WHERE OID IN ($oid_list)");
        $affected_rows += $mysqli->affected_rows;
    }
    
    exit(json_encode(['success' => true, 'updated_records' => $affected_rows]));
}

$export_data = [];

for ($CatID = 1; $CatID <= 3; $CatID++) {
    
    $Table = CAT_TABLE[$CatID]; 
    
    if ($CatID < 3) {
        $BuildingSQL1 = ', Buildings.Photos AS BuildingPhotos'; 
        $BuildingSQL2 = "LEFT JOIN Buildings ON O$Table.BuildingID=Buildings.ID";
    } else {
        $BuildingSQL1 = ''; 
        $BuildingSQL2 = '';
    }

    $query = "SELECT OMandates.*, O$Table.*, Places.PlaceEL, Places.PlaceEN, PlacesGeo.Bounds AS PlaceBounds, Dimoi.DimosEL, Dimoi.DimosEN, Periferies.PeriferiaEL, Periferies.PeriferiaEN, Videos.YouTubeID $BuildingSQL1 
              FROM OMandates 
              LEFT JOIN O$Table ON OMandates.PropertyID=O$Table.ID 
              $BuildingSQL2 
              LEFT JOIN Places ON O$Table.Place=Places.ID 
              LEFT JOIN PlacesGeo ON O$Table.Place=PlacesGeo.ID 
              LEFT JOIN Dimoi ON O$Table.DimosID=Dimoi.ID
              LEFT JOIN Periferies ON Dimoi.PeriferiaID=Periferies.ID 
              LEFT JOIN Videos ON O$Table.VideoID=Videos.VID
              WHERE OMandates.MesitikoID = 1 
              AND OMandates.Category = $CatID 
              AND (
                    (OMandates.SiteActive = 1 AND OMandates.Active >= 0 AND OMandates.SiteUpdated = 0)
                    OR
                    ((OMandates.SiteActive = 0 OR OMandates.Active < 0) AND OMandates.SiteUpdated = 1)
                )";

    if (($result = $mysqli->query($query)) !== FALSE) {
        while ($ad = $result->fetch_assoc()) {
            
            $mid = $ad['MID'];
            
            $is_published = (
                isset($ad['SiteActive']) && $ad['SiteActive'] == 1 && 
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
