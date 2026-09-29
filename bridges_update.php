<?php
// 1. ΔΙΑΓΡΑΦΕΣ ΑΚΙΝΗΤΩΝ ΑΠΟ PORTALS
// 2. ΔΗΜΙΟΥΡΓΙΑ SLIDESHOW ΓΙΑ YOUTUBE
// 3. ΕΠΙΛΟΓΗ ΑΓΓΕΛΙΩΝ ΓΙΑ ΑΝΑΝΕΩΣΗ
// 4. ΕΝΗΜΕΡΩΣΕΙΣ ΑΓΓΕΛΙΩΝ ΣΕ PORTALS
// 5. ΕΚΚΑΘΑΡΙΣΕΙΣ

//******************************************
function SQLQuery4PortalsUpdate($Portal,$CatID,$UpdateTo,$MaxAds)
{
$Table='O'.CAT_TABLE[$CatID];
if ($CatID<3) {$BuildingSQL1='Buildings.Photos AS BuildingPhotos,'; $BuildingSQL2="LEFT JOIN Buildings ON $Table.BuildingID=Buildings.ID";}
else {$BuildingSQL1=''; $BuildingSQL2='';}
$PortalPlacesSQL1='Places.SpitogatosId, Places.SpitogatosId2,'; $PortalPlacesSQL2='LEFT JOIN ... PortalPlaces ON $Table.Place=PortalPlaces.ID';
return"SELECT OMandates.*, $Table.*, $BuildingSQL1 Places.PlaceEL, Places.PlaceEN, $PortalPlacesSQL1 PlacesGeo.Bounds, Dimoi.SpitogatosId AS DimosSpID3, Dimoi.SpitogatosId2 AS DimosSpID2, Partners.PublicID, {$Portal}IDs.{$Portal}ID, {$Portal}IDs.ImagesList FROM OMandates LEFT JOIN $Table ON OMandates.PropertyID=$Table.ID $BuildingSQL2 LEFT JOIN Places ON $Table.Place=Places.ID $PortalPlacesSQL2 LEFT JOIN PlacesGeo ON $Table.Place=PlacesGeo.ID LEFT JOIN Dimoi ON $Table.DimosID=Dimoi.ID LEFT JOIN Partners ON OMandates.RatesPartner=Partners.TraderID LEFT JOIN {$Portal}IDs ON OMandates.OID={$Portal}IDs.OID WHERE OMandates.{$Portal}Active=1 AND OMandates.Category=$CatID AND OMandates.{$Portal}Updated=0 AND OMandates.MesitikoID=".MESITIKO_ID.(OLD_PROPERTY_CODE?' AND MMID!=""':'')." AND OMandates.ChangedDate<='$UpdateTo' ORDER BY OMandates.ChangedDate LIMIT $MaxAds";
}
//******************************************
function SQLQuery4PortalsDelete($Portal,$UpdateTo)
{
return"SELECT OMandates.OID".(OLD_PROPERTY_CODE?", OMandates.MMID":", OMandates.Category, OMandates.MID").", {$Portal}IDs.{$Portal}ID FROM OMandates LEFT JOIN {$Portal}IDs ON OMandates.OID={$Portal}IDs.OID WHERE OMandates.{$Portal}Active=0 AND {$Portal}IDs.{$Portal}LastUpdate IS NOT NULL ".($Portal=='XE' ? 'AND XEValidUntil>NOW() ' : '')."AND OMandates.ChangedDate<='$UpdateTo'";
}
//******************************************

try
	{
	require '/path/to/crm/includes/mes-constants.php';
	require '/path/to/crm/includes/ajax-Functions.php';
	require '/path/to/crm/includes/f_Email4.php';
	require '/path/to/platform/includes/realtor-constants.php';
	require '/path/to/crm/includes/Video.php';
	}
catch (\Throwable $e)
	{exit($e->getMessage());}

register_shutdown_function('handleFatalError',['Params'=>EMAIL_PARAMS, 'Email'=>REALTOR_EMAIL]);
register_shutdown_function('handleFatalError',['Params'=>EMAIL_PARAMS, 'Email'=>'[DEVELOPER_EMAIL]']);

if (empty($_GET))
	{
	parse_str($argv[1], $params);
	$pass=$params['pass'];
	}
else $pass=$_GET['pass'];
if ($pass!='[SECRET_API_KEY]') {EmailIt(EMAIL_PARAMS,REALTOR_EMAIL,'Ενημέρωση Portals','Παράνομη κλήση της Αυτόματης Ενημέρωσης των Portal !','');EmailIt(EMAIL_PARAMS,'[DEVELOPER_EMAIL]','Ενημέρωση Portals','Παράνομη κλήση της Αυτόματης Ενημέρωσης των Portal !',''); exit;}
if (!$mysqli=OpenDB()) exit('ERROR opening DB');

const AD_TYPE=array(
	1=>'Κατοικιών',
	2=>'Επαγγελματικών Χώρων',
	3=>'Γης',
	4=>'Επιχειρήσεων'
	);
const SP_FEATURED=array(
	1=>'UP',
	2=>'PRIME',
	3=>'VIP'
	);

