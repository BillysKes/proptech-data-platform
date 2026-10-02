<?php
//*********************************************************
// ETL Batch Processor & Automated Sync (bridges_update.php)
//*********************************************************

try {
    require '/path/to/crm/includes/mes-constants.php';
    require '/path/to/crm/includes/ajax-Functions.php';
    require '/path/to/crm/includes/f_Email4.php';
    require '/path/to/platform/includes/realtor-constants.php';
    require '/path/to/crm/includes/realhomes-functions.php';
    require '/path/to/crm/vendor/autoload.php';
} catch (\Throwable $e) {
    exit($e->getMessage());
}

use Aws\S3\S3Client;

register_shutdown_function('handleFatalError',['Params'=>EMAIL_PARAMS, 'Email'=>'[DEVELOPER_EMAIL]']);

// 1. Security & DB Initialization
if (empty($_GET)) {
    parse_str($argv[1], $params);
    $pass = $params['pass'];
} else {
    $pass = $_GET['pass'];
}

if ($pass != '[SECRET_API_KEY]') {
    EmailIt(EMAIL_PARAMS, '[DEVELOPER_EMAIL]', 'Sync Alert', 'Unauthorized API Call!', ''); 
    exit;
}

if (!$mysqli = OpenDB()) exit('ERROR opening DB');

set_time_limit(240);
$Errors = '';
$RemResMsg = '';

// 2. AWS S3 Virtualization Init
$s3 = S3Client::factory($AWS_CREDENTIALS);
$s3->registerStreamWrapper();