set_time_limit(240);
$TimeInterval="-5 minutes";
$UpdateTo=date("Y-m-d H:i:s",strtotime($TimeInterval));
$Errors='';
$OK='';
$RemResMsg='';
$SP_Msg='';
$XE_Msg='';

if (PORTALS['Plot']['SYNCHRONIZE'])
	{
	try
		{require '/path/to/crm/includes/plotV2.0.php';}
	catch (\Throwable $e)
		{exit($e->getMessage());}
	$plot_conn = Connect2Plot(false);
	if ($plot_conn !== true) {
		$Errors .= "PLOT Connection Failed: Code " . $plot_conn[0] . " - " . $plot_conn[1] . "<br/>";
	}
	}
if (PORTALS['XE']['SYNCHRONIZE'])
	{
	try
		{require '/path/to/crm/includes/xe.php';}
	catch (\Throwable $e)
		{exit($e->getMessage());}
	}
if (PORTALS['Spitogatos']['SYNCHRONIZE'])
	{
	try
		{require '/path/to/crm/includes/Spitogatos.php';}
	catch (\Throwable $e)
		{exit($e->getMessage());}
	}
if (REALTOR_SYNCHRONIZE)
	{
	try
		{require '/path/to/crm/includes/realhomes-functions.php';}
	catch (\Throwable $e)
		{exit($e->getMessage());}
	}

try
	{require '/path/to/crm/vendor/autoload.php';}
catch (\Throwable $e)
	{exit($e->getMessage());}
use Aws\S3\S3Client;
$s3 = S3Client::factory($AWS_CREDENTIALS);
$s3->registerStreamWrapper();


//***************************************************************
//***********   1. ΔΙΑΓΡΑΦΕΣ ΑΚΙΝΗΤΩΝ ΑΠΟ PORTALS  **************
//***************************************************************
//*********** Διαγραφές από Spitogatos.gr *******
if (PORTALS['Spitogatos']['SYNCHRONIZE'])
	{
	$query=SQLQuery4PortalsDelete('Spitogatos',$UpdateTo); $DebugErrorMsg="Query: $query";
	if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
	$FeaturedMsg='';
	$results=$result->num_rows;
	if ($results>0)
		{
		for ($i=0; $i<$results; $i++)
			{
			$ads=$result->fetch_assoc();
			$r=Spitogatos_DeleteListing(PORTALS['Spitogatos']['INFOS'],$ads['SpitogatosID']);
			if (($r===TRUE) || ($r[0]==105))
				{
				$query="DELETE FROM SpitogatosIDs WHERE OID=$ads[OID]"; $DebugErrorMsg.="QUERY: $query<br>".print_r($ads,true).'<br>';
				if (($result1=$mysqli->query($query))===FALSE) {$Errors.='ERROR 1 :'.$mysqli->error." QUERY : $query";}
				$query="SELECT Category, MID, MMID, SpFeatured FROM OMandates WHERE OID=$ads[OID]"; $DebugErrorMsg.="QUERY: $query<br>".print_r($ads,true).'<br>';
				if (($result1=$mysqli->query($query))===FALSE) {$Errors.='ERROR 3:'.$mysqli->error." QUERY : $query";}
				if ($result1->num_rows>0)
					{
					$Fut=$result1->fetch_assoc();
					if ($Fut['SpFeatured']>0) $FeaturedMsg.="<br/>Το ".(OLD_PROPERTY_CODE?$Fut['MMID']:$Fut['Category'].'-'.$Fut['MID'])." ήταν προβεβλημμένο ".SP_FEATURED[$Fut['SpFeatured']];
					}
				$query="UPDATE OMandates SET SpitogatosUpdated=0, SpFeatured=0 WHERE OID=$ads[OID]"; $DebugErrorMsg.="QUERY: $query<br>".print_r($ads,true);
				if (($result1=$mysqli->query($query))===FALSE) {$Errors.='ERROR 2:'.$mysqli->error;}
				}
			else $Errors.='ERROR 4 : '.$r[1];
			}
		$SP_Msg.="<br/>Διαγράφηκαν $results ακίνητα στο Spitogatos.gr.";
		if ($FeaturedMsg!='') $SP_Msg.='<br/>'.$FeaturedMsg;
		}
	}

//*********** Διαγραφές από XE.gr *******
if (PORTALS['XE']['SYNCHRONIZE'])
	{
	$query=SQLQuery4PortalsDelete('XE',$UpdateTo);
	if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
	$results=$result->num_rows;
	if ($results>0)
		{
		$XEDeleted=[];
		for ($i=0; $i<$results; $i++)
			{
			$ad=$result->fetch_assoc();
			$r=XE_Remove($ad['XEID']);
			if ($r[0]==0)
				{
				$mysqli->query("UPDATE OMandates SET XEUpdated=0 WHERE OID=".$ad['OID']);
				$mysqli->query("UPDATE XEIDs SET XEPublished=0, XEValidUntil=NOW() WHERE OID=".$ad['OID']);
				$XEDeleted[]=(OLD_PROPERTY_CODE?$ad['MMID']:$ad['Category'].'-'.$ad['MID']);
				}
			else $Errors.=$r[1];
			}
		if ($Errors!='') $RemResMsg.='<br/><br/><b>ERRORS</b>'.$Errors;
		if ($XE_Msg!='') $RemResMsg.='<br/><br/><b>XE.gr</b>'.$XE_Msg;
		}
	}

//*********** Διαγραφές από Plot.gr *******
if (PORTALS['Plot']['SYNCHRONIZE'] && defined('PLOT_TOKEN'))
	{
	$query=SQLQuery4PortalsDelete('Plot',$UpdateTo); $DebugErrorMsg="Query: $query";
	if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
	$results=$result->num_rows;
	if ($results>0)
		{
		for ($i=1; $i<=$results; $i++)
			{
			$ads=$result->fetch_assoc();
			$r=DeleteFromPlot($ads['PlotID'],$ads['OID']);
			if ($r[0]>0) $Errors.="ERROR $r[0] : $r[1]<br>";
			}
		}
	}


//***********************************************************
//********    2. ΔΗΜΙΟΥΡΓΙΑ SLIDESHOW ΓΙΑ YOUTUBE    ********
//***********************************************************
if (AUTO_CREATE_SLIDESHOWS)
	{
	for ($CatID=1; $CatID<=4; $CatID++)
		{
		$Table='O'.CAT_TABLE[$CatID];
		if ($CatID<3) {$BuildingSQL1=', Buildings.Photos AS BuildingPhotos'; $BuildingSQL2="LEFT JOIN Buildings ON $Table.BuildingID=Buildings.ID";}
		else {$BuildingSQL1=''; $BuildingSQL2='';}
		$query="SELECT OMandates.*, $Table.*, Places.PlaceEL $BuildingSQL1 FROM OMandates LEFT JOIN $Table ON OMandates.PropertyID=$Table.ID $BuildingSQL2 LEFT JOIN Places ON $Table.Place=Places.ID LEFT JOIN Dimoi ON $Table.DimosID=Dimoi.ID WHERE OMandates.Category=$CatID".(OLD_PROPERTY_CODE?" AND OMandates.MMID<>''":'')." AND OMandates.Active>-1 AND (OMandates.OnMarket>-1 OR OMandates.OnPortal>-1) AND OMandates.MesitikoID=".MESITIKO_ID." AND $Table.VideoID=0 AND $Table.Photos!='' AND ((LENGTH($Table.Photos) - LENGTH(REPLACE($Table.Photos, ',', '')))>=".(SLIDE_MIN_PHOTOS-1).") AND OMandates.ChangedDate<='$UpdateTo'";
		if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
		else {
			$results=$result->num_rows;
			if ($results>0)
				{
				for ($i=1; $i<=$results; $i++)
					{
					$ad=$result->fetch_assoc();
					$r=CreateAndUploadSlideShow($ad,false);
					if ($r['ErrorCode']==0) $Errors.="Δημιουργήθηκε Slideshow για το ".(OLD_PROPERTY_CODE?$ad['MMID']:$ad['Category'].'-'.$ad['MID'])."<br>";
					else
						{
						if ($r['ErrorCode']==403) {break 2;}
						if ($r['ErrorCode']!=999104) {
							$r['TraceLog'][]=["File"=>__FILE__, "Function"=>__FUNCTION__, "Line"=>__LINE__, "Message"=>"", "Variables"=>["ad"=>$ad, "query"=>$query]];
							$Errors.=FormatMyError($r);
							}
						}
					}
				}
			}
		}
	}