if (REALTOR_SYNCHRONIZE) {

    // =========================================================================
    // A. ORPHAN / GHOST LISTING CLEANUP
    // =========================================================================
    $RH_IntervalInSec = time() - strtotime(date('Y-m-d '.REALTOR_CLEAN_TIME));
    
    if (($RH_IntervalInSec >= 0) && ($RH_IntervalInSec < 300)) {
        $site_response = Site_GetPublishedAds();
        
        if ($site_response[0] == 0 && is_array($site_response[1])) {
            $live_site_ids = array_column($site_response[1], 'RefId');
            $crm_active_query = "SELECT CONCAT(Category, '-', MID) AS RefId FROM Listings_Master WHERE Agency_ID = [AGENCY_ID] AND PortalActive = 1 AND Active >= 0";
            
            $crm_active_ids = [];
            if ($result = $mysqli->query($crm_active_query)) {
                while ($row = $result->fetch_assoc()) {
                    $crm_active_ids[] = $row['RefId'];
                }
            }
            
            // Cross-reference live IDs with CRM active IDs to find orphans
            $ghosts = array_diff($live_site_ids, $crm_active_ids);

            if (!empty($ghosts)) {
                $delete_payloads = [];
                foreach ($ghosts as $ghost_ref) { 
                    $delete_payloads[] = ['property_id' => $ghost_ref, 'action' => 'delete'];
                }
                
                $wordpress_api_url = REALTOR_WEB . '/wp-json/crm/v1/import?key=[SECRET_API_KEY]';
                $ch = curl_init($wordpress_api_url);
                $json_payload = json_encode($delete_payloads, JSON_UNESCAPED_UNICODE);
                
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json_payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($json_payload)]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);

                if ($http_code == 200) {
                    $ghost_list_str = implode(', ', $ghosts);
                    $RemResMsg .= "<br/><b>=== [CLIENT_WEBSITE_URL] (Cleanup) ===</b><br/>";
                    $RemResMsg .= "Purged " . count($ghosts) . " orphaned listings: <b>" . $ghost_list_str . "</b><br/>";
                } else {
                    $err_msg = $curl_error ? $curl_error : "HTTP $http_code. Response: " . strip_tags((string)$response);
                    $Errors .= "[CLIENT_WEBSITE_URL] Cleanup Failed: " . $err_msg . "<br/>";
                }
            }
        }
    }

    // =========================================================================
    // B. BATCH ETL SYNCHRONIZATION PIPELINE
    // =========================================================================
    $export_url = 'https://[LEGACY_CRM_URL]/admin/export-realhomes.php?key=[SECRET_API_KEY]';

    $context = stream_context_create([
        'http' => ['ignore_errors' => true],
        'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);

    $json_payload = file_get_contents($export_url, false, $context);

    if ($json_payload !== false) {
        $json_payload = preg_replace('/^[\xef\xbb\xbf]+/', '', $json_payload);
        $properties = json_decode($json_payload, true);
        
        if ($properties !== null && json_last_error() === JSON_ERROR_NONE) {
            
            if (!empty($properties)) {
                $wordpress_api_url = REALTOR_WEB . '/wp-json/crm/v1/import?key=[SECRET_API_KEY]';
                $crm_ack_url = 'https://[LEGACY_CRM_URL]/admin/export-realhomes.php?key=[SECRET_API_KEY]&action=ack';
                
                // Chunk into smaller batches to prevent server timeouts
                $batch_size = 5;
                $chunks = array_chunk($properties, $batch_size);
                $RH_Msg = "";
                
                foreach ($chunks as $index => $chunk) {
                    $chunk_json = json_encode($chunk);
                    
                    $ch = curl_init($wordpress_api_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $chunk_json);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($chunk_json)
                    ]);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    
                    if ($http_code == 200) {
                        $synced_oids = []; 
                        $deleted_oids = [];
                        
                        foreach ($chunk as $prop) {
                            if (isset($prop['oid']) && isset($prop['action'])) {
                                if ($prop['action'] === 'update') {
                                    $synced_oids[] = $prop['oid'];
                                } elseif ($prop['action'] === 'delete') {
                                    $deleted_oids[] = $prop['oid'];
                                }
                            }
                        }
                        
                        // Bidirectional Webhook Acknowledgment
                        if (!empty($synced_oids) || !empty($deleted_oids)) {
                            $ack_payload = json_encode(['synced_oids' => $synced_oids, 'deleted_oids' => $deleted_oids]);
                            $ack_ch = curl_init($crm_ack_url);
                            curl_setopt($ack_ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ack_ch, CURLOPT_POST, true);
                            curl_setopt($ack_ch, CURLOPT_POSTFIELDS, $ack_payload);
                            curl_setopt($ack_ch, CURLOPT_HTTPHEADER, [
                                'Content-Type: application/json',
                                'Content-Length: ' . strlen($ack_payload)
                            ]);
                            curl_setopt($ack_ch, CURLOPT_TIMEOUT, 15);
                            curl_setopt($ack_ch, CURLOPT_SSL_VERIFYPEER, false); 
                            curl_setopt($ack_ch, CURLOPT_SSL_VERIFYHOST, false);
                            curl_exec($ack_ch);
                        }
                        
                    } else {
                        $err_msg = $curl_error ? $curl_error : "HTTP $http_code. Response: " . strip_tags((string)$response);
                        $Errors .= "[CLIENT_WEBSITE_URL] Batch " . ($index + 1) . " failed: " . $err_msg . "<br/>";
                    }
                    
                    sleep(1);
                }
                
                if ($RH_Msg != '') {
                    $RemResMsg .= "<br/><b>=== [CLIENT_WEBSITE_URL] (Sync Results) ===</b><br/>";
                    $RemResMsg .= "Processed " . count($properties) . " properties (" . count($chunks) . " batches).<br/>" . $RH_Msg;
                }
            }
        } else {
            $Errors .= "[CLIENT_WEBSITE_URL] JSON Decode Error: " . json_last_error_msg() . "<br/>";
        }
    } else {
        $Errors .= "[CLIENT_WEBSITE_URL]: Failed to fetch payload from export-realhomes.php<br/>";
    }
}

// 3. Error Reporting
if (($RemResMsg != '') || ($Errors != '')) {
    $Body = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
    <html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title></title></head>
    <body style="margin: 0px; padding: 0px; background-color: #FFFFFF;">' . ($RemResMsg != '' ? $RemResMsg . '<br/><br/>' : '') . $Errors . '</body></html>';
    
    EmailIt(EMAIL_PARAMS, '[DEVELOPER_EMAIL]', 'Sync Report', $Body, '');
}
?>