//**********************************************************
//********     3. ΕΠΙΛΟΓΗ ΑΓΓΕΛΙΩΝ ΓΙΑ ΑΝΑΝΕΩΣΗ     ********
//**********************************************************
// ******  Ανανέωση Spitogatos.gr *******
if (PORTALS['Spitogatos']['SYNCHRONIZE'])
	{
	$SP_IntervalInSec = time() - strtotime(date('Y-m-d '.SP_SYNCHRONIZE_TIME));
	if (($SP_IntervalInSec>=0) && ($SP_IntervalInSec<300))
		{
		if (PORTALS['Spitogatos']['REMOVE_AND_RESEND_AFTER']!==FALSE)
			{
			$result=$mysqli->query('SELECT OID,SpitogatosID FROM SpitogatosIDs WHERE MesitikoID='.MESITIKO_ID.' AND (DATEDIFF(CURDATE(),EntryRefDay)>'.PORTALS['Spitogatos']['REMOVE_AND_RESEND_AFTER'].')');
			if ($result===FALSE) $Errors.='ERROR:'.$mysqli->error;
			else
				{
				$results=$result->num_rows;
				if ($results>0)
					{
					$FeaturedMsg='';
					for ($i=1; $i<=$results; $i++)
						{
						$ads=$result->fetch_assoc();
						$r=Spitogatos_DeleteListing(PORTALS['Spitogatos']['INFOS'],$ads['SpitogatosID']);
						if (($r===TRUE) || ($r[0]==105))
							{
							$query="DELETE FROM SpitogatosIDs WHERE OID=$ads[OID]";
							if (($result1=$mysqli->query($query))===FALSE) {$Errors.='ERROR 1 :'.$mysqli->error." QUERY : $query";}
							if (($result1=$mysqli->query("SELECT Category,MID,MMID,SpFeatured FROM OMandates WHERE OID=$ads[OID]"))===FALSE) {$Errors.='ERROR 3:'.$mysqli->error;}
							if ($result1->num_rows>0)
								{
								$Fut=$result1->fetch_assoc();
								if ($Fut['SpFeatured']>0) $FeaturedMsg.="<br/>Το ".(OLD_PROPERTY_CODE?$Fut['MMID']:$Fut['Category'].'-'.$Fut['MID'])." ήταν προβεβλημμένο ".SP_FEATURED[$Fut['SpFeatured']];
								}
							if (($result1=$mysqli->query("UPDATE OMandates SET SpitogatosUpdated=0,SpFeatured=0 WHERE OID=$ads[OID]"))===FALSE) {$Errors.='ERROR 2:'.$mysqli->error;}
							}
						else $Errors.='ERROR 4 : '.$r[1];
						}
					$SP_Msg="<br/>Θα ανανεωθεί η ημερομηνία καταχώρισης της αγγελίας στο Spitogatos.gr σε $results ακίνητα.";
					if ($FeaturedMsg!='') $SP_Msg.='<br/>'.$FeaturedMsg;
					}
				}
			}
		if (PORTALS['Spitogatos']['REFRESH_ADS_DAILY'])
			{
			if (($result=$mysqli->query("UPDATE OMandates SET SpitogatosUpdated=0 WHERE OMandates.MesitikoID=".MESITIKO_ID.(OLD_PROPERTY_CODE?' AND MMID!=""':'')." AND SpitogatosActive=1 AND Active>=0 AND SpitogatosUpdated=1"))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
			$SP_Msg='Ξεκίνησε η ενημέρωση όλων των ενεργών αγγελιών με σημερινή ημερομηνία'.$SP_Msg;
			}
		if ($SP_Msg!='') $RemResMsg.='<b>Spitogatos.gr</b><br/>'.$SP_Msg;
		}
	$SP_IntervalInSec1 = time() - strtotime(date('Y-m-d '.SP_RENEW_TIME1));
	$SP_IntervalInSec2 = time() - strtotime(date('Y-m-d '.SP_RENEW_TIME2));
	}

// ******  Ανανέωση Xe.gr *******
if ((PORTALS['XE']['SYNCHRONIZE']) && (PORTALS['XE']['FULL_RESEND_AFTER']!==FALSE))
	{
	$XE_IntervalInSec = time() - strtotime(date('Y-m-d '.PORTALS['XE']['RESEND_TIME']));
	if (($XE_IntervalInSec>=0) && ($XE_IntervalInSec<300))
		{
		if ($result=$mysqli->query('UPDATE OMandates LEFT JOIN XEIDs ON OMandates.OID=XEIDs.OID SET XEUpdated=0 WHERE OMandates.MesitikoID='.MESITIKO_ID.(OLD_PROPERTY_CODE?' AND MMID!=""':'')." AND XEActive=1 AND Active>=0 AND XEUpdated=1 AND XELastUpdate<'".date("Y-m-d",strtotime(-PORTALS['XE']['FULL_RESEND_AFTER']." days"))."'")===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
		if ($mysqli->affected_rows>0) $XE_Msg='<br/>Θα ανανεωθεί πλήρως η καταχώριση της αγγελίας στο xe.gr σε '.$mysqli->affected_rows.' ακίνητα.';
		if ($XE_Msg!='') $RemResMsg.='<br/><br/><b>XE.gr</b>'.$XE_Msg;
		if ($Errors!='') $RemResMsg.='<br/><br/><b>ERRORS</b>'.$Errors;
		}
	}


//************************************************************
//********     4. ΕΝΗΜΕΡΩΣΕΙΣ ΑΓΓΕΛΙΩΝ ΣΕ PORTALS     ********
//************************************************************
$TotalRenewed=0;
for ($CatID=1; $CatID<=4; $CatID++)
	{
	$Table=CAT_TABLE[$CatID];
	if ($CatID<3) {$BuildingSQL1=', Buildings.Photos AS BuildingPhotos'; $BuildingSQL2="LEFT JOIN Buildings ON O$Table.BuildingID=Buildings.ID";}
	else {$BuildingSQL1=''; $BuildingSQL2='';}

	//******* Spitogatos.gr ********
	if (PORTALS['Spitogatos']['SYNCHRONIZE'])
		{
		$query="SELECT OMandates.*, O$Table.*, Places.PlaceEL, Places.PlaceEN, Places.SpitogatosId, Places.SpitogatosId2, PlacesGeo.Bounds, Dimoi.XEid AS DimosXEid, Dimoi.SpitogatosId AS DimosSpID3, Dimoi.SpitogatosId2 AS DimosSpID2, Videos.YouTubeID, Partners.PublicID, SpitogatosIDs.SpitogatosID, SpitogatosIDs.ImagesList $BuildingSQL1 FROM OMandates LEFT JOIN O$Table ON OMandates.PropertyID=O$Table.ID $BuildingSQL2 LEFT JOIN Places ON O$Table.Place=Places.ID LEFT JOIN PlacesGeo ON O$Table.Place=PlacesGeo.ID LEFT JOIN Dimoi ON O$Table.DimosID=Dimoi.ID LEFT JOIN Partners ON OMandates.RatesPartner=Partners.TraderID LEFT JOIN Videos ON O$Table.VideoID=Videos.VID LEFT JOIN SpitogatosIDs ON OMandates.OID=SpitogatosIDs.OID WHERE OMandates.MesitikoID=".MESITIKO_ID.(OLD_PROPERTY_CODE?' AND MMID!=""':'')." AND SpitogatosActive=1 AND OMandates.Category=$CatID AND SpitogatosUpdated=0 AND OMandates.ChangedDate<='$UpdateTo' ORDER BY OMandates.ChangedDate LIMIT ".PORTALS['Spitogatos']['MAX_ADS_PP'];
		if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error.' QUERY: '.$query;}
		else
			{
			$results=$result->num_rows;
			for ($i=1; $i<=$results; $i++)
				{
				$ads=$result->fetch_assoc();
				$DebugErrorMsg="QUERY: $query<br>".print_r($ads,true).'<br>';
				if (is_null($ads['SpitogatosID'])) $r=Spitogatos_NewListing(PORTALS['Spitogatos']['INFOS'],$ads);
				else
					{
					$r=Spitogatos_EditListing(PORTALS['Spitogatos']['INFOS'],$ads);
					if (($r[0]>0) && ($r[1]=='Listing not found')) $r=Spitogatos_NewListing(PORTALS['Spitogatos']['INFOS'],$ads);
					}
				if ($r[0]>0) $Errors.="Spitogatos_Listing Ανάθεση: $ads[Category]-$ads[MID] ($ads[MMID]) : $r[1]<br/>";
				else
					{
					if (($result1=$mysqli->query("UPDATE OMandates SET SpitogatosUpdated=1 WHERE OID=$ads[OID]"))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
					$OK.=$ads['Category'].'-'.$ads['MID'].'<br/>';
					}
				}
			}
		//******* RENEW
		if ((PORTALS['Spitogatos']['REFRESH_ADS_DAILY']) && ((($SP_IntervalInSec1>=0) && ($SP_IntervalInSec1<300)) || (($SP_IntervalInSec2>=0) && ($SP_IntervalInSec2<300))))
			{
			$query="SELECT SpitogatosIDs.SpitogatosID, OMandates.OID, OMandates.Category, OMandates.MID FROM OMandates LEFT JOIN SpitogatosIDs ON OMandates.OID=SpitogatosIDs.OID WHERE OMandates.MesitikoID=".MESITIKO_ID.(OLD_PROPERTY_CODE?' AND MMID!=""':'')." AND SpitogatosActive=1 AND OMandates.Category=$CatID AND SpitogatosID IS NOT NULL ORDER BY OMandates.ChangedDate";
			if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error.' QUERY: '.$query;}
			else
				{
				$results=$result->num_rows;
				$RenewErrors='';
				$RenewOK='';
				$Renewed=0;
				for ($i=1; $i<=$results; $i++)
					{
					$ads=$result->fetch_assoc();
					$r=Spitogatos_RenewListing(PORTALS['Spitogatos']['INFOS'],$ads['SpitogatosID']);
					if (is_array($r)) $RenewErrors.='Spitogatos_RenewListing Ανάθεση: '.$ads['Category'].'-'.$ads['MID'].' ERROR='.$r[0].' '.$r[1].'<br/>';
					else
						{
						$RenewOK.=$ads['Category'].'-'.$ads['MID'].'<br/>';
						$result3=$mysqli->query("UPDATE SpitogatosIDs SET SpitogatosLastUpdate=NOW() WHERE OID=$ads[OID]");
						$Renewed++;
						}
					}
				$RemResMsg.="Ανανεώθηκαν $Renewed αγγελίες ".AD_TYPE[$CatID].'<br/>';
				$TotalRenewed+=$Renewed;
				$Errors.=$RenewErrors;
				$OK.=$RenewOK;
				}
			}
		}
	if (($TotalRenewed>0) && ($RemResMsg!='') && ($CatID==4)) $RemResMsg.="--------------------------------------------------<br/>Συνολικά ανανεώθηκαν $TotalRenewed αγγελίες<br/>";

	//******* XE.gr *******
	if (PORTALS['XE']['SYNCHRONIZE'])
		{
		$query="SELECT OMandates.*, O$Table.*, Places.PlaceEL, Places.XEid, Dimoi.DimosEL, Dimoi.XEid AS DimosXEid, Videos.YouTubeID, XEIDs.XEID, XEIDs.Revision, XEIDs.XEPublished, XEIDs.XEValidUntil, Partners.PublicID $BuildingSQL1 FROM OMandates LEFT JOIN O$Table ON OMandates.PropertyID=O$Table.ID $BuildingSQL2 LEFT JOIN Places ON O$Table.Place=Places.ID LEFT JOIN Dimoi ON O$Table.DimosID=Dimoi.ID LEFT JOIN Videos ON O$Table.VideoID=Videos.VID LEFT JOIN Partners ON OMandates.RatesPartner=Partners.TraderID LEFT JOIN XEIDs ON XEIDs.OID=OMandates.OID WHERE OMandates.MesitikoID=".MESITIKO_ID.(OLD_PROPERTY_CODE?' AND MMID!=""':'')." AND Active>=0 AND OMandates.XEActive=1 AND OMandates.Category=$CatID AND OMandates.XEUpdated=0 AND OMandates.ChangedDate<='$UpdateTo' ORDER BY OMandates.ChangedDate LIMIT ".PORTALS['XE']['MAX_ADS_PP'];
		if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
		else
			{
			$results=$result->num_rows;
			for ($i=0; $i<$results; $i++)
				{
				$ad=$result->fetch_assoc();
				$r=XE_Update(PORTALS['XE']['INFOS'],$ad,'BASIC');
				if ($r[0]>1) $Errors.="XE - ERROR in property $ad[Category]-$ad[MID] ($ad[MMID]) : ".$r[1].'<br/><br/>';
				else
					{
					if ($r[0]==1) $Errors.="XE - WARNING $ad[Category]-$ad[MID] ($ad[MMID]) : ".$r[1].'<br/><br/>';
					if (($result1=$mysqli->query('UPDATE OMandates SET XEUpdated=1 WHERE OID='.$ad['OID']))===FALSE) {$Errors.='ERROR:'.$mysqli->error.'<br/>';}
					$assignment_list="OID=$ad[OID], XEID='$r[2]', MesitikoID=".MESITIKO_ID.", XELastUpdate=NOW(), Revision=$r[3], XEPublished=1".(empty($r[4]) ? '' : ", XEValidUntil='$r[4]'");
					if (($result1=$mysqli->query("INSERT INTO XEIDs SET $assignment_list ON DUPLICATE KEY UPDATE$assignment_list"))===FALSE) {$Errors.='ERROR:'.$mysqli->error.'<br/>';}
					}
				}
			}
		$result->free_result();
		}

	//******* Plot.gr *******
	if (PORTALS['Plot']['SYNCHRONIZE'] && defined('PLOT_TOKEN'))
		{
		$query="SELECT OMandates.*, O$Table.*, Places.PlaceEL, PlacesGeo.Bounds, Dimoi.DimosEL, PlotIDs.PlotID $BuildingSQL1 FROM OMandates LEFT JOIN O$Table ON OMandates.PropertyID=O$Table.ID $BuildingSQL2 LEFT JOIN Places ON O$Table.Place=Places.ID LEFT JOIN PlacesGeo ON O$Table.Place=PlacesGeo.ID LEFT JOIN Dimoi ON O$Table.DimosID=Dimoi.ID LEFT JOIN PlotIDs ON OMandates.OID=PlotIDs.OID WHERE OMandates.MesitikoID=".MESITIKO_ID.(OLD_PROPERTY_CODE?' AND MMID!=""':'')." AND OMandates.Active>=0 AND OMandates.PlotActive=1 AND OMandates.Category=$CatID AND OMandates.PlotUpdated=0 AND OMandates.ChangedDate<='$UpdateTo' ORDER BY OMandates.ChangedDate LIMIT ".PL_MAX_ADS_PP;
		if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
		else
			{
			$results=$result->num_rows;
			for ($i=1; $i<=$results; $i++)
				{
				$ad=$result->fetch_assoc();
		   		if (!(isset($ad['PlotID']))) $r=Plot_NewAd($ad);
		   		else $r=Plot_editAd($ad);
				if ($r[0]==0)
					{
					if (($result1=$mysqli->query("UPDATE OMandates SET PlotUpdated=1 WHERE OID=$ad[OID]"))===FALSE) {$Errors.='ERROR:'.$mysqli->error;}
					}
				else
					{
					$Errors.="Plot Ανάθεση: $ad[Category]-$ad[MID] ($ad[MMID]) :$r[1]<br/><br/>";
					}
				}
			}
		$result->free_result();
		}
}

    

//**********************************************************
//************         5. ΕΚΚΑΘΑΡΙΣΕΙΣ          ************
//**********************************************************
$CleanErrors='';$CleanOK='';

//***************    Spitogatos.gr    ****************
if (PORTALS['Spitogatos']['SYNCHRONIZE'])
	{
	$query="SELECT SpitogatosIDs.SpitogatosID, OMandates.OID, OMandates.Category, OMandates.MID, OMandates.MMID FROM `SpitogatosIDs` LEFT JOIN OMandates ON OMandates.OID=SpitogatosIDs.OID WHERE SpitogatosActive=0 AND ISNULL(SpitogatosIDs.SpitogatosLastUpdate) AND OMandates.ChangedDate<='$UpdateTo'";
	if (($result=$mysqli->query($query))===FALSE) {$Errors.='ERROR:'.$mysqli->error.' QUERY: '.$query;}
	else
		{
		$results=$result->num_rows;
		for ($i=1; $i<=$results; $i++)
			{
			$ads=$result->fetch_assoc();
			$r=Spitogatos_DeleteListing(PORTALS['Spitogatos']['INFOS'],$ads['SpitogatosID']);
			if (is_array($r))
				{
				if ($r[0]==105)
					if (($mysqli->query("DELETE FROM SpitogatosIDs WHERE OID=$ads[OID]"))===FALSE) $CleanErrors.='ERROR DELETING SPITOGATOS ID FROM DB';$CleanErrors.='Spitogatos - Εκκαθαρίστηκε: '.$ads['Category'].'-'.$ads['MID']." ($ads[MMID]) : ".$r[1].'<br/>';
				}
			else $CleanOK.="Spitogatos - Διαγράφηκε: $ads[Category]-$ads[MID] ($ads[MMID])<br/>";
			}
		$Errors.=$CleanErrors.$CleanOK;
		}
	}

//***************    XE.gr    ****************
if (PORTALS['XE']['SYNCHRONIZE'])
	{
	$XE_IntervalInSec = time() - strtotime(date('Y-m-d '.PORTALS['XE']['CLEAN_TIME']));
	if (($XE_IntervalInSec>=0) && ($XE_IntervalInSec<300)) 		{$response=XE_GetPublishedAds();
		if ($response[0]==0) 			{$PublishedAds=$response[1];$ErCl='';
			for ($i=0; $i<count($PublishedAds);$i++)
				{
				$query="SELECT OID FROM OMandates WHERE XEActive=1".(OLD_PROPERTY_CODE?" AND MMID=":" AND CONCAT(Category,'-',MID)='".$PublishedAds[$i]['RefId'])."'";
				$result=$mysqli->query($query);
				if ($result->num_rows==0) 					{$r=XE_Remove($PublishedAds[$i]['XEID']);
					if ($r[0]==0)$ErCl.=$PublishedAds[$i]['RefId'].', ';
					else $Errors.=$r[1];
					}
				}
			if ($ErCl!='') $Errors.="ΧΕ - Εκκαθάριση: διαγράφηκαν παλιές ενεργές αγγελίες ($ErCl)";
			}
		else $Errors.="ΧΕ - Εκκαθάριση: $response[1]<br/>";
		}
	}

//*******    Client Custom Platform SYNC *******
if (REALTOR_SYNCHRONIZE) 
	{
    $RH_IntervalInSec = time() - strtotime(date('Y-m-d '.REALTOR_CLEAN_TIME));
    
    if (($RH_IntervalInSec >= 0) && ($RH_IntervalInSec < 300))         {$site_response = Site_GetPublishedAds();
        
        if ($site_response[0] == 0 && is_array($site_response[1]))              {$live_site_ids = array_column($site_response[1], 'RefId');$crm_active_query = "SELECT CONCAT(Category, '-', MID) AS RefId FROM OMandates WHERE MesitikoID = ".MESITIKO_ID." AND SiteActive = 1 AND Active >= 0";
            $crm_active_ids = [];
            if ($result = $mysqli->query($crm_active_query)) {
                while ($row =$result->fetch_assoc()) {
                    $crm_active_ids[] =$row['RefId'];
                }
            }
            $ghosts = array_diff($live_site_ids,$crm_active_ids);

            if (!empty($ghosts)) 
                {
                $delete_payloads = [];
                foreach ($ghosts as $ghost_ref) {$delete_payloads[] = ['property_id' => $ghost_ref,'action' => 'delete'];}$wordpress_api_url = REALTOR_WEB.'/wp-json/crm/v1/import?key=[SECRET_API_KEY]';
                $ch = curl_init($wordpress_api_url);
                $json_payload = json_encode($delete_payloads, JSON_UNESCAPED_UNICODE);
                
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS,$json_payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . strlen($json_payload)]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);

                if ($http_code == 200)                      {$ghost_list_str = implode(', ', $ghosts);$RemResMsg .= "<br/><b>=== [CLIENT_WEBSITE_URL] (Εκκαθάριση) ===</b><br/>";
                    $RemResMsg .= "Εντοπίστηκαν και διαγράφηκαν " . count($ghosts) . " αγγελίες από την ιστοσελίδα: <b>" . $ghost_list_str . "</b><br/>";
                    } 
                else 
                    {
                    $err_msg = $curl_error ? $curl_error : "HTTP $http_code. Απόκριση: " . strip_tags((string)$response);
                    $Errors .= "[CLIENT_WEBSITE_URL] Εκκαθάριση αγγελιών απέτυχε: " . $err_msg . "<br/>";
                    }
                }
            }
        }
	}

//******* Spiti360.gr *******
if (PORTALS['Spiti360']['SYNCHRONIZE'])
	{
	$S360_IntervalInSec = time() - strtotime(date('Y-m-d 00:10:00'));
	if (($S360_IntervalInSec>=0) && ($S360_IntervalInSec<300)) 		{$output=array();
		exec('find '.BRIDGES_PATH.' -maxdepth 1 -type f -mtime +1 -exec rm {} \; 2>&1', $output, $err);$output=print_r($output,true);$RemResMsg.='<br/><br/><b>Spiti360.gr</b><br/>Αποτελέσματα διαγραφής παλιών xml γέφυρας: '.($err?$output:'OK');
		}
	}


//******* Client Custom Platform SYNC *******
if(REALTOR_SYNCHRONIZE)
	{
	$export_url = 'https://[LEGACY_CRM_URL]/admin/export-realhomes.php?key=[SECRET_API_KEY]';

	$context = stream_context_create([
	    'http' => ['ignore_errors' => true],
	    'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
	]);

	$json_payload = file_get_contents($export_url, false,$context);

	if ($json_payload !== false) {
	    $json_payload = preg_replace('/^[\xef\xbb\xbf]+/', '',$json_payload);
	    $properties = json_decode($json_payload, true);
	    
	    if ($properties !== null && json_last_error() === JSON_ERROR_NONE) {
	        
	        if (!empty($properties)) {$wordpress_api_url = REALTOR_WEB.'/wp-json/crm/v1/import?key=[SECRET_API_KEY]';
	            $crm_ack_url = 'https://[LEGACY_CRM_URL]/admin/export-realhomes.php?key=[SECRET_API_KEY]&action=ack';
	            
	            $batch_size = 5;
	            $chunks = array_chunk($properties, $batch_size);$RH_Msg = "";
	            
	            foreach ($chunks as $index =>$chunk) {
	                $chunk_json = json_encode($chunk);
	                
	                $ch = curl_init($wordpress_api_url);
	                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	                curl_setopt($ch, CURLOPT_POST, true);
	                curl_setopt($ch, CURLOPT_POSTFIELDS,$chunk_json);
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
	                    $synced_oids = [];$deleted_oids = [];
                        $updated_props = [];$deleted_props = [];
                        
	                    foreach ($chunk as$prop) {
	                        if (isset($prop['oid']) && isset($prop['action'])) {
                                $p_id = isset($prop['property_id']) ? $prop['property_id'] :$prop['oid'];
                                
	                            if ($prop['action'] === 'update') {
                                    $synced_oids[] =$prop['oid'];
                                    $updated_props[] =$p_id;
                                } elseif ($prop['action'] === 'delete') {
                                    $deleted_oids[] =$prop['oid'];
                                    $deleted_props[] =$p_id;
                                }
	                        }
	                    }
	                    
	                    if (!empty($synced_oids) || !empty($deleted_oids)) {$ack_payload = json_encode(['synced_oids' => $synced_oids, 'deleted_oids' =>$deleted_oids]);
	                        $ack_ch = curl_init($crm_ack_url);
	                        curl_setopt($ack_ch, CURLOPT_RETURNTRANSFER, true);
	                        curl_setopt($ack_ch, CURLOPT_POST, true);
	                        curl_setopt($ack_ch, CURLOPT_POSTFIELDS,$ack_payload);
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
	                    $err_msg =$curl_error ? $curl_error : "HTTP $http_code. Απόκριση: " . strip_tags((string)$response);$Errors .= "[CLIENT_WEBSITE_URL] Batch " . ($index + 1) . " απέτυχε: " . $err_msg . "<br/>";
	                }
	                
	                sleep(1);
	            }
	            
	            if ($RH_Msg != '') {$RemResMsg .= "<br/><b>=== [CLIENT_WEBSITE_URL] (Αποτελέσματα Συγχρονισμού) ===</b><br/>";
	                $RemResMsg .= "Εντοπίστηκαν " . count($properties) . " ακίνητα για συγχρονισμό (" . count($chunks) . " batches).<br/>" . $RH_Msg;
	            }
	        }
	    } else {
	        $Errors .= "[CLIENT_WEBSITE_URL] JSON Decode Error: " . json_last_error_msg() . "<br/>";
	    }
	} else {
	    $Errors .= "[CLIENT_WEBSITE_URL]: Αποτυχία λήψης δεδομένων από το export-realhomes.php<br/>";
	}

	}


//***************************************************
if (($RemResMsg!='') \vert{}\vert{} ($Errors!=''))
	{
	$Body='<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
	<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title></title>
	</head>
	<body leftmargin="0" topmargin="0" marginwidth="0" marginheight="0" style="margin: 0px; padding: 0px; background-color: #FFFFFF;" bgcolor="#FFFFFF">'.($RemResMsg!=''?$RemResMsg.'<br/><br/>':'').$Errors.'</body></html>';
	EmailIt(EMAIL_PARAMS,REALTOR_EMAIL,'Ενημέρωση Portals',$Body,'');
	EmailIt(EMAIL_PARAMS,'[DEVELOPER_EMAIL]','Ενημέρωση Portals',$Body,'');

	}
elseif ($OK!='') {
	EmailIt(EMAIL_PARAMS,'[DEVELOPER_EMAIL]','Ενημέρωση Portals','Οι ενημερώσεις έγιναν:<br/>'.$OK,''); }
?>