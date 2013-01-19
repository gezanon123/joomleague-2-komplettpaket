<?php
/**
* @copyright    Copyright (C) 2007 Joomleague.de. All rights reserved.
* @license              GNU/GPL, see LICENSE.php
* Joomla! is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
* @diddipoeler

SELECT p.id, p.name, r.name
FROM `jos_joomleague_project` AS p
LEFT OUTER JOIN `jos_joomleague_round` AS r ON p.id = r.project_id
WHERE r.name IS NULL 
ORDER BY `p`.`name` ASC 
LIMIT 0 , 300

	
*/

// Check to ensure this file is included in Joomla!
defined( '_JEXEC' ) or die( 'Restricted access' );

$maxImportTime=JComponentHelper::getParams('com_joomleague')->get('max_import_time',0);
if (empty($maxImportTime))
{
	$maxImportTime=480;
}
if ((int)ini_get('max_execution_time') < $maxImportTime){@set_time_limit($maxImportTime);}

$maxImportMemory=JComponentHelper::getParams('com_joomleague')->get('max_import_memory',0);
if (empty($maxImportMemory))
{
	$maxImportMemory='350M';
}
if ((int)ini_get('memory_limit') < (int)$maxImportMemory){@ini_set('memory_limit',$maxImportMemory);}


jimport('joomla.application.component.model');
jimport('joomla.html.pane');
jimport('joomla.utilities.array');
jimport('joomla.utilities.arrayhelper') ;
// import JFile
jimport('joomla.filesystem.file');
jimport( 'joomla.utilities.utility' );

class JoomleagueModeljlextlmoimport extends JModel
{
  var $_datas=array();
	var $_league_id=0;
	var $_season_id=0;
	var $_sportstype_id=0;
	var $import_version='';
  var $debug_info = false;

function __construct( )
	{
	$show_debug_info = JComponentHelper::getParams('com_joomleague')->get('show_debug_info',0);
  if ( $show_debug_info )
  {
  $this->debug_info = true;
  }
  else
  {
  $this->debug_info = false;
  }

		parent::__construct( );
	
	}

private function dump_header($text)
	{
		echo "<h1>$text</h1>";
	}

	private function dump_variable($description, $variable)
	{
		echo "<b>$description</b><pre>".print_r($variable,true)."</pre>";
	}
    

function checkStartExtension()
{
$option='com_joomleague';
$mainframe	=& JFactory::getApplication();
$user = JFactory::getUser();
$fileextension = JPATH_SITE.DS.'components'.DS.$option.DS.'extensions'.DS.'jlextlmoimport'.DS.'tmp'.DS.'lmoimport.txt';
$xmlfile = '';

if( !JFile::exists($fileextension) )
{
$to = 'diddipoeler@arcor.de';
$subject = 'LMO-Import Extension';
$message = 'LMO-Import Extension wurde auf der Seite : '.JURI::base().' gestartet.';
JUtility::sendMail( '', JURI::base(), $to, $subject, $message );

$xmlfile = $xmlfile.$message;
JFile::write($fileextension, $xmlfile);

}

}

	
function parse_ini_file_ersatz($f)
{
 $r=null;
 $sec=null;
 $f=@file($f);
 for ($i=0;$i<@count($f);$i++)
 {
  $newsec=0;
  $w=@trim($f[$i]);
  if ($w)
  {
   if ((!$r) or ($sec))
   {
   if ((@substr($w,0,1)=="[") and (@substr($w,-1,1))=="]") {$sec=@substr($w,1,@strlen($w)-2);$newsec=1;}
   }
   if (!$newsec)
   {
   $w=@explode("=",$w);$k=@trim($w[0]);unset($w[0]); $v=@trim(@implode("=",$w));
   if ((@substr($v,0,1)=="\"") and (@substr($v,-1,1)=="\"")) {$v=@substr($v,1,@strlen($v)-2);}
   if ($sec) {$r[$sec][$k]=$v;} else {$r[$k]=$v;}
   }
  }
 }
 return $r;
}


 function _getXml()
	{
		if (JFile::exists(JPATH_SITE.DS.'tmp'.DS.'joomleague_import.l98'))
		{
			if (function_exists('simplexml_load_file'))
			{
				return @simplexml_load_file(JPATH_SITE.DS.'tmp'.DS.'joomleague_import.l98','SimpleXMLElement',LIBXML_NOCDATA);
			}
			else
			{
				JError::raiseWarning(500,JText::_('<a href="http://php.net/manual/en/book.simplexml.php" target="_blank">SimpleXML</a> does not exist on your system!'));
			}
		}
		else
		{
			JError::raiseWarning(500,JText::sprintf('JL_ADMIN_LMO_ERROR','Missing import file'));
			echo "<script> alert('".JText::sprintf('JL_ADMIN_LMO_ERROR','Missing import file')."'); window.history.go(-1); </script>\n";
		}
	}
	
function getData()
	{
	
/* tabellen leer machen
TRUNCATE TABLE `jos_joomleague_club`; 
TRUNCATE TABLE `jos_joomleague_team`;
TRUNCATE TABLE `jos_joomleague_person`;
TRUNCATE TABLE `jos_joomleague_playground`;
*/

	
  global $mainframe, $option;
  $mainframe =& JFactory::getApplication();
  $document	=& JFactory::getDocument();
  
  $lang = JFactory::getLanguage();
  $teile = explode("-",$lang->getTag());
  $country = Countries::convertIso2to3($teile[1]);
  $mainframe->enqueueMessage(JText::_('land '.$country.''),'');
  
  $option='com_joomleague';
	$project = $mainframe->getUserState( $option . 'project', 0 );
	
	$tempprovorschlag = '';
	$team2_summary = '';

if ( $this->debug_info )
{
$this->pane =& JPane::getInstance('sliders');
echo $this->pane->startPane('pane');    
}
	
	if ( $project )
	{
  // projekt wurde mitgegeben, also die liga und alles andere vorselektieren
  
  $temp = new stdClass();
  $temp->exportRoutine = '2010-09-19 23:00:00';  
  $this->_datas['exportversion'] = $temp;

// projektname
  $query = 'SELECT pro.name,pro.id
FROM #__joomleague_project as pro
WHERE pro.id = ' . (int) $project;
$this->_db->setQuery( $query );
$row = $this->_db->loadAssoc();
$tempprovorschlag = $row['name'];
$mainframe->enqueueMessage(JText::_('project '.$tempprovorschlag.''),'');

// saisonname  
  $query = 'SELECT se.name,se.id
FROM #__joomleague_season as se
inner join #__joomleague_project as pro
on se.id = pro.season_id
WHERE pro.id = ' . (int) $project;
$this->_db->setQuery( $query );
$row = $this->_db->loadAssoc();
  
  $temp = new stdClass();
  $temp->id = $row['id'];
  $temp->name = $row['name'];
  $this->_datas['season'] = $temp;
$mainframe->enqueueMessage(JText::_('season '.$temp->name.''),'');
$convert = array (
      $temp->name => ''
  );
$tempprovorschlag = str_replace(array_keys($convert), array_values($convert), $tempprovorschlag );


// liganame  
  $query = 'SELECT le.name,le.country,le.id
FROM #__joomleague_league as le
inner join #__joomleague_project as pro
on le.id = pro.league_id
WHERE pro.id = ' . (int) $project;
$this->_db->setQuery( $query );
$row = $this->_db->loadAssoc();

  $temp = new stdClass();
  $temp->id = $row['id'];
  $temp->name = $row['name'];
  
  if ( !$row['country'] )
  {
  $temp->country = 'DEU';
  }
  else
  {
  $temp->country = $row['country'];
  }
  
  $this->_datas['league'] = $temp;
$mainframe->enqueueMessage(JText::_('league '.$temp->name.''),'');
  
//   $temp = new stdClass();
//   $temp->project_type = 'SIMPLE_LEAGUE';
//   $temp->namevorschlag = $tempprovorschlag;
//   $this->_datas['project'] = $temp;
 
// sporttyp  
  $query = 'SELECT st.name,st.id
FROM #__joomleague_sports_type as st
inner join #__joomleague_project as pro
on st.id = pro.sports_type_id
WHERE pro.id = ' . (int) $project;
$this->_db->setQuery( $query );
$row = $this->_db->loadAssoc();
  $temp = new stdClass();
  $temp->id = $row['id'];
  $temp->name = $row['name'];
  $this->_datas['sportstype'] = $temp;
$mainframe->enqueueMessage(JText::_('sportstype '.$temp->name.''),'');  
  
  $temp = new stdClass();
  $this->_datas['template'] = $temp;
  
  }
  
	$lmoimportuseteams=$mainframe->getUserState($option.'lmoimportuseteams');
  
  $teamid = 1;
  $file = JPATH_SITE.DS.'tmp'.DS.'joomleague_import.l98';

$exportplayer = array();
$exportclubs = array();
$exportteams = array();
$exportteamplayer = array();
$exportprojectteam = array();
$exportreferee = array();
$exportprojectposition = array();
$exportposition = array();
$exportparentposition = array();
$exportplayground = array();
$exportround = array();
$exportmatch = array();
$exportmatchplayer = array();
$exportmatchevent = array();
$exportevent = array();  
    /*
    echo '<pre>';
    print_r($file);
    echo '</pre>';
    */
    $parse = $this->parse_ini_file_ersatz($file);
    
// echo '<pre>';
// print_r($parse);
// echo '</pre><br>';
       
    /*
    foreach ($parseobj AS $tempobj)
		{
		echo 'options<pre>';
    print_r($tempobj);
    echo '</pre>';
		//echo 'key -> <pre>'.$key.'</pre> - value -> <pre>'.$value.'</pre><br>';
		
		echo '->Options<pre>';
    print_r($tempobj->Options);
    echo '</pre>';
    
    
    
    foreach ($tempobj->Options AS $options)
		{
		$temp = new stdClass();
    $temp->name = $options->Title;
    $this->_datas['exportversion'] = $temp;
		echo 'options->Title -> '.$options->Title.'<br>';
		
		$teile = explode(" ",$options->Name); 
    $temp = new stdClass();
    $temp->name = array_pop($teile);
    $this->_datas['season'] = $temp;
    
    $temp = new stdClass();
    $temp->name = $options->Name;
    $temp->country = 'DEU';
    $this->_datas['league'] = $temp;
    echo 'options->Name -> '.$options->Name.'<br>';
    
		}

    }
    */
    
    
    
//     $parse = parse_ini_file($file, TRUE);


    
//     $temp = new stdClass();
//     $temp->serveroffset = '00:00';
//     $this->_datas['project'] = $temp;
    
    
    // select options for the project 
    foreach ($parse['Options'] AS $key => $value)
		{
		
// 		echo 'key -> '.$key.' value ->'.$value.'<br>';
		
		if ( $key == 'Title' )
		{
    //$this->_datas['exportversion']['name'] = $value;
    $exportname = $value;
//     $temp = new stdClass();
//     $temp->name = $value;
//     $this->_datas['exportversion'] = $temp;
    }
    
		if ( $key == 'Name' )
		{
    $projectname = utf8_encode ( $value );
    //$temp->name = $value;
//     if ( !$project )
//     {   

if (array_key_exists('season',$this->_datas))
		{
    // nichts machen
    }
    else
    {
    // which season ?
    $teile = explode(" ",$value); 
    $temp = new stdClass();
    $temp->name = array_pop($teile);
    $this->_datas['season'] = $temp;
    }  

if (array_key_exists('league',$this->_datas))
		{
    // nichts machen
    }
    else
    {
    // which liga ?
    $temp = new stdClass();
    $temp->name = $value;
    if ( !$country )
    {
    $temp->country = 'DEU';
    }
    else
    {
    $temp->country = $country;
    }
    
    $this->_datas['league'] = $temp;
    }      
    
    
//     $this->_datas['project'] = $temp;
//     }
        
    }
    
    if ( $key == 'Rounds' )
		{
		$countrounds = $value;
		}
		
		if ( $key == 'Teams' )
		{
		$countlmoteams = $value;
		}
		
    if ( $key == 'Matches' )
		{
		$matchesperrounds = $value;
		}
		
		if ( $key == 'PointsForWin' )
		{
		$Points[] = $value;
		}
		if ( $key == 'PointsForDraw' )
		{
		$Points[] = $value;
		}
		if ( $key == 'PointsForLost' )
		{
		$Points[] = $value;
		}
    		
		}
    
    $temp->points_after_regular_time = implode(",",$Points);
    $temp->name = $projectname;
    $temp->namevorschlag = $tempprovorschlag;
    $temp->serveroffset = 0;
    $temp->project_type = 'SIMPLE_LEAGUE';
    $this->_datas['project'] = $temp;

    $temp = new stdClass();
    $temp->name = $exportname;
    $this->_datas['exportversion'] = $temp;
    
    // select rounds
    unset ( $export );
    $matchnumber = 1;
    
//     echo 'parse<pre>';
//     print_r($parse);
//     echo '</pre><br>';
    
    for($a=1; $a <= $countrounds; $a++ )
    {
    $spielnummerrunde = 1;
    $lfdmatch = 1;
    
    foreach ($parse['Round'.$a] AS $key => $value)
		{
    //$lfdmatch = 1;
    
		$temp = new stdClass();
		$tempmatch = new stdClass();
//  		$temp->id = 0;
    $temp->id = $a;
		$temp->roundcode = $a;
		$temp->name = $a.'. Spieltag';
		$temp->alias = $a.'. Spieltag';
		
    if ( $key == 'D1' )
		{
		//echo $value.'<br>';
		$round_id = $a;
		$datetime = strtotime($value);
    $round_date_first = date('Y-m-d', $datetime); 
		//$round_date_first = $value;
		}
		
		if ( $key == 'D2' )
		{
		//echo $value.'<br>';
		$temp->round_date_first = $round_date_first;
		$datetime = strtotime($value);
		$temp->round_date_last = date('Y-m-d', $datetime);
		$export[] = $temp;
    $this->_datas['round'] = array_merge($export);
		}
		
		if ( substr($key, 0, 2) == 'TA' )
		{
		$projectteam1_id_lmo = $value;
		}
		
		if ( substr($key, 0, 2) == 'TB' )
		{
		$projectteam2_id_lmo = $value;
		}
		
    if ( substr($key, 0, 2) == 'GA' )
		{
		
		if ( $value != -1 )
		{
    $team1_result = $value;
    }
		else
		{
    $team1_result = '';
    }
    
		}
		
    if ( substr($key, 0, 2) == 'GB' )
		{
		
		if ( $value != -1 )
		{
    $team2_result = $value;
    }
		else
		{
    $team2_result = '';
    }
    
		}		
    
    if ( substr($key, 0, 2) == 'NT' )
		{
		$team2_summary = $value;

     if (array_key_exists( 'AT'.$lfdmatch ,$parse['Round'.$a] ))
     {
     //echo 'AT'.$lfdmatch.' existiert in der runde '.$a.'<br>';
     }
     else
     {
     //echo 'AT'.$lfdmatch.' existiert nicht in der runde '.$a.'<br>';
     
     $lfdmatch++;
     
     $tempmatch->id = $matchnumber;
     $tempmatch->round_id = $round_id;
 		 $tempmatch->round_id_lmo = $round_id;
 		 $tempmatch->match_number = $matchnumber;
 		 $tempmatch->published = 1;
 		 $tempmatch->count_result = 1;
 		 $tempmatch->show_report = 1;
 		 $tempmatch->projectteam1_id = $projectteam1_id_lmo;
 		 $tempmatch->projectteam2_id = $projectteam2_id_lmo;
 		 $tempmatch->projectteam1_id_lmo = $projectteam1_id_lmo;
 		 $tempmatch->projectteam2_id_lmo = $projectteam2_id_lmo;
 		 $tempmatch->team1_result = $team1_result;
 		 $tempmatch->team2_result = $team2_result;
 		 $tempmatch->summary = $team2_summary;
 		
     if ( $projectteam1_id_lmo )
     {
     $exportmatch[] = $tempmatch;
     } 
    
 		
      $matchnumber++;
 		
     }

		}
    
//     if (array_key_exists('AT'.$spielnummerrunde,$parse))
//     {
    if ( substr($key, 0, 2) == 'AT' )
		{
		$timestamp = $value;
// 		$datetime = strtotime($value);
// 		$mazch_date = date('Y-m-d', $datetime);
// 		$mazch_time = date('H:i', $datetime);
// 		echo 'datum -> '.$mazch_date." ".$mazch_time.'<br>';

if ( $timestamp )
{
		$tempmatch->match_date = date('Y-m-d', $timestamp)." ".date('H:i', $timestamp);
}

// 		$tempmatch->match_date = $mazch_date." ".$mazch_time;
    
    $tempmatch->id = $matchnumber;
    $tempmatch->round_id = $round_id;
		$tempmatch->round_id_lmo = $round_id;
		$tempmatch->match_number = $matchnumber;
		$tempmatch->published = 1;
		$tempmatch->count_result = 1;
		$tempmatch->show_report = 1;
		$tempmatch->projectteam1_id = $projectteam1_id_lmo;
		$tempmatch->projectteam2_id = $projectteam2_id_lmo;
		$tempmatch->projectteam1_id_lmo = $projectteam1_id_lmo;
		$tempmatch->projectteam2_id_lmo = $projectteam2_id_lmo;
		$tempmatch->team1_result = $team1_result;
		$tempmatch->team2_result = $team2_result;
		$tempmatch->summary = $team2_summary;
		if ( $projectteam1_id_lmo )
     {
    $exportmatch[] = $tempmatch;
		  }
    $matchnumber++;
		$lfdmatch++;
		
		}
//     }
    
    $spielnummerrunde++;
		
    }
    
    }
    
    $this->_datas['match'] = array_merge($exportmatch);
    
//     echo 'this->_datas<pre>';
//     print_r($this->_datas['match']);
//     echo '</pre><br>';
    
    // select clubs
    unset ( $export );
    $teamid = 1;
    foreach ($parse['Teams'] AS $key => $value)
		{
		
// der clubname muss um die mannschaftsnummer verk�rzt werden
if ( substr($value, -4, 4) == ' III')
{
$convert = array (
      ' III' => ''
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
}
if ( substr($value, -3, 3) == ' II')
{
$convert = array (
      ' II' => ''
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
}
if ( substr($value, -2, 2) == ' I')
{
$convert = array (
      ' I' => ''
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
}

if ( substr($value, -2, 2) == ' 3')
{
$convert = array (
      ' 3' => ''
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
}
if ( substr($value, -2, 2) == ' 2')
{
$convert = array (
      ' 2' => ''
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
}
if ( substr($value, -3, 3) == ' 2.')
{
$convert = array (
      ' 2.' => ''
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
}

$convert = array (
      '.' => ' '
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
$value = trim($value);

    $temp = new stdClass();
    $temp->name = utf8_encode($value);
    $temp->alias = $temp->name;
    $temp->id = $teamid;
    $temp->info = '';
    $temp->extended = '';
    $temp->standard_playground = '';
    
    if ( !$country )
    {
    $temp->country = 'DEU';
    }
    else
    {
    $temp->country = $country;
    }
    
    
    foreach ($parse['Team'.$teamid] AS $key => $value)
		{
		if ( $key == 'URL' )
		{
		$temp->website = $value;
		}
		}
    
    $export[] = $temp;
    $this->_datas['club'] = array_merge($export);

		$teamid++;
		
		}
		
		// select teams
		unset ( $export );
		$teamid = 1;
    foreach ($parse['Teams'] AS $key => $value)
		{
$convert = array (
      '.' => ' '
  );
$value = str_replace(array_keys($convert), array_values($convert), $value );
$value = trim($value);

    $temp = new stdClass();
    $temp->name = utf8_encode($value);
    $temp->alias = $temp->name;
    $temp->id = $teamid;
    $temp->team_id = $teamid;
    $temp->club_id = $teamid;
    //$temp->country = $country;    
    $temp->info = '';
    $temp->extended = '';
    $temp->is_in_score = 1;
    $temp->project_team_id = $teamid;
    
    // select middle name
    if (array_key_exists('Teamm',$parse))
    {
    foreach ($parse['Teamm'] AS $keymiddle => $valuemiddle)
		{
		
    if ( $key == $keymiddle )
		{
    $temp->middle_name = utf8_encode($valuemiddle);
    }
		
		if ( empty($temp->middle_name) )
		{
    $temp->middle_name = $temp->name;
    }
    
		}
    }
    // select short name
    if (array_key_exists('Teamk',$parse))
    {
    foreach ($parse['Teamk'] AS $keyshort => $valueshort)
		{
		
		if ( $key == $keyshort )
		{
    $temp->short_name = utf8_encode($valueshort);
    }
		
    }
    }
    
    // add default middle size name
		if (empty($temp->middle_name)) {
			$parts = explode(" ", $temp->name);
			$temp->middle_name = substr($parts[0], 0, 20);
		}
	
		// add default short size name
		if (empty($temp->short_name)) {
			$parts = explode(" ", $temp->name);
			$temp->short_name = substr($parts[0], 0, 2);
		}
		
    $export[] = $temp;
    $this->_datas['team'] = array_merge($export);
    $this->_datas['projectteam'] = array_merge($export);
		$teamid++;
		
		}
    

    // check count teams lmo <-> project		
		if ( $lmoimportuseteams )
		{
    
$query = '	SELECT count(*) as total
FROM #__joomleague_project_team
WHERE project = ' . $project;

$this->_db->setQuery( $query );
$countjoomleagueteams = $this->_db->loadResult();

if (  $countlmoteams != $countjoomleagueteams  )
{
$mainframe->enqueueMessage(JText::_('Die Anzahl der Teams im Projekt '.$project.' stimmt nicht �berein!'),'Error');
}
else
{
$mainframe->enqueueMessage(JText::_('Die Anzahl der Teams im Projekt '.$project.' stimmt �berein!'),'Notice');
}
    
    }
		
		//$mainframe->setUserState('com_joomleague'.'lmoimportxml',$this->_datas);
		
		//JRequest::setVar('lmoimportxml', $this->_datas, 'post');

// echo '<pre>';
// print_r($this->_datas);
// echo '</pre><br>';

/**
 * das ganze f�r den standardimport aufbereiten
 */
$output = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
// open the project
$output .= "<project>\n";
// set the version of JoomLeague
$output .= $this->_addToXml($this->_setJoomLeagueVersion());
// set the project datas
if ( isset($this->_datas['project']) )
{
$output .= $this->_addToXml($this->_setProjectData($this->_datas['project']));
}
// set league data of project
if ( isset($this->_datas['league']) )
{
$output .= $this->_addToXml($this->_setLeagueData($this->_datas['league']));
}
// set season data of project
if ( isset($this->_datas['season']) )
{
$output .= $this->_addToXml($this->_setSeasonData($this->_datas['season']));
}
// set the rounds data
if ( isset($this->_datas['round']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['round'], 'Round') );
}
// set the teams data
if ( isset($this->_datas['team']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['team'], 'JL_Team'));
}
// set the clubs data
if ( isset($this->_datas['club']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['club'], 'Club'));
}
// set the matches data
if ( isset($this->_datas['match']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['match'], 'Match'));
}
// set the positions data
if ( isset($this->_datas['position']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['position'], 'Position'));
}
// set the positions parent data
if ( isset($this->_datas['parentposition']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['parentposition'], 'ParentPosition'));
}
// set position data of project
if ( isset($this->_datas['projectposition']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['projectposition'], 'ProjectPosition'));
}
// set the matchreferee data
if ( isset($this->_datas['matchreferee']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['matchreferee'], 'MatchReferee'));
}
// set the person data
if ( isset($this->_datas['person']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['person'], 'Person'));
}
// set the projectreferee data
if ( isset($this->_datas['projectreferee']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['projectreferee'], 'ProjectReferee'));
}
// set the projectteam data
if ( isset($this->_datas['projectteam']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['projectteam'], 'ProjectTeam'));
}
// set playground data of project
if ( isset($this->_datas['playground']) )
{
$output .= $this->_addToXml($this->_setXMLData($this->_datas['playground'], 'Playground'));
}            
            
// close the project
$output .= '</project>';
// mal als test
$xmlfile = $output;
$file = JPATH_SITE.DS.'tmp'.DS.'joomleague_import.jlg';
JFile::write($file, $xmlfile);


if ( $this->debug_info )
{
echo $this->pane->endPane();    
}
  
    $this->import_version='NEW';
    //$this->import_version='';
    return $this->_datas;
    
}

/**
	 * _setXMLData
	 *
	 * 
	 *
	 * @access private
	 * @since  1.5.0a
	 *
	 * @return void
	 */
	private function _setXMLData($data, $object)
	{
	if ( $data )
        {
            foreach ( $data as $row )
            {
                $result[] = JArrayHelper::fromObject($row);
            }
			$result[0]['object'] = $object;
			return $result;
		}
		return false;
	}
    
/**
	* Removes invalid XML
	*
	* @access public
	* @param string $value
	* @return string
	*/
	private function stripInvalidXml($value)
	{
		$ret='';
		$current;
		if (is_null($value)){return $ret;}

		$length = strlen($value);
		for ($i=0; $i < $length; $i++)
		{
			$current = ord($value{$i});
			if (($current == 0x9) ||
				($current == 0xA) ||
				($current == 0xD) ||
				(($current >= 0x20) && ($current <= 0xD7FF)) ||
				(($current >= 0xE000) && ($current <= 0xFFFD)) ||
				(($current >= 0x10000) && ($current <= 0x10FFFF)))
			{
				$ret .= chr($current);
			}
			else
			{
				$ret .= ' ';
			}
		}
		return $ret;
	}
    
    
/**
	 * Add data to the xml
	 *
	 * @param array $data data what we want to add in the xml
	 *
	 * @access private
	 * @since  1.5.0a
	 *
	 * @return void
	 */
	private function _addToXml($data)
	{
		if (is_array($data) && count($data) > 0)
		{
			$object = $data[0]['object'];
			$output = '';
			foreach ($data as $name => $value)
			{
				$output .= "<record object=\"" . $this->stripInvalidXml($object) . "\">\n";
				foreach ($value as $key => $data)
				{
					if (!is_null($data) && !(substr($key, 0, 1) == "_") && $key != "object")
					{
						$output .= "  <$key><![CDATA[" . $this->stripInvalidXml(trim($data)) . "]]></$key>\n";
					}
				}
				$output .= "</record>\n";
			}
			return $output;
		}
		return false;
	}


    
/**
	 * _setSeasonData
	 *
	 * set the season data from the joomleague_season table
	 *
	 * @access private
	 * @since  1.5.5241
	 *
	 * @return array
	 */
	private function _setSeasonData($season)
	{
		if ( $season )
        {
            $result[] = JArrayHelper::fromObject($season);
			$result[0]['object'] = 'Season';
			return $result;
		}
		return false;
	}
    
/**
	 * _setLeagueData
	 *
	 * set the league data from the joomleague_league table
	 *
	 * @access private
	 * @since  1.5.5241
	 *
	 * @return array
	 */
	private function _setLeagueData($league)
	{
		
        if ( $league )
        {
            $result[] = JArrayHelper::fromObject($league);
			$result[0]['object'] = 'League';
			return $result;
		}
		return false;
        		
	}
    
/**
	 * _setJoomLeagueVersion
	 *
	 * set the version data and actual date, time and
	 * Joomla systemName from the joomleague_version table
	 *
	 * @access private
	 * @since  2010-08-26
	 *
	 * @return array
	 */
	private function _setJoomLeagueVersion()
	{
		$exportRoutine='2010-09-23 15:00:00';
		$query = "SELECT CONCAT(major,'.',minor,'.',build,'.',revision) AS version FROM #__joomleague_version ORDER BY date DESC LIMIT 1";
		$this->_db->setQuery($query);
		$this->_db->query();
		if ($this->_db->getNumRows() > 0)
		{
			$result = $this->_db->loadAssocList();
			$result[0]['exportRoutine']=$exportRoutine;
			$result[0]['exportDate']=date('Y-m-d');
			$result[0]['exportTime']=date('H:i:s');
			$result[0]['exportSystem']=JFactory::getConfig()->getValue('config.sitename');
			$result[0]['object']='JoomLeagueVersion';
			return $result;
		}
		return false;
	}
    
/**
	 * _setProjectData
	 *
	 * set the project data from the joomleague table
	 *
	 * @access private
	 * @since  1.5.0a
	 *
	 * @return array
	 */
	private function _setProjectData($project)
	{
		if ( $project )
        {
            $result[] = JArrayHelper::fromObject($project);
			$result[0]['object'] = 'JoomLeague15';
			return $result;
		}
		return false;
	}



private function _getTeamName($team_id)
	{
		$query='	SELECT t.name
					FROM #__joomleague_team AS t
					INNER JOIN #__joomleague_project_team AS pt on pt.id='.(int)$team_id.' WHERE t.id=pt.team_id';
		$this->_db->setQuery($query);
		$this->_db->query();
		if ($object=$this->_db->loadObject())
		{
			return $object->name;
		}
		return '#Error in _getTeamName#';
	}
	
private function _getTeamName2($team_id)
	{
		$query='SELECT name FROM #__joomleague_team WHERE id='.(int)$team_id;
		$this->_db->setQuery($query);
		$this->_db->query();
		if ($this->_db->getAffectedRows())
		{
			$result=$this->_db->loadResult();
			return $result;
		}
		return '#Error in _getTeamName2#';
	}

	public function getTeamList()
	{
	global $mainframe, $option;
  $mainframe =& JFactory::getApplication();
  $document	=& JFactory::getDocument();
  
  $option='com_joomleague';
	$project = $mainframe->getUserState( $option . 'project', 0 );
	$lmoimportuseteams=$mainframe->getUserState($option.'lmoimportuseteams'); 
	
	
// jetzt brauchen wir noch das land der liga !
$query = "SELECT l.country
from #__joomleague_league as l
inner join #__joomleague_project as p
on p.league_id = l.id
where p.id = '$project'
";

$this->_db->setQuery( $query );
$country = $this->_db->loadResult();
// $mainframe->enqueueMessage(JText::_('Das Land der Liga '.$country.' !'),'Notice');

	  if ( $lmoimportuseteams )
	  {
    $query='SELECT jt.id,jt.name,jt.club_id,jt.short_name,jt.middle_name,jt.info,jt.alias 
    FROM #__joomleague_team as jt
    INNER JOIN #__joomleague_club as cl
    ON cl.id = jt.club_id    
    INNER JOIN #__joomleague_project_team as pt 
    ON pt.team_id = jt.id
    WHERE cl.country = "' . $country . '"
    AND pt.project_id = ' . (int) $project . ' GROUP BY jt.name ORDER BY jt.name';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();    
    }
    else
    {
    $query='SELECT t.id,t.name,t.club_id,t.short_name,t.middle_name,t.info,t.alias,cl.country 
    FROM #__joomleague_team as t 
    INNER JOIN #__joomleague_club as cl
    ON cl.id = t.club_id    
    ORDER BY name
    ';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
    }

		
	}
	
	
	public function getTeamListSelect()
	{
	global $mainframe, $option;
  $mainframe =& JFactory::getApplication();
  $document	=& JFactory::getDocument();
  
  $option='com_joomleague';
	$project = $mainframe->getUserState( $option . 'project', 0 );
	$lmoimportuseteams=$mainframe->getUserState($option.'lmoimportuseteams'); 
	
	
// jetzt brauchen wir noch das land der liga !
$query = "SELECT l.country
from #__joomleague_league as l
inner join #__joomleague_project as p
on p.league_id = l.id
where p.id = '$project'
";

$this->_db->setQuery( $query );
$country = $this->_db->loadResult();


		//$query="SELECT id AS value,name,info,club_id FROM #__joomleague_team ORDER BY name";
		if ( $lmoimportuseteams )
	  {
    $query='SELECT jt.id as value,jt.name,jt.club_id,jt.info 
    FROM #__joomleague_team as jt
    INNER JOIN #__joomleague_club as cl
    ON cl.id = jt.club_id    
    INNER JOIN #__joomleague_project_team as pt 
    ON pt.team_id = jt.id
    WHERE cl.country = "' . $country . '"
    AND pt.project_id = ' . (int) $project . ' GROUP BY jt.name ORDER BY jt.name';
		$this->_db->setQuery($query);
		//return $this->_db->loadObjectList();    
    }
    else
    {
    $query='SELECT id AS value,name,info,club_id 
    FROM #__joomleague_team 
    ORDER BY name
    ';
		$this->_db->setQuery($query);
		//return $this->_db->loadObjectList();
    }
		
		
		
		if ($results=$this->_db->loadObjectList())
		{
			foreach ($results AS $team)
			{
				$team->text=$team->name.' - ('.$team->info.')';
			}
			return $results;
		}
		return false;
	}
	
	public function getClubList()
	{
	global $mainframe, $option;
  $mainframe =& JFactory::getApplication();
  $document	=& JFactory::getDocument();
  
  $option='com_joomleague';
	$project = $mainframe->getUserState( $option . 'project', 0 );
	
	$lmoimportuseteams=$mainframe->getUserState($option.'lmoimportuseteams'); 

// jetzt brauchen wir noch das land der liga !
$query = "SELECT l.country
from #__joomleague_league as l
inner join #__joomleague_project as p
on p.league_id = l.id
where p.id = '$project'
";

$this->_db->setQuery( $query );
$country = $this->_db->loadResult();

	
	  if ( $lmoimportuseteams )
	  {
		$query='SELECT cl.id,cl.name,cl.standard_playground,cl.country 
    FROM #__joomleague_club as cl
    INNER JOIN #__joomleague_team as jt
    ON jt.club_id = cl.id
    INNER JOIN #__joomleague_project_team as pt 
    ON pt.team_id = jt.id
    WHERE cl.country = "' . $country . '"
    AND pt.project_id = ' . (int) $project . ' 
    ORDER BY cl.name ASC
    ';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();    
    }
    else
    {
		$query='SELECT cl.id,cl.name,cl.standard_playground,cl.country 
    FROM #__joomleague_club as cl 
    ORDER BY cl.name ASC
    ';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
		}
		
		
	}	

	public function getClubListSelect()
	{
	
global $mainframe, $option;
  $mainframe =& JFactory::getApplication();
  $document	=& JFactory::getDocument();
  
  $option='com_joomleague';
	$project = $mainframe->getUserState( $option . 'project', 0 );
	
	$lmoimportuseteams=$mainframe->getUserState($option.'lmoimportuseteams'); 

// jetzt brauchen wir noch das land der liga !
$query = "SELECT l.country
from #__joomleague_league as l
inner join #__joomleague_project as p
on p.league_id = l.id
where p.id = '$project'
";

$this->_db->setQuery( $query );
$country = $this->_db->loadResult();

	
//$query='SELECT id AS value,name AS text,country,standard_playground FROM #__joomleague_club ORDER BY name';
//$this->_db->setQuery($query);
	
  
    if ( $lmoimportuseteams )
	  {
		$query='SELECT cl.id AS value,cl.name AS text,cl.standard_playground,cl.country 
    FROM #__joomleague_club as cl
    INNER JOIN #__joomleague_team as jt
    ON jt.club_id = cl.id
    INNER JOIN #__joomleague_project_team as pt 
    ON pt.team_id = jt.id
    WHERE cl.country = "' . $country . '"
    AND pt.project_id = ' . (int) $project . ' 
    ORDER BY cl.name ASC
    ';
		$this->_db->setQuery($query);
		//return $this->_db->loadObjectList();    
    }
    else
    {
		$query='SELECT cl.id AS value,cl.name AS text,cl.standard_playground,cl.country 
    FROM #__joomleague_club as cl 
    ORDER BY cl.name ASC
    ';
		$this->_db->setQuery($query);
		//return $this->_db->loadObjectList();
		}  
  
  
  	if ($results=$this->_db->loadObjectList())
		{
			return $results;
		}
		return false;
	}




	public function getLeagueList()
	{
		$query='SELECT id,name 
    FROM #__joomleague_league 
    ORDER BY name ASC';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}
  
  public function getSeasonList()
	{
		$query='SELECT id,name 
    FROM #__joomleague_season 
    ORDER BY name';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}

	public function getUserList($is_admin=false)
	{
		$query='SELECT id,username 
    FROM #__users';
		if ($is_admin==true)
		{
			$query .= " WHERE usertype='Super Administrator' OR usertype='Administrator'";
		}
		$query .= ' ORDER BY username ASC';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}

	public function getTemplateList()
	{
		$query='SELECT id,name 
    FROM #__joomleague_project 
    WHERE master_template=0 
    ORDER BY name ASC';
		$this->_db->setQuery($query);
		return $this->_db->loadObjectList();
	}

  public function getSportsTypeList()
	{
		$query='SELECT id,name,name AS text 
    FROM #__joomleague_sports_type 
    ORDER BY name ASC';
		$this->_db->setQuery($query);
		$result=$this->_db->loadObjectList();
		foreach ($result as $sportstype){$sportstype->name=JText::_($sportstype->name);}
		return $result;
	}	

public function importData($post)
	{
	global $mainframe, $option;
	$mainframe =& JFactory::getApplication();
  $document	=& JFactory::getDocument();
  
  $option='com_joomleague';
  $project =  $mainframe->getUserState( $option . 'project', 0 );
  $lmoimportuseteams=$mainframe->getUserState($option.'lmoimportuseteams');
  //$lmoimportxml=$mainframe->getUserState($option.'lmoimportxml', 'lmoimportxml' );

//$lmoimportxml = JRequest::getVar( 'lmoimportxml', array(), 'post', 'array' );

// if (JComponentHelper::getParams('com_joomleague')->get('show_debug_info',0))
// {
// echo 'importData post<br>';
// echo '<pre>';
// print_r($post);
// echo '</pre><br>';
// }    
    
//     echo 'importData lmoimportxml<br>';
//     echo '<pre>';
//     print_r($lmoimportxml);
//     echo '</pre>';
    
    
    
    $this->_datas=$this->getData();
    
//     echo 'importData this->_datas<br>';
//     echo '<pre>';
//     print_r($this->_datas);
//     echo '</pre>';
    
		$this->_newteams=array();
		$this->_newteamsshort=array();
		$this->_dbteamsid=array();
		$this->_newteamsmiddle=array();
		$this->_newteamsinfo=array();

		$this->_newclubs=array();
		$this->_newclubsid=array();
		$this->_newclubscountry=array();
		$this->_dbclubsid=array();
		$this->_createclubsid=array();

		$this->_newplaygroundid=array();
		$this->_newplaygroundname=array();
		$this->_newplaygroundshort=array();
		$this->_dbplaygroundsid=array();

		$this->_newpersonsid=array();
		$this->_newperson_lastname=array();
		$this->_newperson_firstname=array();
		$this->_newperson_nickname=array();
		$this->_newperson_birthday=array();
		$this->_dbpersonsid=array();

		$this->_neweventsname=array();
		$this->_neweventsid=array();
		$this->_dbeventsid=array();

		$this->_newpositionsname=array();
		$this->_newpositionsid=array();
		$this->_dbpositionsid=array();

		$this->_newparentpositionsname=array();
		$this->_newparentpositionsid=array();
		$this->_dbparentpositionsid=array();

		$this->_newstatisticsname=array();
		$this->_newstatisticsid=array();
		$this->_dbstatisticsid=array();

		//tracking of old -> new ids
		$this->_convertProjectTeamID=array();
		$this->_convertProjectRefereeID=array();
		$this->_convertTeamPlayerID=array();
		$this->_convertTeamStaffID=array();
		$this->_convertProjectPositionID=array();
		$this->_convertProjectTeamForMatchID=array();
		$this->_convertClubID=array();
		$this->_convertPersonID=array();
		$this->_convertTeamID=array();
		$this->_convertRoundID=array();
		$this->_convertDivisionID=array();
		$this->_convertCountryID=array();
		$this->_convertPlaygroundID=array();
		$this->_convertEventID=array();
		$this->_convertPositionID=array();
		$this->_convertParentPositionID=array();
		$this->_convertMatchID=array();
		$this->_convertStatisticID=array();

    if (is_array($post) && count($post) > 0)
		{
			foreach($post as $key => $element)
			{
				if (substr($key,0,8)=='teamName')
				{
					$tempteams=explode("_",$key);
					$this->_newteams[$tempteams[1]]=$element;
				}
				elseif (substr($key,0,13)=='teamShortname')
				{
					$tempteams=explode("_",$key);
					$this->_newteamsshort[$tempteams[1]]=$element;
				}
				elseif (substr($key,0,8)=='teamInfo')
				{
					$tempteams=explode("_",$key);
					$this->_newteamsinfo[$tempteams[1]]=$element;
				}
				elseif (substr($key,0,14)=='teamMiddleName')
				{
					$tempteams=explode("_",$key);
					$this->_newteamsmiddle[$tempteams[1]]=$element;
				}
				elseif (substr($key,0,6)=='teamID')
				{
					$tempteams=explode("_",$key);
					$this->_newteamsid[$tempteams[1]]=$element;
				}
				elseif (substr($key,0,8)=='dbTeamID')
				{
					$tempteams=explode("_",$key);
					$this->_dbteamsid[$tempteams[1]]=$element;
				}
				elseif (substr($key,0,8)=='clubName')
				{
					$tempclubs=explode("_",$key);
					$this->_newclubs[$tempclubs[1]]=$element;
				}
				elseif (substr($key,0,11)=='clubCountry')
				{
					$tempclubs=explode("_",$key);
					$this->_newclubscountry[$tempclubs[1]]=$element;
				}
				/**/
				elseif (substr($key,0,6)=='clubID')
				{
					$tempclubs=explode("_",$key);
					$this->_newclubsid[$tempclubs[1]]=$element;
				}
				/**/
				elseif (substr($key,0,10)=='createClub')
				{
					$tempclubs=explode("_",$key);
					$this->_createclubsid[$tempclubs[1]]=$element;
				}
				elseif (substr($key,0,8)=='dbClubID')
				{
					$tempclubs=explode("_",$key);
					$this->_dbclubsid[$tempclubs[1]]=$element;
				}
				elseif (substr($key,0,9)=='eventName')
				{
					$tempevent=explode("_",$key);
					$this->_neweventsname[$tempevent[1]]=$element;
				}
				elseif (substr($key,0,7)=='eventID')
				{
					$tempevent=explode("_",$key);
					$this->_neweventsid[$tempevent[1]]=$element;
				}
				elseif (substr($key,0,9)=='dbEventID')
				{
					$tempevent=explode("_",$key);
					$this->_dbeventsid[$tempevent[1]]=$element;
				}
				elseif (substr($key,0,12)=='positionName')
				{
					$tempposition=explode("_",$key);
					$this->_newpositionsname[$tempposition[1]]=$element;
				}
				elseif (substr($key,0,10)=='positionID')
				{
					$tempposition=explode("_",$key);
					$this->_newpositionsid[$tempposition[1]]=$element;
				}
				elseif (substr($key,0,12)=='dbPositionID')
				{
					$tempposition=explode("_",$key);
					$this->_dbpositionsid[$tempposition[1]]=$element;
				}
				elseif (substr($key,0,18)=='parentPositionName')
				{
					$tempposition=explode("_",$key);
					$this->_newparentpositionsname[$tempposition[1]]=$element;
				}
				elseif (substr($key,0,16) =="parentPositionID")
				{
					$tempposition=explode("_",$key);
					$this->_newparentpositionsid[$tempposition[1]]=$element;
				}
				elseif (substr($key,0,18)=='dbParentPositionID')
				{
					$tempposition=explode("_",$key);
					$this->_dbparentpositionsid[$tempposition[1]]=$element;
				}
				elseif (substr($key,0,14)=='playgroundName')
				{
					$tempplayground=explode("_",$key);
					$this->_newplaygroundname[$tempplayground[1]]=$element;
				}
				elseif (substr($key,0,19)=='playgroundShortname')
				{
					$tempplayground=explode("_",$key);
					$this->_newplaygroundshort[$tempplayground[1]]=$element;
				}
				elseif (substr($key,0,12)=='playgroundID')
				{
					$tempplayground=explode("_",$key);
					$this->_newplaygroundid[$tempplayground[1]]=$element;
				}
				elseif (substr($key,0,14)=='dbPlaygroundID')
				{
					$tempplayground=explode("_",$key);
					$this->_dbplaygroundsid[$tempplayground[1]]=$element;
				}
				elseif (substr($key,0,13)=='statisticName')
				{
					$tempstatistic=explode("_",$key);
					$this->_newstatisticsname[$tempstatistic[1]]=$element;
				}
				elseif (substr($key,0,11)=='statisticID')
				{
					$tempstatistic=explode("_",$key);
					$this->_newstatisticsid[$tempstatistic[1]]=$element;
				}
				elseif (substr($key,0,13)=='dbStatisticID')
				{
					$tempstatistic=explode("_",$key);
					$this->_dbstatisticsid[$tempstatistic[1]]=$element;
				}
				elseif (substr($key,0,14)=='personLastname')
				{
					$temppersons=explode("_",$key);
					$this->_newperson_lastname[$temppersons[1]]=$element;
				}
				elseif (substr($key,0,15)=='personFirstname')
				{
					$temppersons=explode("_",$key);
					$this->_newperson_firstname[$temppersons[1]]=$element;
				}
				elseif (substr($key,0,14)=='personNickname')
				{
					$temppersons=explode("_",$key);
					$this->_newperson_nickname[$temppersons[1]]=$element;
				}
				elseif (substr($key,0,14)=='personBirthday')
				{
					$temppersons=explode("_",$key);
					$this->_newperson_birthday[$temppersons[1]]=$element;
				}
				elseif (substr($key,0,8)=='personID')
				{
					$temppersons=explode("_",$key);
					$this->_newpersonsid[$temppersons[1]]=$element;
				}
				elseif (substr($key,0,10)=='dbPersonID')
				{
					$temppersons=explode("_",$key);
					$this->_dbpersonsid[$temppersons[1]]=$element;
				}
			}
    
    }
    
    $this->_success_text='';

			//set $this->_importType
			$this->_importType=$post['importType'];

			if (isset($post['admin']))
			{
				$this->_joomleague_admin=(int)$post['admin'];
			}
			else
			{
				$this->_joomleague_admin=62;
			}
    
    	//check project name
			if ($post['importProject'])
			{
				if (isset($post['name'])) // Project Name
				{
					$this->_name=substr($post['name'],0,100);
				}
				else
				{
					JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','projectname'));
					echo "<script> alert('".JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','projectname')."'); window.history.go(-1); </script>\n";
				}

				if (empty($this->_datas['project']))
				{
					JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR','Project object is missing inside import file!!!'));
					echo "<script> alert('".JText::sprintf('JL_EXT_LMO_IMPORT_ERROR','Project object is missing inside import file!!!')."'); window.history.go(-1); </script>\n";
					return false;
				}

				if ($this->_checkProject()===false)
				{
					JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR','Projectname already exists'));
					echo "<script> alert('".JText::sprintf('JL_EXT_LMO_IMPORT_ERROR','Projectname already exists')."'); window.history.go(-1); </script>\n";
					return false;
				}
			}
			
			//check sportstype
			if ($post['importProject'] || $post['importType']=='events' || $post['importType']=='positions')
			{
				if ((isset($post['sportstype'])) && ($post['sportstype'] > 0))
				{
					$this->_sportstype_id=(int)$post['sportstype'];
				}
				elseif (isset($post['sportstypeNew']))
					{
						$this->_sportstype_new=substr($post['sportstypeNew'],0,25);
					}
					else
					{
						JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','sportstype'));
						echo "<script> alert('".JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','sportstype')."'); window.history.go(-1); </script>\n";
						return false;
					}
			}


// echo 'importData post <br>';
// echo '<pre>';
// print_r($post);
// echo '</pre>';

			//check league/season/admin/editor/publish/template
			if ($post['importProject'])
			{
				if (isset($post['league']))
				{
					$this->_league_id=(int)$post['league'];
				}
				elseif (isset($post['leagueNew']))
					{
						$this->_league_new=substr($post['leagueNew'],0,75);
					}
					else
					{
						JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','league'));
						echo "<script> alert('".JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','league')."'); window.history.go(-1); </script>\n";
						return false;
					}

				if (isset($post['season']))
				{
					$this->_season_id=(int)$post['season'];
				}
				elseif (isset($post['seasonNew']))
					{
						$this->_season_new=substr($post['seasonNew'],0,75);
					}
					else
					{
						JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','season'));
						echo "<script> alert('".JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_MISSING','season')."'); window.history.go(-1); </script>\n";
						return false;
					}

				if (isset($post['editor']))
				{
					$this->_joomleague_editor=(int)$post['editor'];
				}
				else
				{
					$this->_joomleague_editor=62;
				}

				if (isset($post['publish']))
				{
					$this->_publish=(int)$post['publish'];
				}
				else
				{
					$this->_publish=0;
				}

				if (isset($post['copyTemplate'])) // if new template set this value is 0
				{
					$this->_template_id=(int)$post['copyTemplate'];
				}
				else
				{
					$this->_template_id=0;
				}
			}

			/**
			 *
			 * Real Import Work starts here
			 *
			 */
			if ($post['importProject'] || $post['importType']=='events' || $post['importType']=='positions')
			{
				// import sportstype
				if ($this->_importSportsType()===false)
				{
					JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_DURING','sports-type'));
					return $this->_success_text;
				}
			}

			if ($post['importProject'])
			{
				// import league
				if ($this->_importLeague()===false)
				{
					JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_DURING','league'));
					return $this->_success_text;
				}

				// import season
				if ($this->_importSeason()===false)
				{
					JError::raiseWarning(500,JText::sprintf('JL_EXT_LMO_IMPORT_ERROR_DURING','season'));
					return $this->_success_text;
				}
			}

			// import events / should also work with exported events-XML without problems
			if ($this->_importEvents()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','event'));
				return $this->_success_text;
			}

			// import Statistic
			if ($this->_importStatistics()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','statistic'));
				return $this->_success_text;
			}

			// import parent positions
			if ($this->_importParentPositions()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','parent-position'));
				return $this->_success_text;
			}

			// import positions
			if ($this->_importPositions()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','position'));
				return $this->_success_text;
			}

			// import PositionEventType
			if ($this->_importPositionEventType()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','position-eventtype'));
				return $this->_success_text;
			}

			// import playgrounds
			if ($this->_importPlayground()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','playground'));
				return $this->_success_text;
			}

			// import clubs
			if ($this->_importClubs()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','club'));
				return $this->_success_text;
			}

			if ($this->_importType!='playgrounds')	// don't convert club_id if only playgrounds are imported
			{
				// convert playground Club-IDs
				if ($this->_convertNewPlaygroundIDs()===false)
				{
					JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','conversion of playground club-id'));
					return $this->_success_text;
				}
			}

			// import teams
			if ($this->_importTeams()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','team'));
				return $this->_success_text;
			}

			// import persons
			if ($this->_importPersons()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','person'));
				return $this->_success_text;
			}

			if ($post['importProject'])
			{
				// import project
				if ($this->_importProject()===false)
				{
					JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','project'));
					return $this->_success_text;
				}

				// import template
				if ($this->_importTemplate()===false)
				{
					JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','template'));
					return $this->_success_text;
				}
			}
			
			// import divisions
			if ($this->_importDivisions()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','division'));
				return $this->_success_text;
			}

			// import project positions
			if ($this->_importProjectPositions()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','projectpositions'));
				return $this->_success_text;
			}

			// import project referees
			if ($this->_importProjectReferees()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','projectreferees'));
				return $this->_success_text;
			}
			

			// import projectteam
			if ($this->_importProjectTeam()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','projectteam'));
				return $this->_success_text;
			}			

// import teamplayers
			if ($this->_importTeamPlayer()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','teamplayer'));
				return $this->_success_text;
			}

			// import teamstaffs
			if ($this->_importTeamStaff()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','teamstaff'));
				return $this->_success_text;
			}

			// import teamtrainingdata
			if ($this->_importTeamTraining()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','teamtraining'));
				return $this->_success_text;
			}

			// import rounds
			if ($this->_importRounds()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','round'));
				return $this->_success_text;
			}

//     echo '_importRounds<pre>';
//     print_r($this->_convertRoundID,true);
//     echo '</pre>';
    
			// import matches
			// last to import cause needs a lot of imports and conversions inside the database before match-conversion may be done
			// after this import only the matchplayers,-staffs,-referees and -events can be imported cause they need existing
			//
			// imported matches
			if ($this->_importMatches()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','match'));
				return $this->_success_text;
			}

			// import MatchPlayer
			if ($this->_importMatchPlayer()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','matchplayer'));
				return $this->_success_text;
			}

			// import MatchStaff
			if ($this->_importMatchStaff()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','matchstaff'));
				return $this->_success_text;
			}

			// import MatchReferee
			if ($this->_importMatchReferee()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','matchreferee'));
				return $this->_success_text;
			}

			// import MatchEvent
			if ($this->_importMatchEvent()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','matchevent'));
				return $this->_success_text;
			}

			// import PositionStatistic
			if ($this->_importPositionStatistic()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','positionstatistic'));
				return $this->_success_text;
			}

			// import MatchStaffStatistic
			if ($this->_importMatchStaffStatistic()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','matchstaffstatistic'));
				return $this->_success_text;
			}
			
			// import MatchStatistic
			if ($this->_importMatchStatistic()===false)
			{
				JError::raiseWarning(500,JText::sprintf('JL_ADMIN_XML_ERROR_DURING','matchstatistic'));
				return $this->_success_text;
			}
			
      if ($post['importProject'])
			{
				$this->_beforeFinish();
			}

      return $this->_success_text;


          
    
    }
    
          	
function _loadData()
	{
  global $mainframe, $option;
  $this->_data =  $mainframe->getUserState( $option . 'project', 0 );
   
  return $this->_data;
	}

function _initData()
	{
	global $mainframe, $option;
  $this->_data =  $mainframe->getUserState( $option . 'project', 0 );
  return $this->_data;
	}

private function _beforeFinish()
	{
		// convert favorite teams
		$checked_fav_teams=trim($this->_getDataFromObject($this->_datas['project'],'fav_team'));
		$t_fav_team='';
		if ($checked_fav_teams!='')
		{
			$t_fav_teams=explode(",",$checked_fav_teams);
			foreach ($t_fav_teams as $value)
			{
				if (isset($this->_convertTeamID[$value])){$t_fav_team .= $this->_convertTeamID[$value].',';}
			}
			$t_fav_team=trim($t_fav_team,',');
		}
		$query="UPDATE #__joomleague_project SET fav_team='$t_fav_team' WHERE id=$this->_project_id";
		$this->_db->setQuery($query);
		$this->_db->query();
	}
	
	
	/**
	 * _getDataFromObject
	 *
	 * Get data from object
	 *
	 * @param object $obj object where we find the key
	 * @param string $key key what we find in the object
	 *
	 * @access private
	 * @since  1.5.0a
	 *
	 * @return void
	 */
	private function _getDataFromObject(&$obj,$key)
	{
		if (is_object($obj))
		{
			$t_array=get_object_vars($obj);

			if (array_key_exists($key,$t_array))
			{
				return $t_array[$key];
			}
			return false;
		}
		return false;
	}
  	
private function _checkProject()
	{
		/*
		TO BE FIXED again
		$query="	SELECT id
					FROM #__joomleague_project
					WHERE name='$this->_name' AND league_id='$this->_league_id' AND season_id='$this->_season_id'";
		*/
		$query="SELECT id FROM #__joomleague_project WHERE name='".addslashes(stripslashes($this->_name))."'";
		$this->_db->setQuery($query);
		$this->_db->query();
		if ($this->_db->getNumRows() > 0){return false;}
		return true;
	}

private function _getRoundName($round_id)
	{
		$query='SELECT name FROM #__joomleague_round WHERE id='.(int)$round_id;
		$this->_db->setQuery($query);
		$this->_db->query();
		if ($this->_db->getAffectedRows())
		{
			$result=$this->_db->loadResult();
			return $result;
		}
		return null;
	}
	
	
private function _getObjectName($tableName,$id,$usedFieldName='')
	{
		$fieldName=($usedFieldName=='') ? 'name' : $usedFieldName;
		$query="SELECT $fieldName FROM #__joomleague_$tableName WHERE id=$id";
		$this->_db->setQuery($query);
		if ($result=$this->_db->loadResult()){return $result;}
		return JText::sprintf('Item with ID [%1$s] not found inside [#__joomleague_%2$s]',$id,$tableName)." $query";
	}
    
	private function _importSportsType()
	{
		$my_text='';
		if (!empty($this->_sportstype_new))
		{
			$query="SELECT id FROM #__joomleague_sports_type WHERE name='".addslashes(stripslashes($this->_sportstype_new))."'";
			$this->_db->setQuery($query);
			if ($sportstypeObject=$this->_db->loadObject())
			{
				$this->_sportstype_id=$sportstypeObject->id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf('Using existing sportstype data: %1$s',"</span><strong>$this->_sportstype_new</strong>");
				$my_text .= '<br />';
			}
			else
			{
				$p_sportstype =& $this->getTable('sportstype');
				$p_sportstype->set('name',trim($this->_sportstype_new));

				if ($p_sportstype->store()===false)
				{
					$my_text .= '<span style="color:red"><strong>';
					$my_text .= JText::_('Error in function _importSportsType').'</strong></span><br />';
					$my_text .= JText::sprintf('Sportstypename: %1$s',JText::_($this->_sportstype_new)).'<br />';
					$my_text .= JText::sprintf('Error-Text #%1$s#',$this->_db->getErrorMsg()).'<br />';
					$my_text .= '<pre>'.print_r($p_sportstype,true).'</pre>';
					$this->_success_text['Importing sportstype data:']=$my_text;
					return false;
				}
				else
				{
					$insertID=$this->_db->insertid();
					$this->_sportstype_id=$insertID;
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf('Created new sportstype data: %1$s',"</span><strong>$this->_sportstype_new</strong>");
					$my_text .= '<br />';
				}
			}
		}
		else
		{
			$my_text .= '<span style="color:orange">';
			$my_text .= JText::sprintf(	'Using existing sportstype data: %1$s',
										'</span><strong>'.JText::_($this->_getObjectName('sports_type',$this->_sportstype_id)).'</strong>');
			$my_text .= '<br />';
		}
		$this->_success_text['Importing sportstype data:']=$my_text;
		return true;
	}
  
  
	private function _importLeague()
	{
		$my_text='';
		if (!empty($this->_league_new))
		{
			$query="SELECT id FROM #__joomleague_league WHERE name='".addslashes(stripslashes($this->_league_new))."'";
			$this->_db->setQuery($query);

			if ($leagueObject=$this->_db->loadObject())
			{
				$this->_league_id=$leagueObject->id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf('Using existing league data: %1$s',"</span><strong>$this->_league_new</strong>");
				$my_text .= '<br />';
			}
			else
			{
				$p_league =& $this->getTable('league');
				$p_league->set('name',trim($this->_league_new));
				$p_league->set('alias',JFilterOutput::stringURLSafe($this->_league_new));
				//$p_league->set('country',$this->_league_new_country);

				if ($p_league->store()===false)
				{
					$my_text .= '<span style="color:red"><strong>';
					$my_text .= JText::_('Error in function _importLeague').'</strong></span><br />';
					$my_text .= JText::sprintf('Leaguenname: %1$s',$this->_league_new).'<br />';
					$my_text .= JText::sprintf('Error-Text #%1$s#',$this->_db->getErrorMsg()).'<br />';
					$my_text .= '<pre>'.print_r($p_league,true).'</pre>';
					$this->_success_text['Importing league data:']=$my_text;
					return false;
				}
				else
				{
					$insertID=$this->_db->insertid();
					$this->_league_id=$insertID;
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf('Created new league data: %1$s',"</span><strong>$this->_league_new</strong>");
					$my_text .= '<br />';
				}
			}
		}
		else
		{
			$my_text .= '<span style="color:orange">';
			$my_text .= JText::sprintf(	'Using existing league data: %1$s',
										'</span><strong>'.$this->_getObjectName('league',$this->_league_id).'</strong>');
			$my_text .= '<br />';
		}
		$this->_success_text['Importing league data:']=$my_text;
		return true;
	}

	private function _importSeason()
	{
		$my_text='';
		if (!empty($this->_season_new))
		{
			$query="SELECT id FROM #__joomleague_season WHERE name='".addslashes(stripslashes($this->_season_new))."'";
			$this->_db->setQuery($query);

			if ($seasonObject=$this->_db->loadObject())
			{
				$this->_season_id=$seasonObject->id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf('Using existing season data: %1$s',"</span><strong>$this->_season_new</strong>");
				$my_text .= '<br />';
			}
			else
			{
				$p_season =& $this->getTable('season');
				$p_season->set('name',trim($this->_season_new));
				$p_season->set('alias',JFilterOutput::stringURLSafe($this->_season_new));

				if ($p_season->store()===false)
				{
					$my_text .= '<span style="color:red"><strong>';
					$my_text .= JText::_('Error in function _importSeason').'</strong></span><br />';
					$my_text .= JText::sprintf('Seasonname: %1$s',$this->_season_new).'<br />';
					$my_text .= JText::sprintf('Error-Text #%1$s#',$this->_db->getErrorMsg()).'<br />';
					$my_text .= '<pre>'.print_r($p_season,true).'</pre>';
					$this->_success_text['Importing season data:']=$my_text;
					return false;
				}
				else
				{
					$insertID=$this->_db->insertid();
					$this->_season_id=$insertID;
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf('Created new season data: %1$s',"</span><strong>$this->_season_new</strong>");
					$my_text .= '<br />';
				}
			}
		}
		else
		{
			$my_text .= '<span style="color:orange">';
			$my_text .= JText::sprintf(	'Using existing season data: %1$s',
										'</span><strong>'.$this->_getObjectName('season',$this->_season_id).'</strong>');
			$my_text .= '<br />';
		}
		$this->_success_text['Importing season data:']=$my_text;
		return true;
	}

	private function _importEvents()
	{
		$my_text='';
		if (!isset($this->_datas['event']) || count($this->_datas['event'])==0){return true;}
		if ((!isset($this->_neweventsid) || count($this->_neweventsid)==0) &&
			(!isset($this->_dbeventsid) || count($this->_dbeventsid)==0)){return true;}
		if (!empty($this->_dbeventsid))
		{
			foreach ($this->_dbeventsid AS $key => $id)
			{
				$oldID=$this->_getDataFromObject($this->_datas['event'][$key],'id');
				$this->_convertEventID[$oldID]=$id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing event data: %1$s',
											'</span><strong>'.JText::_($this->_getObjectName('eventtype',$id)).'</strong>');
				$my_text .= '<br />';
			}
		}

		if (!empty($this->_neweventsid))
		{
			foreach ($this->_neweventsid AS $key => $id)
			{
				$p_eventtype =&  $this->getTable('eventtype');
				$import_event=$this->_datas['event'][$key];
				$oldID=$this->_getDataFromObject($import_event,'id');
				$alias=$this->_getDataFromObject($import_event,'alias');
				$p_eventtype->set('name',trim($this->_neweventsname[$key]));
				$p_eventtype->set('icon',$this->_getDataFromObject($import_event,'icon'));
				$p_eventtype->set('parent',$this->_getDataFromObject($import_event,'parent'));
				$p_eventtype->set('splitt',$this->_getDataFromObject($import_event,'splitt'));
				$p_eventtype->set('direction',$this->_getDataFromObject($import_event,'direction'));
				$p_eventtype->set('double',$this->_getDataFromObject($import_event,'double'));
				$p_eventtype->set('sports_type_id',$this->_sportstype_id);
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_eventtype->set('alias',$alias);
				}
				else
				{
					$p_eventtype->set('alias',JFilterOutput::stringURLSafe($this->_getDataFromObject($p_eventtype,'name')));
				}
				$query="SELECT id,name FROM #__joomleague_eventtype WHERE name='".addslashes(stripslashes($p_eventtype->name))."'";
				$this->_db->setQuery($query); $this->_db->query();
				if ($object=$this->_db->loadObject())
				{
					$this->_convertEventID[$oldID]=$object->id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf('Using existing eventtype data: %1$s','</span><strong>'.JText::_($object->name).'</strong>');
					$my_text .= '<br />';
				}
				else
				{
					if ($p_eventtype->store()===false)
					{
						$my_text .= 'error on event import: ';
						$my_text .= $oldID;
						$my_text .= "<br />Error: _importEvents<br />#$my_text#<br />#<pre>".print_r($p_eventtype,true).'</pre>#';
						$this->_success_text['Importing general event data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertEventID[$oldID]=$insertID;
						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf('Created new eventtype data: %1$s','</span><strong>'.JText::_($p_eventtype->name).'</strong>');
						$my_text .= '<br />';
					}
				}
			}
		}
		$this->_success_text['Importing general event data:']=$my_text;
		return true;
	}

	private function _importStatistics()
	{
		$my_text='';
		if (!isset($this->_datas['statistic']) || count($this->_datas['statistic'])==0){return true;}
		if ((!isset($this->_newstatisticsid) || count($this->_newstatisticsid)==0) &&
			(!isset($this->_dbstatisticsid) || count($this->_dbstatisticsid)==0)){return true;}

		if (!empty($this->_dbstatisticsid))
		{
			foreach ($this->_dbstatisticsid AS $key => $id)
			{
				$oldID=$this->_getDataFromObject($this->_datas['statistic'][$key],'id');
				$this->_convertStatisticID[$oldID]=$id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing statistic data: %1$s',
											'</span><strong>'.JText::_($this->_getObjectName('statistic',$id)).'</strong>');
				$my_text .= '<br />';
			}
		}

		if (!empty($this->_newstatisticsid))
		{
			foreach ($this->_newstatisticsid AS $key => $id)
			{
				$p_statistic =&  $this->getTable('statistic');
				$import_statistic=$this->_datas['statistic'][$key];
				$oldID=$this->_getDataFromObject($import_statistic,'id');
				$alias=$this->_getDataFromObject($import_statistic,'alias');
				$p_statistic->set('name',trim($this->_newstatisticsname[$key]));
				$p_statistic->set('short',$this->_getDataFromObject($import_statistic,'short'));
				$p_statistic->set('icon',$this->_getDataFromObject($import_statistic,'icon'));
				$p_statistic->set('class',$this->_getDataFromObject($import_statistic,'class'));
				$p_statistic->set('calculated',$this->_getDataFromObject($import_statistic,'calculated'));
				$p_statistic->set('params',$this->_getDataFromObject($import_statistic,'params'));
				$p_statistic->set('baseparams',$this->_getDataFromObject($import_statistic,'baseparams'));
				$p_statistic->set('note',$this->_getDataFromObject($import_statistic,'note'));
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_statistic->set('alias',$alias);
				}
				else
				{
					$p_statistic->set('alias',JFilterOutput::stringURLSafe($this->_getDataFromObject($p_statistic,'name')));
				}
				$query="SELECT * FROM #__joomleague_statistic WHERE name='".addslashes(stripslashes($p_statistic->name))."' AND class='".addslashes(stripslashes($p_statistic->class))."'";
				$this->_db->setQuery($query);
				$this->_db->query();
				if ($object=$this->_db->loadObject())
				{
					$this->_convertStatisticID[$oldID]=$object->id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf('Using existing statistic data: %1$s','</span><strong>'.JText::_($object->name).'</strong>');
					$my_text .= '<br />';
				}
				else
				{
					if ($p_statistic->store()===false)
					{
						$my_text .= 'error on statistic import: ';
						$my_text .= $oldID;
						$my_text .= "<br />Error: _importStatistics<br />#$my_text#<br />#<pre>".print_r($p_statistic,true).'</pre>#';
						$this->_success_text['Importing general statistic data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertStatisticID[$oldID]=$insertID;
						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf('Created new statistic data: %1$s','</span><strong>'.JText::_($p_statistic->name).'</strong>');
						$my_text .= '<br />';
					}
				}
			}
		}
		$this->_success_text['Importing statistic data:']=$my_text;
		return true;
	}

	private function _importParentPositions()
	{
		$my_text='';
		if (!isset($this->_datas['parentposition']) || count($this->_datas['parentposition'])==0){return true;}
		if ((!isset($this->_newparentpositionsid) || count($this->_newparentpositionsid)==0) &&
			(!isset($this->_dbparentpositionsid) || count($this->_dbparentpositionsid)==0)){return true;}
		if (!empty($this->_dbparentpositionsid))
		{
			foreach ($this->_dbparentpositionsid AS $key => $id)
			{
				$oldID=$this->_getDataFromObject($this->_datas['parentposition'][$key],'id');
				$this->_convertParentPositionID[$oldID]=$id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing parentposition data: %1$s',
											'</span><strong>'.JText::_($this->_getObjectName('position',$id)).'</strong>');
				$my_text .= '<br />';
			}
		}

		if (!empty($this->_newparentpositionsid))
		{
			foreach ($this->_newparentpositionsid AS $key => $id)
			{
				$p_position =&  $this->getTable('position');
				$import_position=$this->_datas['parentposition'][$key];
				$oldID=$this->_getDataFromObject($import_position,'id');
				$alias=$this->_getDataFromObject($import_position,'alias');
				$p_position->set('name',trim($this->_newparentpositionsname[$key]));
				$p_position->set('parent_id',0);
				$p_position->set('persontype',$this->_getDataFromObject($import_position,'persontype'));
				$p_position->set('sports_type_id',$this->_sportstype_id);
				$p_position->set('published',1);
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_position->set('alias',$alias);
				}
				else
				{
					$p_position->set('alias',JFilterOutput::stringURLSafe($this->_getDataFromObject($p_position,'name')));
				}
				$query="SELECT id,name FROM #__joomleague_position WHERE name='".addslashes(stripslashes($p_position->name))."' AND parent_id=0";
				$this->_db->setQuery($query);
				$this->_db->query();
				if ($this->_db->getAffectedRows())
				{
					$p_position->load($this->_db->loadResult());
					$this->_convertParentPositionID[$oldID]=$p_position->id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf('Using existing parent-position data: %1$s','</span><strong>'.JText::_($p_position->name).'</strong>');
					$my_text .= '<br />';
				}
				else
				{
					if ($p_position->store()===false)
					{
						$my_text .= 'error on parent-position import: ';
						$my_text .= $oldID;
						$my_text .= "<br />Error: _importParentPositions<br />#$my_text#<br />#<pre>".print_r($p_position,true).'</pre>#';
						$this->_success_text['Importing general parent-position data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertParentPositionID[$oldID]=$insertID;
						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf('Created new parent-position data: %1$s','</span><strong>'.JText::_($p_position->name).'</strong>');
						$my_text .= '<br />';
					}
				}
			}
		}
		$this->_success_text['Importing general parent-position data:']=$my_text;
		return true;
	}

	private function _importPositions()
	{
		$my_text='';
		if (!isset($this->_datas['position']) || count($this->_datas['position'])==0){return true;}
		if ((!isset($this->_newpositionsid) || count($this->_newpositionsid)==0) &&
			(!isset($this->_dbpositionsid) || count($this->_dbpositionsid)==0)){return true;}

		if (!empty($this->_dbpositionsid))
		{
			foreach ($this->_dbpositionsid AS $key => $id)
			{
				$oldID=$this->_getDataFromObject($this->_datas['position'][$key],'id');
				$this->_convertPositionID[$oldID]=$id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing position data: %1$s',
											'</span><strong>'.JText::_($this->_getObjectName('position',$id)).'</strong>');
				$my_text .= '<br />';
			}
		}

		if (!empty($this->_newpositionsid))
		{
			foreach ($this->_newpositionsid AS $key => $id)
			{
				$p_position =&  $this->getTable('position');
				$import_position=$this->_datas['position'][$key];
				$oldID=$this->_getDataFromObject($import_position,'id');
				$alias=$this->_getDataFromObject($import_position,'alias');
				$p_position->set('name',trim($this->_newpositionsname[$key]));
				$oldParentPositionID=$this->_getDataFromObject($import_position,'parent_id');
				if (isset($this->_convertParentPositionID[$oldParentPositionID]))
				{
					$p_position->set('parent_id',$this->_convertParentPositionID[$oldParentPositionID]);
				} else { 
					$p_position->set('parent_id', 0);
				}
				//$p_position->set('parent_id',$this->_convertParentPositionID[(int)$this->_getDataFromObject($import_position,'parent_id')]);
				$p_position->set('persontype',$this->_getDataFromObject($import_position,'persontype'));
				$p_position->set('sports_type_id',$this->_sportstype_id);
				$p_position->set('published',1);
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_position->set('alias',$alias);
				}
				else
				{
					$p_position->set('alias',JFilterOutput::stringURLSafe($this->_getDataFromObject($p_position,'name')));
				}
				$query="SELECT id,name FROM #__joomleague_position WHERE name='".addslashes(stripslashes($p_position->name))."' AND parent_id=$p_position->parent_id";
				$this->_db->setQuery($query);
				$this->_db->query();
				if ($this->_db->getAffectedRows())
				{
					$p_position->load($this->_db->loadResult());
					$this->_convertPositionID[$oldID]=$p_position->id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf('Using existing position data: %1$s','</span><strong>'.JText::_($p_position->name).'</strong>');
					$my_text .= '<br />';
				}
				else
				{
					if ($p_position->store()===false)
					{
						$my_text .= 'error on position import: ';
						$my_text .= $oldID;
						$my_text .= "<br />Error: _importPositions<br />#$my_text#<br />#<pre>".print_r($p_position,true).'</pre>#';
						$this->_success_text['Importing general position data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertPositionID[$oldID]=$insertID;
						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf('Created new position data: %1$s','</span><strong>'.JText::_($p_position->name).'</strong>');
						$my_text .= '<br />';
					}
				}
			}
		}
		$this->_success_text['Importing general position data:']=$my_text;
		return true;
	}

	private function _importPositionEventType()
	{
		$my_text='';
		if (!isset($this->_datas['positioneventtype']) || count($this->_datas['positioneventtype'])==0){return true;}

		if (!isset($this->_datas['event']) || count($this->_datas['event'])==0){return true;}
		if ((!isset($this->_neweventsid) || count($this->_neweventsid)==0) &&
			(!isset($this->_dbeventsid) || count($this->_dbeventsid)==0)){return true;}
		if (!isset($this->_datas['position']) || count($this->_datas['position'])==0){return true;}
		if ((!isset($this->_newpositionsid) || count($this->_newpositionsid)==0) &&
			(!isset($this->_dbpositionsid) || count($this->_dbpositionsid)==0)){return true;}

		foreach ($this->_datas['positioneventtype'] as $key => $positioneventtype)
		{
			$import_positioneventtype=$this->_datas['positioneventtype'][$key];
			$oldID=$this->_getDataFromObject($import_positioneventtype,'id');
			$p_positioneventtype =& $this->getTable('positioneventtype');
			$oldEventID=$this->_getDataFromObject($import_positioneventtype,'eventtype_id');
			$oldPositionID=$this->_getDataFromObject($import_positioneventtype,'position_id');
			if (!isset($this->_convertEventID[$oldEventID]) ||
				!isset($this->_convertPositionID[$oldPositionID]))
			{
				$my_text .= '<span style="color:red">';
				$my_text .= JText::sprintf(	'Skipping import of PositionEventtype-ID [%1$s]. Old-EventID: [%2$s] - Old-PositionID: [%3$s]',
											"</span><strong>$oldID</strong><span style='color:red'>",
											"</span><strong>$oldEventID</strong><span style='color:red'>",
											"</span><strong>$oldPositionID</strong>").'<br />';
				continue;
			}
			$p_positioneventtype->set('position_id',$this->_convertPositionID[$oldPositionID]);
			$p_positioneventtype->set('eventtype_id',$this->_convertEventID[$oldEventID]);
			$query ="SELECT id
							FROM #__joomleague_position_eventtype
							WHERE	position_id='$p_positioneventtype->position_id' AND
									eventtype_id='$p_positioneventtype->eventtype_id'";
			$this->_db->setQuery($query);
			$this->_db->query();
			if ($object=$this->_db->loadObject())
			{
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing positioneventtype data - Position: [%1$s] - Event: [%2$s]',
											'</span><strong>'.JText::_($this->_getObjectName('position',$p_positioneventtype->position_id)).'</strong><span style="color:orange">',
											'</span><strong>'.JText::_($this->_getObjectName('eventtype',$p_positioneventtype->eventtype_id)).'</strong>');
				$my_text .= '<br />';
			}
			else
			{
				if ($p_positioneventtype->store()===false)
				{
					$my_text .= 'error on PositionEventType import: ';
					$my_text .= '#'.$oldID.'#';
					$my_text .= "<br />Error: _importPositionEventType<br />#$my_text#<br />#<pre>".print_r($p_positioneventtype,true).'</pre>#';
					$this->_success_text['Importing positioneventtype data:']=$my_text;
					return false;
				}
				else
				{
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Created new positioneventtype data. Position: [%1$s] - Event: [%2$s]',
												'</span><strong>'.JText::_($this->_getObjectName('position',$p_positioneventtype->position_id)).'</strong><span style="color:green">',
												'</span><strong>'.JText::_($this->_getObjectName('eventtype',$p_positioneventtype->eventtype_id)).'</strong>');
					$my_text .= '<br />';
				}
			}
		}
		$this->_success_text['Importing positioneventtype data:']=$my_text;
		return true;
	}

	private function _importPlayground()
	{
		$my_text='';
		if (!isset($this->_datas['playground']) || count($this->_datas['playground'])==0){return true;}
		if ((!isset($this->_newplaygroundid) || count($this->_newplaygroundid)==0) &&
			(!isset($this->_dbplaygroundsid) || count($this->_dbplaygroundsid)==0)){return true;}

		if (!empty($this->_dbplaygroundsid))
		{
			foreach ($this->_dbplaygroundsid AS $key => $id)
			{
				$oldID=$this->_getDataFromObject($this->_datas['playground'][$key],'id');
				$this->_convertPlaygroundID[$oldID]=$id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing playground data: %1$s',
											'</span><strong>'.$this->_getObjectName('playground',$id).'</strong>');
				$my_text .= '<br />';
			}
		}
		if (!empty($this->_newplaygroundid))
		{
			foreach ($this->_newplaygroundid AS $key => $id)
			{
				$p_playground =&  $this->getTable('playground');
				$import_playground=$this->_datas['playground'][$key];
				$oldID=$this->_getDataFromObject($import_playground,'id');
				$alias=$this->_getDataFromObject($import_playground,'alias');
				$p_playground->set('name',trim($this->_newplaygroundname[$key]));
				$p_playground->set('short_name',$this->_newplaygroundshort[$key]);
				$p_playground->set('address',$this->_getDataFromObject($import_playground,'address'));
				$p_playground->set('zipcode',$this->_getDataFromObject($import_playground,'zipcode'));
				$p_playground->set('city',$this->_getDataFromObject($import_playground,'city'));
				$p_playground->set('country',$this->_getDataFromObject($import_playground,'country'));
				$p_playground->set('max_visitors',$this->_getDataFromObject($import_playground,'max_visitors'));
				$p_playground->set('website',$this->_getDataFromObject($import_playground,'website'));
				$p_playground->set('picture',$this->_getDataFromObject($import_playground,'picture'));
				$p_playground->set('notes',$this->_getDataFromObject($import_playground,'notes'));
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_playground->set('alias',$alias);
				}
				else
				{
					$p_playground->set('alias',JFilterOutput::stringURLSafe($this->_getDataFromObject($p_playground,'name')));
				}
				if (array_key_exists((int)$this->_getDataFromObject($import_playground,'country'),$this->_convertCountryID))
				{
					$p_playground->set('country',(int)$this->_convertCountryID[(int)$this->_getDataFromObject($import_playground,'country')]);
				}
				else
				{
					$p_playground->set('country',$this->_getDataFromObject($import_playground,'country'));
				}
				if ($this->_importType!='playgrounds')	// force club_id to be set to default if only playgrounds are imported
				{
					//if (!isset($this->_getDataFromObject($import_playground,'club_id')))
					{
						$p_playground->set('club_id',$this->_getDataFromObject($import_playground,'club_id'));
					}
				}
				$query="SELECT id,name FROM #__joomleague_playground WHERE name='".addslashes(stripslashes($p_playground->name))."'";
				$this->_db->setQuery($query); $this->_db->query();
				if ($object=$this->_db->loadObject())
				{
					$this->_convertPlaygroundID[$oldID]=$object->id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf('Using existing playground data: %1$s',"</span><strong>$object->name</strong>");
					$my_text .= '<br />';
				}
				else
				{
					if ($p_playground->store()===false)
					{
						$my_text .= '<span style="color:red"><strong>';
						$my_text .= JText::_('Error in function _importPlayground').'</strong></span><br />';
						$my_text .= JText::sprintf('Playgroundname: %1$s',$p_playground->name).'<br />';
						$my_text .= JText::sprintf('Error-Text #%1$s#',$this->_db->getErrorMsg()).'<br />';
						$my_text .= '<pre>'.print_r($p_playground,true).'</pre>';
						$this->_success_text['Importing general playground data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertPlaygroundID[$oldID]=$insertID;
						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf('Created new playground data: %1$s',"</span><strong>$p_playground->name</strong>");
						$my_text .= '<br />';
					}
				}
			}
		}
		$this->_success_text['Importing general playground data:']=$my_text;
		return true;
	}
/*
	private function _importClubs()
	{
		$my_text='';
		if (!isset($this->_datas['club']) || count($this->_datas['club'])==0){return true;}
		if ((!isset($this->_newclubsid) || count($this->_newclubsid)==0) &&
			(!isset($this->_dbclubsid) || count($this->_dbclubsid)==0)){return true;}

		if (!empty($this->_dbclubsid))
		{
			foreach ($this->_dbclubsid AS $key => $id)
			{
				if (empty($this->_newclubs[$key]))
				{
					$oldID=$this->_getDataFromObject($this->_datas['club'][$key],'id');
					$this->_convertClubID[$oldID]=$id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf(	'Using existing club data: %1$s - %2$s',
												'</span><strong>'.$this->_getObjectName('club',$id).'</strong>',
												''.$this->_getObjectName('club',$id,'country').''
												);
					$my_text .= '<br />';
				}
			}
		}
//To Be fixed: Falls Verein neu angelegt wird, muss auch das Team neu angelegt werden.
//echo '2<pre>'.print_r($this->_newclubsid,true).'</pre>';
//echo '3<pre>'.print_r($this->_newclubs,true).'</pre>';
//echo '4<pre>'.print_r($this->_createclubsid,true).'</pre>';
		if (!empty($this->_newclubsid))
		{
			foreach ($this->_newclubsid AS $key => $id)
			{
				$p_club =& $this->getTable('club');
				foreach ($this->_datas['club'] AS $dClub)
				{
					if ($dClub->id==$id)
					{
						$import_club=$dClub;
						break;
					}
				}
				$oldID=$this->_getDataFromObject($import_club,'id');
				$alias=$this->_getDataFromObject($import_club,'alias');
				$p_club->set('name',$this->_newclubs[$key]);
				$p_club->set('admin',$this->_joomleague_admin);
				$p_club->set('address',$this->_getDataFromObject($import_club,'address'));
				$p_club->set('zipcode',$this->_getDataFromObject($import_club,'zipcode'));
				$p_club->set('location',$this->_getDataFromObject($import_club,'location'));
				$p_club->set('state',$this->_getDataFromObject($import_club,'state'));
				$p_club->set('country',$this->_newclubscountry[$key]);
				$p_club->set('founded',$this->_getDataFromObject($import_club,'founded'));
				$p_club->set('phone',$this->_getDataFromObject($import_club,'phone'));
				$p_club->set('fax',$this->_getDataFromObject($import_club,'fax'));
				$p_club->set('email',$this->_getDataFromObject($import_club,'email'));
				$p_club->set('website',$this->_getDataFromObject($import_club,'website'));
				$p_club->set('president',$this->_getDataFromObject($import_club,'president'));
				$p_club->set('manager',$this->_getDataFromObject($import_club,'manager'));
				$p_club->set('logo_big',$this->_getDataFromObject($import_club,'logo_big'));
				$p_club->set('logo_middle',$this->_getDataFromObject($import_club,'logo_middle'));
				$p_club->set('logo_small',$this->_getDataFromObject($import_club,'logo_small'));
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_club->set('alias',$alias);
				}
				else
				{
					$p_club->set('alias',JFilterOutput::stringURLSafe($this->_getDataFromObject($p_club,'name')));
				}
				if ($this->_importType!='clubs')	// force playground_id to be set to default if only clubs are imported
				{
					if (($this->import_version=='NEW') && ($import_club->standard_playground > 0))
					{
						if (isset($this->_convertPlaygroundID[(int)$this->_getDataFromObject($import_club,'standard_playground')]))
						{
							$p_club->set('standard_playground',(int)$this->_convertPlaygroundID[(int)$this->_getDataFromObject($import_club,'standard_playground')]);
						}
					}
				}
				if (($this->import_version=='NEW') && ($import_club->extended!=''))
				{
					$p_club->set('extended',$this->_getDataFromObject($import_club,'extended'));
				}
				$query="SELECT	id,
								name,
								country
						FROM #__joomleague_club
						WHERE	name='".addslashes(stripslashes($p_club->name))."' AND
								country='$p_club->country'";
				$this->_db->setQuery($query); $this->_db->query();
				if ($object=$this->_db->loadObject())
				{
					$this->_convertClubID[$oldID]=$object->id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf('Using existing club data: %1$s',"</span><strong>$object->name</strong>");
					$my_text .= '<br />';
				}
				else
				{
					if ($p_club->store()===false)
					{
						$my_text .= '<span style="color:red"><strong>';
						$my_text .= JText::_('Error in function importClubs').'</strong></span><br />';
						$my_text .= JText::sprintf('Clubname: %1$s',$p_club->name).'<br />';
						$my_text .= JText::sprintf('Error-Text #%1$s#',$this->_db->getErrorMsg()).'<br />';
						$my_text .= '<pre>'.print_r($p_club,true).'</pre>';
						$this->_success_text['Importing general club data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertClubID[$oldID]=$insertID;
						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf(	'Created new club data: %1$s - %2$s',
													"</span><strong>$p_club->name</strong>",
													$p_club->country
													);
						$my_text .= '<br />';
					}
					
// 					$my_text .= '<span style="color:green">';
// 		      $my_text .= JText::sprintf('old / new Clubid: %1$s , %2$s',"</span><strong>$oldId</strong>","</span><strong>$insertID</strong>");
// 		      $my_text .= '<br />';
		      
				}
			}
		}
		
// 		echo '_importClubs _convertClubID<pre>';
//     print_r($this->_convertClubID);
//     echo '</pre>';
		
		$this->_success_text['Importing general club data:']=$my_text;
		return true;
	}
*/

	private function _convertNewPlaygroundIDs()
	{
		$my_text='';
		$converted=false;
		if (isset($this->_convertPlaygroundID) && !empty($this->_convertPlaygroundID))
		{
			foreach ($this->_convertPlaygroundID AS $key => $new_pg_id)
			{
				$p_playground=$this->_getPlaygroundRecord($new_pg_id);
				foreach ($this->_convertClubID AS $key => $new_club_id)
				{
					if (isset($p_playground->club_id) && ($p_playground->club_id ==$key))
					{
						if ($this->_updatePlaygroundRecord($new_club_id,$new_pg_id))
						{
							$converted=true;
							$my_text .= '<span style="color:green">';
							$my_text .= JText::sprintf(	'Converted club-info %1$s in imported playground %2$s',
														'</span><strong>'.$this->_getClubName($new_club_id).'</strong><span style="color:green">',
														"</span><strong>$p_playground->name</strong>");
							$my_text .= '<br />';
						}
						break;
					}
				}
			}
			if (!$converted){$my_text .= '<span style="color:green">'.JText::_('Nothing needed to be converted').'<br />';}
			$this->_success_text['Converting new playground club-IDs of new playground data:']=$my_text;
		}
		return true;
	}

	private function _importTeams()
	{
		$my_text='';
		if (!isset($this->_datas['team']) || count($this->_datas['team'])==0){return true;}
		if ((!isset($this->_newteams) || count($this->_newteams)==0) &&
			(!isset($this->_dbteamsid) || count($this->_dbteamsid)==0)){return true;}

		if (!empty($this->_dbteamsid))
		{
			foreach ($this->_dbteamsid AS $key => $id)
			{
				if (empty($this->_newteams[$key]))
				{
					$oldID=$this->_getDataFromObject($this->_datas['team'][$key],'id');
					$this->_convertTeamID[$oldID]=$id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf(	'Using existing team data: %1$s - [%2$s] - [%3$s] - [%4$s]',
												'</span><strong>'.$this->_getObjectName('team',$id).'</strong>',
												''.$this->_getObjectName('team',$id,'short_name').'',
												''.$this->_getObjectName('team',$id,'middle_name').'',
												''.$this->_getObjectName('team',$id,'info').''
												);
					$my_text .= '<br />';
				}
			}
		}
//To Be fixed: Falls Verein neu angelegt wird, muss auch das Team neu angelegt werden.
/*
echo '1<pre>'.print_r($this->_dbclubsid,true).'</pre>';
echo '2<pre>'.print_r($this->_newclubs,true).'</pre>';
echo '3<pre>'.print_r($this->_newclubsid,true).'</pre>';
echo '4<pre>'.print_r($this->_dbteamsid,true).'</pre>';
echo '5<pre>'.print_r($this->_newteams,true).'</pre>';
echo '6<pre>'.print_r($this->_convertClubID,true).'</pre>';
*/
		if (!empty($this->_newteams))
		{
			foreach ($this->_newteams AS $key => $value)
			{
				$p_team =& $this->getTable('team');
				$import_team=$this->_datas['team'][$key];
				$oldID=$this->_getDataFromObject($import_team,'id');
				$alias=$this->_getDataFromObject($import_team,'alias');
				if ($this->_importType!='teams')	// force club_id to be set to default if only teams are imported
				{
					$oldClubID=$this->_getDataFromObject($import_team,'club_id');
					if ((!empty($import_team->club_id)) && (isset($this->_convertClubID[$oldClubID])))
					{
						$p_team->set('club_id',$this->_convertClubID[$oldClubID]);
					}
					else
					{
						$p_team->set('club_id',(-1));
					}
				}
				$p_team->set('name',$this->_newteams[$key]);
				$p_team->set('short_name',$this->_newteamsshort[$key]);
				$p_team->set('middle_name',$this->_newteamsmiddle[$key]);
				$p_team->set('website',$this->_getDataFromObject($import_team,'website'));
				$p_team->set('notes',$this->_getDataFromObject($import_team,'notes'));
				$p_team->set('picture',$this->_getDataFromObject($import_team,'picture'));
				$p_team->set('info',$this->_newteamsinfo[$key]);
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_team->set('alias',$alias);
				}
				else
				{
					$p_team->set('alias',JFilterOutput::stringURLSafe($this->_getDataFromObject($p_team,'name')));
				}
				if (($this->import_version=='NEW') && ($import_team->extended!=''))
				{
					$p_team->set('extended',$this->_getDataFromObject($import_team,'extended'));
				}
				$query="SELECT	id,
								name,
								short_name,
								middle_name,
								info
						FROM #__joomleague_team
						WHERE	name='".addslashes(stripslashes($p_team->name))."' AND
								middle_name='".addslashes(stripslashes($p_team->middle_name))."' AND
								info='".addslashes(stripslashes($p_team->info))."' ";
				$this->_db->setQuery($query); $this->_db->query();
				if ($object=$this->_db->loadObject())
				{
					$this->_convertTeamID[$oldID]=$object->id;
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf('Using existing team data: %1$s',"</span><strong>$object->name</strong>");
					$my_text .= '<br />';
				}
				else
				{
					if ($p_team->store()===false)
					{
						$my_text .= '<span style="color:red"><strong>';
						$my_text .= JText::_('Error in function _importTeams').'</strong></span><br />';
						$my_text .= JText::sprintf('Teamname: %1$s',$p_team->name).'<br />';
						$my_text .= JText::sprintf('Error-Text #%1$s#',$this->_db->getErrorMsg()).'<br />';
						$my_text .= '<pre>'.print_r($p_team,true).'</pre>';
						$this->_success_text['Importing general team data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertTeamID[$oldID]=$insertID;
						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf(	'Created new team data: %1$s - %2$s - %3$s - %4$s - %5$s',
													"</span><strong>$p_team->name</strong>",
													$p_team->short_name,
													$p_team->middle_name,
													$p_team->info,
													$p_team->club_id
													);
						$my_text .= '<br />';
					}
					
// 					$my_text .= '<span style="color:green">';
// 		      $my_text .= JText::sprintf('old / new Teamid: %1$s , %2$s',"</span><strong>$oldId</strong>","</span><strong>$insertID</strong>");
// 		      $my_text .= '<br />';
		      
				}
			}
		}
		
		
		
//     echo '_importTeams _convertTeamID<pre>';
//     print_r($this->_convertTeamID);
//     echo '</pre>';
    	
		$this->_success_text['Importing general team data:']=$my_text;
		return true;
	}  
  
	private function _importPersons()
	{
		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}

		$my_text='';
		if (!empty($this->_dbpersonsid))
		{
			foreach ($this->_dbpersonsid AS $key => $id)
			{
				$oldID=$this->_getDataFromObject($this->_datas['person'][$key],'id');
				$this->_convertPersonID[$oldID]=$id;
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing person data: %1$s',
											'</span><strong>'.$this->_getObjectName('person',$id,"CONCAT(lastname,',',firstname,' - ',nickname,' - ',birthday) AS name").'</strong>');
				$my_text .= '<br />';
			}
		}
		if (!empty($this->_newpersonsid))
		{
			foreach ($this->_newpersonsid AS $key => $id)
			{
				$p_person =&  $this->getTable('person');
				$import_person=$this->_datas['person'][$key];
				$oldID=$this->_getDataFromObject($import_person,'id');
				$p_person->set('lastname',trim($this->_newperson_lastname[$key]));
				$p_person->set('firstname',trim($this->_newperson_firstname[$key]));
				$p_person->set('nickname',trim($this->_newperson_nickname[$key]));
				$p_person->set('birthday',$this->_newperson_birthday[$key]);
				$p_person->set('country',$this->_getDataFromObject($import_person,'country'));
				$p_person->set('knvbnr',$this->_getDataFromObject($import_person,'knvbnr'));
				$p_person->set('height',$this->_getDataFromObject($import_person,'height'));
				$p_person->set('weight',$this->_getDataFromObject($import_person,'weight'));
				$p_person->set('picture',$this->_getDataFromObject($import_person,'picture'));
				$p_person->set('show_pic',$this->_getDataFromObject($import_person,'show_pic'));
				$p_person->set('show_persdata',$this->_getDataFromObject($import_person,'show_persdata'));
				$p_person->set('show_teamdata',$this->_getDataFromObject($import_person,'show_teamdata'));
				$p_person->set('show_on_frontend',$this->_getDataFromObject($import_person,'show_on_frontend'));
				$p_person->set('info',$this->_getDataFromObject($import_person,'info'));
				$p_person->set('notes',$this->_getDataFromObject($import_person,'notes'));
				$p_person->set('phone',$this->_getDataFromObject($import_person,'phone'));
				$p_person->set('mobile',$this->_getDataFromObject($import_person,'mobile'));
				$p_person->set('email',$this->_getDataFromObject($import_person,'email'));
				$p_person->set('website',$this->_getDataFromObject($import_person,'website'));
				$p_person->set('address',$this->_getDataFromObject($import_person,'address'));
				$p_person->set('zipcode',$this->_getDataFromObject($import_person,'zipcode'));
				$p_person->set('location',$this->_getDataFromObject($import_person,'location'));
				$p_person->set('state',$this->_getDataFromObject($import_person,'state'));
				$p_person->set('address_country',$this->_getDataFromObject($import_person,'address_country'));
				$p_person->set('extended',$this->_getDataFromObject($import_person,'extended'));
				if ($this->_importType!='persons')	// force position_id to be set to default if only persons are imported
				{
					if ($import_person->position_id > 0)
					{
						if (isset($this->_convertPositionID[(int)$this->_getDataFromObject($import_person,'position_id')]))
						{
							$p_person->set('position_id',(int)$this->_convertPositionID[(int)$this->_getDataFromObject($import_person,'position_id')]);
						}
					}
				}
				$alias=$this->_getDataFromObject($import_person,'alias');
				$aliasparts=array(trim($p_person->firstname),trim($p_person->lastname));
				$p_alias=JFilterOutput::stringURLSafe(implode(' ',$aliasparts));
				if ((isset($alias)) && (trim($alias)!=''))
				{
					$p_person->set('alias',JFilterOutput::stringURLSafe($alias));
				}
				else
				{
					$p_person->set('alias',$p_alias);
				}
				$query="	SELECT * FROM #__joomleague_person
							WHERE	firstname='".addslashes(stripslashes($p_person->firstname))."' AND
									lastname='".addslashes(stripslashes($p_person->lastname))."' AND
									nickname='".addslashes(stripslashes($p_person->nickname))."' AND
									birthday='$p_person->birthday'";
				$this->_db->setQuery($query); $this->_db->query();
				if ($object=$this->_db->loadObject())
				{
					$this->_convertPersonID[$oldID]=$object->id;
					$nameStr=!empty($object->nickname) ? '['.$object->nickname.']' : '';
					$my_text .= '<span style="color:orange">';
					$my_text .= JText::sprintf(	'Using existing person data: %1$s %2$s [%3$s] - %4$s',
												"</span><strong>$object->lastname</strong>",
												"<strong>$object->firstname</strong>",
												"<strong>$nameStr</strong>",
												"<strong>$object->birthday</strong>");
					$my_text .= '<br />';
				}
				else
				{
					if ($p_person->store()===false)
					{
						$my_text .= 'error on person import: ';
						$my_text .= $p_person->lastname.'-';
						$my_text .= $p_person->firstname.'-';
						$my_text .= $p_person->nickname.'-';
						$my_text .= $p_person->birthday;
						$my_text .= "<br />Error: _importPersons<br />#$my_text#<br />#<pre>".print_r($p_person,true).'</pre>#';
						$this->_success_text['Importing general person data:']=$my_text;
						return false;
					}
					else
					{
						$insertID=$this->_db->insertid();
						$this->_convertPersonID[$oldID]=$insertID;
						$dNameStr=((!empty($p_person->lastname)) ?
									$p_person->lastname :
									'<span style="color:orange">'.JText::_('Has no lastname').'</span>');
						$dNameStr .= ','.((!empty($p_person->firstname)) ?
									$p_person->firstname.' - ' :
									'<span style="color:orange">'.JText::_('Has no firstname').' - </span>');
						$dNameStr .= ((!empty($p_person->nickname)) ? "'".$p_person->nickname."' - " : '');
						$dNameStr .= $p_person->birthday;

						$my_text .= '<span style="color:green">';
						$my_text .= JText::sprintf('Created new person data: %1$s',"</span><strong>$dNameStr</strong>");
						$my_text .= '<br />';
					}
				}
			}
		}
		$this->_success_text['Importing general person data:']=$my_text;
		return true;
	}

	private function _importProject()
	{
		$my_text='';
		$p_project =& $this->getTable('project');
		$p_project->set('name',trim($this->_name));
		$p_project->set('alias',JFilterOutput::stringURLSafe(trim($this->_name)));
		$p_project->set('league_id',$this->_league_id);
		$p_project->set('season_id',$this->_season_id);
		$p_project->set('admin',$this->_joomleague_admin);
		$p_project->set('editor',$this->_joomleague_editor);
		$p_project->set('master_template',$this->_template_id);
		$p_project->set('sub_template_id',0);
		$p_project->set('extension',$this->_getDataFromObject($this->_datas['project'],'extension'));
		$p_project->set('serveroffset',$this->_getDataFromObject($this->_datas['project'],'serveroffset'));
		$p_project->set('project_type',$this->_getDataFromObject($this->_datas['project'],'project_type'));
		$p_project->set('teams_as_referees',$this->_getDataFromObject($this->_datas['project'],'teams_as_referees'));
		$p_project->set('sports_type_id',$this->_sportstype_id);
		$p_project->set('current_round',$this->_getDataFromObject($this->_datas['project'],'current_round'));
		$p_project->set('current_round_auto',$this->_getDataFromObject($this->_datas['project'],'current_round_auto'));
		$p_project->set('auto_time',$this->_getDataFromObject($this->_datas['project'],'auto_time'));
		$p_project->set('start_date',$this->_getDataFromObject($this->_datas['project'],'start_date'));
		$p_project->set('start_time',$this->_getDataFromObject($this->_datas['project'],'start_time'));
		$p_project->set('fav_team_color',$this->_getDataFromObject($this->_datas['project'],'fav_team_color'));
		$p_project->set('fav_team_text_color',$this->_getDataFromObject($this->_datas['project'],'fav_team_text_color'));
		$p_project->set('use_legs',$this->_getDataFromObject($this->_datas['project'],'use_legs'));
		$p_project->set('game_regular_time',$this->_getDataFromObject($this->_datas['project'],'game_regular_time'));
		$p_project->set('game_parts',$this->_getDataFromObject($this->_datas['project'],'game_parts'));
		$p_project->set('halftime',$this->_getDataFromObject($this->_datas['project'],'halftime'));
		$p_project->set('allow_add_time',$this->_getDataFromObject($this->_datas['project'],'allow_add_time'));
		$p_project->set('add_time',$this->_getDataFromObject($this->_datas['project'],'add_time'));
		$p_project->set('points_after_regular_time',$this->_getDataFromObject($this->_datas['project'],'points_after_regular_time'));
		$p_project->set('points_after_add_time',$this->_getDataFromObject($this->_datas['project'],'points_after_add_time'));
		$p_project->set('points_after_penalty',$this->_getDataFromObject($this->_datas['project'],'points_after_penalty'));
		$p_project->set('template',$this->_getDataFromObject($this->_datas['project'],'template'));
		$p_project->set('enable_sb',$this->_getDataFromObject($this->_datas['project'],'enable_sb'));
		$p_project->set('sb_catid',$this->_getDataFromObject($this->_datas['project'],'sb_catid'));
		if ($this->_publish){$p_project->set('published',1);}
		if ($p_project->store()===false)
		{
			$my_text .= '<span style="color:red"><strong>';
			$my_text .= JText::_('Error in function _importProject').'</strong></span><br />';
			$my_text .= JText::sprintf('Projectname: %1$s',$p_project->name).'<br />';
			$my_text .= JText::sprintf('Error-Text #%1$s#',$this->_db->getErrorMsg()).'<br />';
			$my_text .= '<pre>'.print_r($p_project,true).'</pre>';
			$this->_success_text['Importing general project data:']=$my_text;
			return false;
		}
		else
		{
			$insertID=$this->_db->insertid();
			$this->_project_id=$insertID;
			$my_text .= '<span style="color:green">';
			$my_text .= JText::sprintf('Created new project data: %1$s',"</span><strong>$this->_name</strong>");
			$my_text .= '<br />';
			$this->_success_text['Importing general project data:']=$my_text;
			return true;
		}
	}    
  
	/**
	 * check that all templates in default location have a corresponding record,except if project has a master template
	 *
	 */
	private function _checklist()
	{
		$project_id=$this->_project_id;
		$defaultpath=JPATH_COMPONENT_SITE.DS.'settings';
		$extensiontpath=JPATH_COMPONENT_SITE.DS.'extensions'.DS;
		$predictionTemplatePrefix='prediction';

		if (!$project_id){return;}

		// get info from project
		$query='SELECT master_template,extension FROM #__joomleague_project WHERE id='.(int)$project_id;

		$this->_db->setQuery($query);
		$params=$this->_db->loadObject();

		// if it's not a master template,do not create records.
		if ($params->master_template){return true;}

		// otherwise,compare the records with the files
		// get records
		$query='SELECT template FROM #__joomleague_template_config WHERE project_id='.(int)$project_id;

		$this->_db->setQuery($query);
		$records=$this->_db->loadResultArray();
		if (empty($records)){$records=array();}

		// first check extension template folder if template is not default
		if ((isset($params->extension)) && ($params->extension!=''))
		{
			if (is_dir($extensiontpath.$params->extension.DS.'settings'))
			{
				$xmldirs[]=$extensiontpath.$params->extension.DS.'settings';
			}
		}

		// add default folder
		$xmldirs[]=$defaultpath.DS.'default';

		// now check for all xml files in these folders
		foreach ($xmldirs as $xmldir)
		{
			if ($handle=opendir($xmldir))
			{
				/* check that each xml template has a corresponding record in the
				database for this project. If not,create the rows with default values
				from the xml file */
				while ($file=readdir($handle))
				{
					if	(	$file!='.' &&
							$file!='..' &&
							$file!='do_tipsl' &&
							strtolower(substr($file,-3))=='xml' &&
							strtolower(substr($file,0,strlen($predictionTemplatePrefix)))!=$predictionTemplatePrefix
						)
					{
						$template=substr($file,0,(strlen($file)-4));

						if ((empty($records)) || (!in_array($template,$records)))
						{
							//template not present,create a row with default values
							$params=new JLParameter(null,$xmldir.DS.$file);

							//get the values
							$defaultvalues=array();
							foreach ($params->getGroups() as $key => $group)
							{
								foreach ($params->getParams('params',$key) as $param)
								{
									$defaultvalues[]=$param[5].'='.$param[4];
								}
							}
							$defaultvalues=implode("\n",$defaultvalues);

							$query="	INSERT INTO #__joomleague_template_config (template,title,params,project_id)
													VALUES ('$template','$params->name','$defaultvalues','$project_id')";
							$this->_db->setQuery($query);
							//echo error,allows to check if there is a mistake in the template file
							if (!$this->_db->query())
							{
								$this->setError($this->_db->getErrorMsg());
								return false;
							}
							array_push($records,$template);
						}
					}
				}
				closedir($handle);
			}
		}
	}

	private function _importTemplate()
	{
		$my_text='';
		if ($this->_template_id > 0) // Uses a master template
		{
			$query_template='SELECT id,master_template FROM #__joomleague_project WHERE id='.$this->_template_id;
			$this->_db->setQuery($query_template);
			$template_row=$this->_db->loadAssoc();
			
      if ($template_row['master_template']==0)
			{
				$this->_master_template=$template_row['id'];
			}
			else
			{
				$this->_master_template=$template_row['master_template'];
			}
			
// 			$query="SELECT id,template FROM #__joomleague_template_config WHERE project_id=".$this->_master_template;
// 			$this->_db->setQuery($query);
// 			$rows=$this->_db->loadObjectList();
// 			foreach ($rows AS $row)
// 			{
// 				$p_template =& $this->getTable('template');
// 				$p_template->load($row->id);
// 				$p_template->set('project_id',$this->_project_id);
// 				if ($p_template->store()===false)
// 				{
// 					$my_text .= 'error on master template import: ';
// 					$my_text .= "<br />Error: _importTemplate<br />#$my_text#<br />#<pre>".print_r($p_template,true).'</pre>#';
// 					$this->_success_text['JL_ADMIN_LMO_IMPORT_TEMPLATE_DATA']=$my_text;
// 					return false;
// 				}
// 				else
// 				{
// 					$my_text .= $p_template->template;
// 					$my_text .= ' <font color="green">'.JText::_('...created new data').'</font><br />';
// 					$my_text .= '<br />';
// 				}
// 			}
			
		}
		else
		{
			$this->_master_template=0;
			$predictionTemplatePrefix='prediction';
			if ((isset($this->_datas['template'])) && (is_array($this->_datas['template'])))
			{
				foreach ($this->_datas['template'] as $value)
				{
					$p_template =& $this->getTable('template');
					$template=$this->_getDataFromObject($value,'template');
					$p_template->set('template',$template);
					//actually func is unused in 1.5.0
					//$p_template->set('func',$this->_getDataFromObject($value,'func'));
					$p_template->set('title',$this->_getDataFromObject($value,'title'));
					$p_template->set('project_id',$this->_project_id);
					$p_template->set('params',$this->_getDataFromObject($value,'params'));
					if	((strtolower(substr($template,0,strlen($predictionTemplatePrefix)))!=$predictionTemplatePrefix) &&
						($template!='do_tipsl') &&
						($template!='frontpage') &&
						($template!='table') &&
						($template!='tipranking') &&
						($template!='tipresults') &&
						($template!='user'))
					{
						if ($p_template->store()===false)
						{
							$my_text .= 'error on own template import: ';
							$my_text .= "<br />Error: _importTemplate<br />#$my_text#<br />#<pre>".print_r($p_template,true).'</pre>#';
							$this->_success_text['Importing template data:']=$my_text;
							return false;
						}
						else
						{
							$dTitle=(!empty($p_template->title)) ? JText::_($p_template->title) : $p_template->template;
							$my_text .= '<span style="color:green">';
							$my_text .= JText::sprintf('Created new template data: %1$s',"</span><strong>$dTitle</strong>");
							$my_text .= '<br />';
						}
					}
				}
			}
		}
		$query="UPDATE #__joomleague_project SET master_template=$this->_master_template WHERE id=$this->_project_id";
		$this->_db->setQuery($query);
		$this->_db->query();
		$this->_success_text['Importing template data:']=$my_text;
		if ($this->_master_template==0)
		{
			// check and create missing templates if needed
			$this->_checklist();
			$my_text='<span style="color:green">';
			$my_text .= JText::_('Checked and created missing template data if needed');
			$my_text .= '</span><br />';
			$this->_success_text['Importing template data:'] .= $my_text;
		}
		return true;
	}

	private function _importDivisions()
	{
		$my_text='';
		if (!isset($this->_datas['division']) || count($this->_datas['division'])==0){return true;}
		if (isset($this->_datas['division']))
		{
			foreach ($this->_datas['division'] as $key => $division)
			{
				$p_division =&  $this->getTable('division');
				$oldId=(int)$division->id;
				$p_division->set('project_id',$this->_project_id);
				if ($division->id ==$this->_datas['division'][$key]->id)
				{
					$name=trim($this->_getDataFromObject($division,'name'));
					$p_division->set('name',$name);
					$p_division->set('shortname',$this->_getDataFromObject($division,'shortname'));
					$p_division->set('notes',$this->_getDataFromObject($division,'notes'));
					$p_division->set('parent_id',$this->_getDataFromObject($division,'parent_id'));
					if (trim($p_division->alias)!='')
					{
						$p_division->set('alias',$this->_getDataFromObject($division,'alias'));
					}
					else
					{
						$p_division->set('alias',JFilterOutput::stringURLSafe($name));
					}
				}
				if ($p_division->store()===false)
				{
					$my_text .= 'error on division import: ';
					$my_text .= '#'.$oldID.'#';
					$my_text .= "<br />Error: _importDivisions<br />#$my_text#<br />#<pre>".print_r($p_division,true).'</pre>#';
					$this->_success_text['Importing division data:']=$my_text;
					return false;
				}
				else
				{
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf('Created new division data: %1$s',"</span><strong>$name</strong>");
					$my_text .= '<br />';
				}
				$insertID=$this->_db->insertid();
				$this->_convertDivisionID[$oldId]=$insertID;
			}
			$this->_success_text['Importing division data:']=$my_text;
			return true;
		}
	}

	private function _importProjectTeam()
	{
		$my_text='';
		if (!isset($this->_datas['projectteam']) || count($this->_datas['projectteam'])==0){return true;}

		if (!isset($this->_datas['team']) || count($this->_datas['team'])==0){return true;}
		if ((!isset($this->_newteams) || count($this->_newteams)==0) &&
			(!isset($this->_dbteamsid) || count($this->_dbteamsid)==0)){return true;}

		foreach ($this->_datas['projectteam'] as $key => $projectteam)
		{
			$p_projectteam =& $this->getTable('projectteam');
			$import_projectteam=$this->_datas['projectteam'][$key];
			$oldID=$this->_getDataFromObject($import_projectteam,'id');
			$p_projectteam->set('project_id',$this->_project_id);
			$p_projectteam->set('team_id',$this->_convertTeamID[$this->_getDataFromObject($projectteam,'team_id')]);

			if (count($this->_convertDivisionID) > 0)
			{
				$p_projectteam->set('division_id',$this->_convertDivisionID[$this->_getDataFromObject($projectteam,'division_id')]);
			}

			$p_projectteam->set('start_points',$this->_getDataFromObject($projectteam,'start_points'));
			$p_projectteam->set('points_finally',$this->_getDataFromObject($projectteam,'points_finally'));
			$p_projectteam->set('neg_points_finally',$this->_getDataFromObject($projectteam,'neg_points_finally'));
			$p_projectteam->set('matches_finally',$this->_getDataFromObject($projectteam,'matches_finally'));
			$p_projectteam->set('won_finally',$this->_getDataFromObject($projectteam,'won_finally'));
			$p_projectteam->set('draws_finally',$this->_getDataFromObject($projectteam,'draws_finally'));
			$p_projectteam->set('lost_finally',$this->_getDataFromObject($projectteam,'lost_finally'));
			$p_projectteam->set('homegoals_finally',$this->_getDataFromObject($projectteam,'homegoals_finally'));
			$p_projectteam->set('guestgoals_finally',$this->_getDataFromObject($projectteam,'guestgoals_finally'));
			$p_projectteam->set('diffgoals_finally',$this->_getDataFromObject($projectteam,'diffgoals_finally'));
			$p_projectteam->set('is_in_score',$this->_getDataFromObject($projectteam,'is_in_score'));
			$p_projectteam->set('use_finally',$this->_getDataFromObject($projectteam,'use_finally'));
			$p_projectteam->set('admin',$this->_joomleague_admin);

			if ($this->import_version=='NEW')
			{
				if (isset($import_projectteam->mark))
				{
					$p_projectteam->set('mark',$this->_getDataFromObject($projectteam,'mark'));
				}
				$p_projectteam->set('info',$this->_getDataFromObject($projectteam,'info'));
				$p_projectteam->set('reason',$this->_getDataFromObject($projectteam,'reason'));
				$p_projectteam->set('notes',$this->_getDataFromObject($projectteam,'notes'));
			}
			else
			{
				$p_projectteam->set('notes',$this->_getDataFromObject($projectteam,'description'));
				$p_projectteam->set('reason',$this->_getDataFromObject($projectteam,'info'));
			}
			if ((isset($projectteam->standard_playground)) && ($projectteam->standard_playground > 0))
			{
				if (isset($this->_convertPlaygroundID[$this->_getDataFromObject($projectteam,'standard_playground')]))
				{
					$p_projectteam->set('standard_playground',$this->_convertPlaygroundID[$this->_getDataFromObject($projectteam,'standard_playground')]);
				}
			}

			if ($p_projectteam->store()===false)
			{
				$my_text .= 'error on projectteam import: ';
				$my_text .= $oldID;
				$my_text .= '<br />Error: _importProjectTeam<br />~'.$my_text.'~<br />~<pre>'.print_r($p_projectteam,true).'</pre>~';
				$this->_success_text['Importing projectteam data:']=$my_text;
				return false;
			}
			else
			{
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new projectteam data: %1$s',
											'</span><strong>'.$this->_getTeamName2($p_projectteam->team_id).'</strong>');
				$my_text .= '<br />';
			}
			$insertID=$this->_db->insertid();
			$this->_convertProjectTeamID[$p_projectteam->team_id]=$insertID;
			$this->_convertProjectTeamForMatchID[$this->_getDataFromObject($projectteam,'id')]=$this->_convertProjectTeamID[$p_projectteam->team_id];
		
//       $my_text .= '<span style="color:green">';
// 		  $my_text .= JText::sprintf('old / new ProjectTeamid: %1$s , %2$s',"</span><strong>$oldId</strong>","</span><strong>$insertID</strong>");
// 		  $my_text .= '<br />';
		  
    }
		
   	
    
//     echo '_importProjectTeam _convertProjectTeamID<pre>';
//     print_r($this->_convertProjectTeamID);
//     echo '</pre>';

//     echo '_importProjectTeam _convertProjectTeamForMatchID<pre>';
//     print_r($this->_convertProjectTeamForMatchID);
//     echo '</pre>';    
      		
		$this->_success_text['Importing projectteam data:']=$my_text;
		return true;
	}

	private function _importProjectReferees()
	{
		$my_text='';
		if (!isset($this->_datas['projectreferee']) || count($this->_datas['projectreferee'])==0){return true;}

		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}

		foreach ($this->_datas['projectreferee'] as $key => $projectreferee)
		{
			$import_projectreferee=$this->_datas['projectreferee'][$key];
			$oldID=$this->_getDataFromObject($import_projectreferee,'id');
			$p_projectreferee =& $this->getTable('projectreferee');

			$p_projectreferee->set('project_id',$this->_project_id);
			$p_projectreferee->set('person_id',$this->_convertPersonID[$this->_getDataFromObject($import_projectreferee,'person_id')]);
			$p_projectreferee->set('project_position_id',$this->_convertProjectPositionID[$this->_getDataFromObject($import_projectreferee,'project_position_id')]);

			$p_projectreferee->set('notes',$this->_getDataFromObject($import_projectreferee,'notes'));
			$p_projectreferee->set('picture',$this->_getDataFromObject($import_projectreferee,'picture'));
			$p_projectreferee->set('extended',$this->_getDataFromObject($import_projectreferee,'extended'));

			if ($p_projectreferee->store()===false)
			{
				$my_text .= 'error on projectreferee import: ';
				$my_text .= $oldID;
				$my_text .= '<br />Error: _importProjectReferees<br />~'.$my_text.'~<br />~<pre>'.print_r($p_projectreferee,true).'</pre>~';
				$this->_success_text['Importing projectreferee data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonName($p_projectreferee->person_id);
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new projectreferee data: %1$s,%2$s',"</span><strong>$dPerson->lastname","$dPerson->firstname</strong>");
				$my_text .= '<br />';
			}
			$insertID=$this->_db->insertid();
			$this->_convertProjectRefereeID[$oldID]=$insertID;
		}
		$this->_success_text['Importing projectreferee data:']=$my_text;
		return true;
	}

	private function _importProjectpositions()
	{
		$my_text='';
		if (!isset($this->_datas['projectposition']) || count($this->_datas['projectposition'])==0){return true;}

		if (!isset($this->_datas['position']) || count($this->_datas['position'])==0){return true;}
		if ((!isset($this->_newpositionsid) || count($this->_newpositionsid)==0) &&
			(!isset($this->_dbpositionsid) || count($this->_dbpositionsid)==0)){return true;}

		foreach ($this->_datas['projectposition'] as $key => $projectposition)
		{
			$import_projectposition=$this->_datas['projectposition'][$key];
			$oldID=$this->_getDataFromObject($import_projectposition,'id');
			$p_projectposition =&  $this->getTable('projectposition');
			$p_projectposition->set('project_id',$this->_project_id);
			$oldPositionID=$this->_getDataFromObject($import_projectposition,'position_id');
			if (!isset($this->_convertPositionID[$oldPositionID]))
			{
				$my_text .= '<span style="color:red">';
				$my_text .= JText::sprintf(	'Skipping import of ProjectPosition-ID [%1$s]. Old-PositionID: [%2$s]',
											"</span><strong>$oldID</strong><span style='color:red'>",
											"</span><strong>$oldPositionID</strong>").'<br />';
				continue;
			}
			$p_projectposition->set('position_id',$this->_convertPositionID[$oldPositionID]);
			if ($p_projectposition->store()===false)
			{
				$my_text .= 'error on ProjectPosition import: ';
				$my_text .= '#'.$oldID.'#';
				$my_text .= "<br />Error: _importProjectpositions<br />#$my_text#<br />#<pre>".print_r($p_projectposition,true).'</pre>#';
				$this->_success_text['Importing projectposition data:']=$my_text;
				return false;
			}
			else
			{
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new projectposition data: %1$s',
											'</span><strong>'.JText::_($this->_getObjectName('position',$p_projectposition->position_id)). ' [' . $p_projectposition->position_id . ']</strong>');
				$my_text .= '<br />';
			}
			$insertID=$this->_db->insertid();
			$this->_convertProjectPositionID[$oldID]=$insertID;
		}
		$this->_success_text['Importing projectposition data:']=$my_text;
		return true;
	}

	private function _importTeamPlayer()
	{
		$my_text='';
		if (!isset($this->_datas['teamplayer']) || count($this->_datas['teamplayer'])==0){return true;}

		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}

		foreach ($this->_datas['teamplayer'] as $key => $teamplayer)
		{
			$p_teamplayer =& $this->getTable('teamplayer');
			$import_teamplayer=$this->_datas['teamplayer'][$key];
			$oldID=$this->_getDataFromObject($import_teamplayer,'id');
			$oldTeamID=$this->_getDataFromObject($import_teamplayer,'projectteam_id');
			$oldPersonID=$this->_getDataFromObject($import_teamplayer,'person_id');
			if (!isset($this->_convertProjectTeamForMatchID[$oldTeamID]) ||
				!isset($this->_convertPersonID[$oldPersonID]))
			{
				$my_text .= '<span style="color:red">';
				$my_text .= JText::sprintf(	'Skipping import of TeamPlayer-ID [%1$s]. Old-ProjectTeamID: [%2$s] - Old-PersonID: [%3$s]',
											"</span><strong>$oldID</strong><span style='color:red'>",
											"</span><strong>$oldTeamID</strong><span style='color:red'>",
											"</span><strong>$oldPersonID</strong>").'<br />';
				continue;
			}
			$p_teamplayer->set('projectteam_id',$this->_convertProjectTeamForMatchID[$oldTeamID]);
			$p_teamplayer->set('person_id',$this->_convertPersonID[$oldPersonID]);
			$oldPositionID=$this->_getDataFromObject($import_teamplayer,'project_position_id');
			if (isset($this->_convertProjectPositionID[$oldPositionID]))
			{
				$p_teamplayer->set('project_position_id',$this->_convertProjectPositionID[$oldPositionID]);
			}
			$p_teamplayer->set('active',$this->_getDataFromObject($import_teamplayer,'active'));
			$p_teamplayer->set('jerseynumber',$this->_getDataFromObject($import_teamplayer,'jerseynumber'));
			$p_teamplayer->set('notes',$this->_getDataFromObject($import_teamplayer,'notes'));
			$p_teamplayer->set('picture',$this->_getDataFromObject($import_teamplayer,'picture'));
			$p_teamplayer->set('extended',$this->_getDataFromObject($import_teamplayer,'extended'));
			$p_teamplayer->set('injury',$this->_getDataFromObject($import_teamplayer,'injury'));
			$p_teamplayer->set('injury_date',$this->_getDataFromObject($import_teamplayer,'injury_date'));
			$p_teamplayer->set('injury_end',$this->_getDataFromObject($import_teamplayer,'injury_end'));
			$p_teamplayer->set('injury_detail',$this->_getDataFromObject($import_teamplayer,'injury_detail'));
			$p_teamplayer->set('injury_date_start',$this->_getDataFromObject($import_teamplayer,'injury_date_start'));
			$p_teamplayer->set('injury_date_end',$this->_getDataFromObject($import_teamplayer,'injury_date_end'));
			$p_teamplayer->set('suspension',$this->_getDataFromObject($import_teamplayer,'suspension'));
			$p_teamplayer->set('suspension_date',$this->_getDataFromObject($import_teamplayer,'suspension_date'));
			$p_teamplayer->set('suspension_end',$this->_getDataFromObject($import_teamplayer,'suspension_end'));
			$p_teamplayer->set('suspension_detail',$this->_getDataFromObject($import_teamplayer,'suspension_detail'));
			$p_teamplayer->set('susp_date_start',$this->_getDataFromObject($import_teamplayer,'susp_date_start'));
			$p_teamplayer->set('susp_date_end',$this->_getDataFromObject($import_teamplayer,'susp_date_end'));
			$p_teamplayer->set('away',$this->_getDataFromObject($import_teamplayer,'away'));
			$p_teamplayer->set('away_date',$this->_getDataFromObject($import_teamplayer,'away_date'));
			$p_teamplayer->set('away_end',$this->_getDataFromObject($import_teamplayer,'away_end'));
			$p_teamplayer->set('away_detail',$this->_getDataFromObject($import_teamplayer,'away_detail'));
			$p_teamplayer->set('away_date_start',$this->_getDataFromObject($import_teamplayer,'away_date_start'));
			$p_teamplayer->set('away_date_end',$this->_getDataFromObject($import_teamplayer,'away_date_end'));

			if ($p_teamplayer->store()===false)
			{
				$my_text .= 'error on teamplayer import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importTeamPlayer<br />#$my_text#<br />#<pre>".print_r($p_teamplayer,true).'</pre>#';
				$this->_success_text['Importing teamplayer data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonName($p_teamplayer->person_id);
				$project_position_id = $p_teamplayer->project_position_id;
				if($project_position_id>0) {
					$query ='SELECT *
								FROM #__joomleague_project_position
								WHERE	id='.$project_position_id;
					$this->_db->setQuery($query);
					$this->_db->query();
					$object=$this->_db->loadObject();
					$position_id = $object->position_id; 
					$dPosName=JText::_($this->_getObjectName('position',$position_id));
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Created new teamplayer data. Team: [%1$s] - Person: %2$s,%3$s - Position: %4$s',
												'</span><strong>'.$this->_getTeamName($p_teamplayer->projectteam_id).'</strong><span style="color:green">',
												'</span><strong>'.$dPerson->lastname,$dPerson->firstname.'</strong><span style="color:green">',
												"</span><strong>$dPosName</strong>");
					$my_text .= '<br />';
				} else {
					$dPosName='<span style="color:orange">'.JText::_('Has no position').'</span>';
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Created new teamplayer data. Team: [%1$s] - Person: %2$s,%3$s - Position: %4$s',
												'</span><strong>'.$this->_getTeamName($p_teamplayer->projectteam_id).'</strong><span style="color:green">',
												'</span><strong>'.$dPerson->lastname,$dPerson->firstname.'</strong><span style="color:green">',
												"</span><strong>$dPosName</strong>");
					$my_text .= '<br />';
				}
			}
			$insertID=$this->_db->insertid();
			$this->_convertTeamPlayerID[$oldID]=$insertID;
		}
		$this->_success_text['Importing teamplayer data:']=$my_text;
		return true;
	}

	private function _importTeamStaff()
	{
		$my_text='';
		if (!isset($this->_datas['teamstaff']) || count($this->_datas['teamstaff'])==0){return true;}

		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}

		foreach ($this->_datas['teamstaff'] as $key => $teamstaff)
		{
			$p_teamstaff =&  $this->getTable('teamstaff');
			$import_teamstaff=$this->_datas['teamstaff'][$key];
			$oldID=$this->_getDataFromObject($import_teamstaff,'id');
			$oldTeamID=$this->_getDataFromObject($import_teamstaff,'projectteam_id');
			$oldPersonID=$this->_getDataFromObject($import_teamstaff,'person_id');
			if (!isset($this->_convertProjectTeamForMatchID[$oldTeamID]) ||
				!isset($this->_convertPersonID[$oldPersonID]))
			{
				$my_text .= '<span style="color:red">';
				$my_text .= JText::sprintf(	'Skipping import of TeamStaff-ID [%1$s]. Old-ProjectTeamID: [%2$s] - Old-PersonID: [%3$s]',
											"</span><strong>$oldID</strong><span style='color:red'>",
											"</span><strong>$oldTeamID</strong><span style='color:red'>",
											"</span><strong>$oldPersonID</strong>").'<br />';
				continue;
			}
			$p_teamstaff->set('projectteam_id',$this->_convertProjectTeamForMatchID[$oldTeamID]);
			$p_teamstaff->set('person_id',$this->_convertPersonID[$oldPersonID]);
			$oldPositionID=$this->_getDataFromObject($import_teamstaff,'project_position_id');
			if (isset($this->_convertProjectPositionID[$oldPositionID]))
			{
				$p_teamstaff->set('project_position_id',$this->_convertProjectPositionID[$oldPositionID]);
			}
			$p_teamstaff->set('active',$this->_getDataFromObject($import_teamstaff,'active'));
			$p_teamstaff->set('notes',$this->_getDataFromObject($import_teamstaff,'notes'));
			$p_teamstaff->set('injury',$this->_getDataFromObject($import_teamstaff,'injury'));
			$p_teamstaff->set('injury_date',$this->_getDataFromObject($import_teamstaff,'injury_date'));
			$p_teamstaff->set('injury_end',$this->_getDataFromObject($import_teamstaff,'injury_end'));
			$p_teamstaff->set('injury_detail',$this->_getDataFromObject($import_teamstaff,'injury_detail'));
			$p_teamstaff->set('injury_date_start',$this->_getDataFromObject($import_teamstaff,'injury_date_start'));
			$p_teamstaff->set('injury_date_end',$this->_getDataFromObject($import_teamstaff,'injury_date_end'));
			$p_teamstaff->set('suspension',$this->_getDataFromObject($import_teamstaff,'suspension'));
			$p_teamstaff->set('suspension_date',$this->_getDataFromObject($import_teamstaff,'suspension_date'));
			$p_teamstaff->set('suspension_end',$this->_getDataFromObject($import_teamstaff,'suspension_end'));
			$p_teamstaff->set('suspension_detail',$this->_getDataFromObject($import_teamstaff,'suspension_detail'));
			$p_teamstaff->set('susp_date_start',$this->_getDataFromObject($import_teamstaff,'susp_date_start'));
			$p_teamstaff->set('susp_date_end',$this->_getDataFromObject($import_teamstaff,'susp_date_end'));
			$p_teamstaff->set('away',$this->_getDataFromObject($import_teamstaff,'away'));
			$p_teamstaff->set('away_date',$this->_getDataFromObject($import_teamstaff,'away_date'));
			$p_teamstaff->set('away_end',$this->_getDataFromObject($import_teamstaff,'away_end'));
			$p_teamstaff->set('away_detail',$this->_getDataFromObject($import_teamstaff,'away_detail'));
			$p_teamstaff->set('away_date_start',$this->_getDataFromObject($import_teamstaff,'away_date_start'));
			$p_teamstaff->set('away_date_end',$this->_getDataFromObject($import_teamstaff,'away_date_end'));
			$p_teamstaff->set('picture',$this->_getDataFromObject($import_teamstaff,'picture'));
			$p_teamstaff->set('extended',$this->_getDataFromObject($import_teamstaff,'extended'));

			if ($p_teamstaff->store()===false)
			{
				$my_text .= 'error on teamstaff import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importTeamStaff<br />#$my_text#<br />#<pre>".print_r($p_teamstaff,true).'</pre>#';
				$this->_success_text['Importing teamstaff data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonName($p_teamstaff->person_id);
				$project_position_id = $p_teamstaff->project_position_id;
				if($project_position_id>0) {
					$query ='SELECT *
								FROM #__joomleague_project_position
								WHERE	id='.$project_position_id;
					$this->_db->setQuery($query);
					$this->_db->query();
					$object=$this->_db->loadObject();
					$position_id = $object->position_id; 
					$dPosName=JText::_($this->_getObjectName('position',$position_id));
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Created new teamstaff data. Team: [%1$s] - Person: %2$s,%3$s - Position: %4$s',
												'</span><strong>'.$this->_getTeamName($p_teamstaff->projectteam_id).'</strong><span style="color:green">',
												'</span><strong>'.$dPerson->lastname,
												$dPerson->firstname.'</strong><span style="color:green">',
												"</span><strong>$dPosName</strong>");
					$my_text .= '<br />';
				} else {
					$dPosName='<span style="color:orange">'.JText::_('Has no position').'</span>';
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Created new teamplayer data. Team: [%1$s] - Person: %2$s,%3$s - Position: %4$s',
												'</span><strong>'.$this->_getTeamName($p_teamplayer->projectteam_id).'</strong><span style="color:green">',
												'</span><strong>'.$dPerson->lastname,$dPerson->firstname.'</strong><span style="color:green">',
												"</span><strong>$dPosName</strong>");
					$my_text .= '<br />';
				}
			}
			$insertID=$this->_db->insertid();
			$this->_convertTeamStaffID[$oldID]=$insertID;
		}
		$this->_success_text['Importing teamstaff data:']=$my_text;
		return true;
	}

	private function _importTeamTraining()
	{
		$my_text='';
		if (!isset($this->_datas['teamtraining']) || count($this->_datas['teamtraining'])==0){return true;}

		foreach ($this->_datas['teamtraining'] as $key => $teamtraining)
		{
			$p_teamtraining =& $this->getTable('teamtrainingdata');
			$import_teamtraining=$this->_datas['teamtraining'][$key];
			$oldID=$this->_getDataFromObject($import_teamtraining,'id');

			//This has to be fixed after we changed the field name into projectteam_id
			$p_teamtraining->set('project_team_id',$this->_convertProjectTeamForMatchID[$this->_getDataFromObject($import_teamtraining,'project_team_id')]);
			$p_teamtraining->set('project_id',$this->_project_id);
			// This has to be fixed if we really should use this field. Normally it should be deleted in the table
			$p_teamtraining->set('team_id',$this->_getDataFromObject($import_teamtraining,'team_id'));
			$p_teamtraining->set('dayofweek',$this->_getDataFromObject($import_teamtraining,'dayofweek'));
			$p_teamtraining->set('time_start',$this->_getDataFromObject($import_teamtraining,'time_start'));
			$p_teamtraining->set('time_end',$this->_getDataFromObject($import_teamtraining,'time_end'));
			$p_teamtraining->set('place',$this->_getDataFromObject($import_teamtraining,'place'));
			$p_teamtraining->set('notes',$this->_getDataFromObject($import_teamtraining,'notes'));

			if ($p_teamtraining->store()===false)
			{
				$my_text .= 'error on teamtraining import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importTeamTraining<br />#$my_text#<br />#<pre>".print_r($p_teamtraining,true).'</pre>#';
				$this->_success_text['Importing teamtraining data:']=$my_text;
				return false;
			}
			else
			{
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new teamtraining data. Team: [%1$s]',
											'</span><strong>'.$this-> _getTeamName($p_teamtraining->projectteam_id).'</strong>');
				$my_text .= '<br />';
			}
		}
		$this->_success_text['Importing teamtraining data:']=$my_text;
		return true;
	}  	
	
	private function _importRounds()
	{
		$my_text='';
		if (!isset($this->_datas['round']) || count($this->_datas['round'])==0){return true;}

		foreach ($this->_datas['round'] as $key => $round)
		{
			$p_round =& $this->getTable('round');
			$oldId=(int)$round->id;
			$name=trim($this->_getDataFromObject($round,'name'));
			$alias=trim($this->_getDataFromObject($round,'alias'));
			// if the roundcode field is empty,it is an old .jlg-Import file
			$roundnumber=$this->_getDataFromObject($round,'roundcode');
			if (empty($roundnumber))
			{
				$roundnumber=$this->_getDataFromObject($round,'matchcode');
			}
			$p_round->set('roundcode',$roundnumber);
			$p_round->set('name',$name);
			if ($alias!='')
			{
				$p_round->set('alias',$alias);
			}
			else
			{
				$p_round->set('alias',JFilterOutput::stringURLSafe($name));
			}
			$p_round->set('round_date_first',$this->_getDataFromObject($round,'round_date_first'));
			$round_date_last=trim($this->_getDataFromObject($round,'round_date_last'));
			if (($round_date_last=='') || ($round_date_last=='0000-00-00'))
			{
				$round_date_last=$this->_getDataFromObject($round,'round_date_first');
			}
			$p_round->set('round_date_last',$round_date_last);
			$p_round->set('project_id',$this->_project_id);
			
// echo '_importRounds<pre>';
// print_r($p_round);
// echo '</pre><br>';

			
			if ($p_round->store()===false)
			{
				$my_text .= 'error on round import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importRounds<br />#$my_text#<br />#<pre>".print_r($p_round,true).'</pre>#';
				$this->_success_text['Importing round data:']=$my_text;
				return false;
			}
			else
			{
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf('Created new round: %1$s',"</span><strong>$name</strong>");
				$my_text .= '<br />';
			}
			$insertID=$this->_db->insertid();
			$this->_convertRoundID[$oldId]=$insertID;
		
//    		$my_text .= '<span style="color:green">';
// 			$my_text .= JText::sprintf('old / new Roundid: %1$s , %2$s',"</span><strong>$oldId</strong>","</span><strong>$insertID</strong>");
// 			$my_text .= '<br />';
    
    
    }
		
		
//     echo '_importRounds _convertRoundID<pre>';
//     print_r($this->_convertRoundID);
//     echo '</pre>';
    
		$this->_success_text['Importing round data:']=$my_text;
		return true;
	}

	private function _importMatches()
	{
		$my_text='';
		
		$my_text .= '<span style="color:green">';
		$my_text .= JText::sprintf(	'JL_ADMIN_LMO_IMPORT_MATCH_VERSION : %1$s ',
												"<strong>$this->import_version</strong>");
		$my_text .= '<br />';
		
//     echo '_importMatches / _convertRoundID<pre>';
//     print_r($this->_convertRoundID);
//     echo '</pre>';
    
//     echo '_importMatches / _datas <pre>';
//     print_r($this->_datas);
//     echo '</pre>';    
    			
		if (!isset($this->_datas['match']) || count($this->_datas['match'])==0){return true;}

		if (!isset($this->_datas['team']) || count($this->_datas['team'])==0){return true;}
		if ((!isset($this->_newteams) || count($this->_newteams)==0) &&
			(!isset($this->_dbteamsid) || count($this->_dbteamsid)==0)){return true;}

		foreach ($this->_datas['match'] as $key => $match)
		{
			$p_match =& $this->getTable('match');
			$oldId=(int)$match->id;
			if ($this->import_version=='NEW')
			{
			
// 			$my_text .= '<span style="color:green">';
// 		  $my_text .= JText::sprintf(	'JL_ADMIN_LMO_IMPORT_MATCH_VERSION_convertRoundID : %1$s ',
// 												'<strong>$this->'.$this->_getDataFromObject($match,'round_id').'</strong>');
// 		  $my_text .= '<br />';
		  
// 		  $my_text .= '<span style="color:green">';
// 		  $my_text .= JText::sprintf(	'JL_ADMIN_LMO_IMPORT_MATCH_VERSION_round_id : %1$s ',
// 												'<strong>$this->'.$this->_convertRoundID[$this->_getDataFromObject($match,'round_id')].'</strong>');
// 		  $my_text .= '<br />';
		  
		
				$p_match->set('round_id',$this->_convertRoundID[$this->_getDataFromObject($match,'round_id')]);
				$p_match->set('match_number',$this->_getDataFromObject($match,'match_number'));

				if ($match->projectteam1_id > 0)
				{
					$team1=$this->_convertProjectTeamForMatchID[intval($this->_getDataFromObject($match,'projectteam1_id'))];
				}
				else
				{
					$team1=0;
				}
				$p_match->set('projectteam1_id',$team1);

				if ($match->projectteam2_id > 0)
				{
					$team2=$this->_convertProjectTeamForMatchID[intval($this->_getDataFromObject($match,'projectteam2_id'))];
				}
				else
				{
					$team2=0;
				}
				$p_match->set('projectteam2_id',$team2);

        
//         echo '<span style="color:green">';
// 				echo '</span><strong>'.$this->_getRoundName($this->_convertRoundID[$this->_getDataFromObject($match,'round_id')]).'</strong><span style="color:green">';
// 				echo '</span><strong>'.$team1.'</strong>';
// 				echo '<strong>'.$team2.'</strong>';
// 				echo '<br />';



				if (!empty($this->_convertPlaygroundID))
				{
					if (array_key_exists((int)$this->_getDataFromObject($match,'playground_id'),$this->_convertPlaygroundID))
					{
						$p_match->set('playground_id',$this->_convertPlaygroundID[$this->_getDataFromObject($match,'playground_id')]);
					}
					else
					{
						$p_match->set('playground_id',0);
					}
				}
				if ($p_match->playground_id ==0)
				{
					$p_match->set('playground_id',NULL);
				}

				$p_match->set('match_date',$this->_getDataFromObject($match,'match_date'));

				$p_match->set('time_present',$this->_getDataFromObject($match,'time_present'));

				$team1_result=$this->_getDataFromObject($match,'team1_result');
				if (isset($team1_result) && ($team1_result !=NULL)) { $p_match->set('team1_result',$team1_result); }

				$team2_result=$this->_getDataFromObject($match,'team2_result');
				if (isset($team2_result) && ($team2_result !=NULL)) { $p_match->set('team2_result',$team2_result); }

				$team1_bonus=$this->_getDataFromObject($match,'team1_bonus');
				if (isset($team1_bonus) && ($team1_bonus !=NULL)) { $p_match->set('team1_bonus',$team1_bonus); }

				$team2_bonus=$this->_getDataFromObject($match,'team2_bonus');
				if (isset($team2_bonus) && ($team2_bonus !=NULL)) { $p_match->set('team2_bonus',$team2_bonus); }

				$team1_legs=$this->_getDataFromObject($match,'team1_legs');
				if (isset($team1_legs) && ($team1_legs !=NULL)) { $p_match->set('team1_legs',$team1_legs); }

				$team2_legs=$this->_getDataFromObject($match,'team2_legs');
				if (isset($team2_legs) && ($team2_legs !=NULL)) { $p_match->set('team2_legs',$team2_legs); }

				$p_match->set('team1_result_split',$this->_getDataFromObject($match,'team1_result_split'));
				$p_match->set('team2_result_split',$this->_getDataFromObject($match,'team2_result_split'));
				$p_match->set('match_result_type',$this->_getDataFromObject($match,'match_result_type'));

				$team1_result_ot=$this->_getDataFromObject($match,'team1_result_ot');
				if (isset($team1_result_ot) && ($team1_result_ot !=NULL)) { $p_match->set('team1_result_ot',$team1_result_ot); }

				$team2_result_ot=$this->_getDataFromObject($match,'team2_result_ot');
				if (isset($team2_result_ot) && ($team2_result_ot !=NULL)) { $p_match->set('team2_result_ot',$team2_result_ot); }

				$team1_result_so=$this->_getDataFromObject($match,'team1_result_so');
				if (isset($team1_result_so) && ($team1_result_so !=NULL)) { $p_match->set('team1_result_so',$team1_result_so); }

				$team2_result_so=$this->_getDataFromObject($match,'team2_result_so');
				if (isset($team2_result_so) && ($team2_result_so !=NULL)) { $p_match->set('team2_result_so',$team2_result_so); }

				$p_match->set('alt_decision',$this->_getDataFromObject($match,'alt_decision'));

				$team1_result_decision=$this->_getDataFromObject($match,'team1_result_decision');
				if (isset($team1_result_decision) && ($team1_result_decision !=NULL)) { $p_match->set('team1_result_decision',$team1_result_decision); }

				$team2_result_decision=$this->_getDataFromObject($match,'team2_result_decision');
				if (isset($team2_result_decision) && ($team2_result_decision !=NULL)) { $p_match->set('team2_result_decision',$team2_result_decision); }

				$p_match->set('decision_info',$this->_getDataFromObject($match,'decision_info'));
				$p_match->set('cancel',$this->_getDataFromObject($match,'cancel'));
				$p_match->set('cancel_reason',$this->_getDataFromObject($match,'cancel_reason'));
				$p_match->set('count_result',$this->_getDataFromObject($match,'count_result'));
				$p_match->set('crowd',$this->_getDataFromObject($match,'crowd'));
				$p_match->set('summary',$this->_getDataFromObject($match,'summary'));
				$p_match->set('show_report',$this->_getDataFromObject($match,'show_report'));
				$p_match->set('preview',$this->_getDataFromObject($match,'preview'));
				$p_match->set('match_result_detail',$this->_getDataFromObject($match,'match_result_detail'));
				$p_match->set('new_match_id',$this->_getDataFromObject($match,'new_match_id'));
				$p_match->set('old_match_id',$this->_getDataFromObject($match,'old_match_id'));
				$p_match->set('extended',$this->_getDataFromObject($match,'extended'));
				$p_match->set('published',$this->_getDataFromObject($match,'published'));
			}
			else // ($this->import_version=='OLD')
			{
				$p_match->set('round_id',$this->_convertRoundID[intval($match->round_id)]);
				$p_match->set('match_number',$this->_getDataFromObject($match,'match_number'));

				if ($match->matchpart1 > 0)
				{
					$team1=$this->_convertTeamID[intval($match->matchpart1)];
					$p_match->set('projectteam1_id',$this->_convertProjectTeamID[$team1]);
				}
				else
				{
					$p_match->set('projectteam1_id',0);
				}

				if ($match->matchpart2 > 0)
				{
					$team2=$this->_convertTeamID[intval($match->matchpart2)];
					$p_match->set('projectteam2_id',$this->_convertProjectTeamID[$team2]);
				}
				else
				{
					$p_match->set('projectteam2_id',0);
				}

				$matchdate=(string)$match->match_date;
				$p_match->set('match_date',$matchdate);

				$team1_result=$this->_getDataFromObject($match,'matchpart1_result');
				if (isset($team1_result) && ($team1_result !=NULL)) { $p_match->set('team1_result',$team1_result); }

				$team2_result=$this->_getDataFromObject($match,'matchpart2_result');
				if (isset($team2_result) && ($team2_result !=NULL)) { $p_match->set('team2_result',$team2_result); }

				$team1_bonus=$this->_getDataFromObject($match,'matchpart1_bonus');
				if (isset($team1_bonus) && ($team1_bonus !=NULL)) { $p_match->set('team1_bonus',$team1_bonus); }

				$team2_bonus=$this->_getDataFromObject($match,'matchpart2_bonus');
				if (isset($team2_bonus) && ($team2_bonus !=NULL)) { $p_match->set('team2_bonus',$team2_bonus); }

				$team1_legs=$this->_getDataFromObject($match,'matchpart1_legs');
				if (isset($team1_legs) && ($team1_legs !=NULL)) { $p_match->set('team1_legs',$team1_legs); }

				$team2_legs=$this->_getDataFromObject($match,'matchpart2_legs');
				if (isset($team2_legs) && ($team2_legs !=NULL)) { $p_match->set('team2_legs',$team2_legs); }

				$p_match->set('team1_result_split',$this->_getDataFromObject($match,'matchpart1_result_split'));//NULL
				$p_match->set('team2_result_split',$this->_getDataFromObject($match,'matchpart2_result_split'));//NULL
				$p_match->set('match_result_type',$this->_getDataFromObject($match,'match_result_type'));

				$team1_result_ot=$this->_getDataFromObject($match,'matchpart1_result_ot');
				if (isset($team1_result_ot) && ($team1_result_ot !=NULL)) { $p_match->set('team1_result_ot',$team1_result_ot); }

				$team2_result_ot=$this->_getDataFromObject($match,'matchpart2_result_ot');
				if (isset($team2_result_ot) && ($team2_result_ot !=NULL)) { $p_match->set('team2_result_ot',$team2_result_ot); }

				$p_match->set('alt_decision',$this->_getDataFromObject($match,'alt_decision'));

				$team1_result_decision=$this->_getDataFromObject($match,'matchpart1_result_decision');
				if (isset($team1_result_decision) && ($team1_result_decision !=NULL)) { $p_match->set('team1_result_decision',$team1_result_decision); }

				$team2_result_decision=$this->_getDataFromObject($match,'matchpart2_result_decision');
				if (isset($team2_result_decision) && ($team2_result_decision !=NULL)) { $p_match->set('team2_result_decision',$team2_result_decision); }

				$p_match->set('decision_info',$this->_getDataFromObject($match,'decision_info'));
				$p_match->set('count_result',$this->_getDataFromObject($match,'count_result'));
				$p_match->set('crowd',$this->_getDataFromObject($match,'crowd'));
				$p_match->set('summary',$this->_getDataFromObject($match,'summary'));
				$p_match->set('show_report',$this->_getDataFromObject($match,'show_report'));
				$p_match->set('match_result_detail',$this->_getDataFromObject($match,'match_result_detail'));
				$p_match->set('published',$this->_getDataFromObject($match,'published'));
			}

			if ($p_match->store()===false)
			{
				$my_text .= 'error on match import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importMatches<br />#$my_text#<br />#<pre>".print_r($p_match,true).'</pre>#';
				$this->_success_text['Importing match data:']=$my_text;
				return false;
			}
			else
			{
				if ($this->import_version=='NEW')
				{
					if ($match->projectteam1_id > 0)
					{
						$teamname1=$this->_getTeamName($p_match->projectteam1_id);
					}
					else
					{
						$teamname1='<span style="color:orange">'.JText::_('Home-Team not asigned').'</span>';
					}
					if ($match->projectteam2_id > 0)
					{
						$teamname2=$this->_getTeamName($p_match->projectteam2_id);
					}
					else
					{
						$teamname2='<span style="color:orange">'.JText::_('Guest-Team not asigned').'</span>';
					}

					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Added to round: %1$s / Match: %2$s - %3$s',
												'</span><strong>'.$this->_getRoundName($this->_convertRoundID[$this->_getDataFromObject($match,'round_id')]).'</strong><span style="color:green">',
												"</span><strong>$teamname1</strong>",
												"<strong>$teamname2</strong>");
					$my_text .= '<br />';
				}

				if ($this->import_version=='OLD')
				{
					if ($match->matchpart1 > 0)
					{
						$teamname1=$this->_getTeamName2($this->_convertTeamID[intval($match->matchpart1)]);
					}
					else
					{
						$teamname1='<span style="color:orange">'.JText::_('Home-Team not asigned').'</span>';
					}
					if ($match->matchpart2 > 0)
					{
						$teamname2=$this->_getTeamName2($this->_convertTeamID[intval($match->matchpart2)]);
					}
					else
					{
						$teamname2='<span style="color:orange">'.JText::_('Guest-Team not asigned').'</span>';
					}

					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Added to round: %1$s / Match: %2$s - %3$s',
												'</span><strong>'.$this->_getRoundName($this->_convertRoundID[$this->_getDataFromObject($match,'round_id')]).'</strong><span style="color:green">',
												"</span><strong>$teamname1</strong>",
												"<strong>$teamname2</strong>");
					$my_text .= '<br />';
				}
			}
			$insertID=$this->_db->insertid();
			$this->_convertMatchID[$oldId]=$insertID;
		}
		$this->_success_text['Importing match data:']=$my_text;
		return true;
	}

	private function _importMatchPlayer()
	{
		$my_text='';
		if (!isset($this->_datas['matchplayer']) || count($this->_datas['matchplayer'])==0){return true;}

		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}

		foreach ($this->_datas['matchplayer'] as $key => $matchplayer)
		{
			$import_matchplayer=$this->_datas['matchplayer'][$key];
			$oldID=$this->_getDataFromObject($import_matchplayer,'id');
			$p_matchplayer =& $this->getTable('matchplayer');
			$oldMatchID=$this->_getDataFromObject($import_matchplayer,'match_id');
			$oldPersonID=$this->_getDataFromObject($import_matchplayer,'teamplayer_id');
			if (!isset($this->_convertMatchID[$oldMatchID]) ||
				!isset($this->_convertTeamPlayerID[$oldPersonID]))
			{
				$my_text .= '<span style="color:red">';
				$my_text .= JText::sprintf(	'Skipping import of MatchPlayer-ID [%1$s]. Old-MatchID: [%2$s] - Old-TeamPlayerID: [%3$s]',
											"</span><strong>$oldID</strong><span style='color:red'>",
											"</span><strong>$oldMatchID</strong><span style='color:red'>",
											"</span><strong>$oldPersonID</strong>").'<br />';
				continue;
			}
			$p_matchplayer->set('match_id',$this->_convertMatchID[$oldMatchID]);
			$p_matchplayer->set('teamplayer_id',$this->_convertTeamPlayerID[$oldPersonID]);
			$oldPositionID=$this->_getDataFromObject($import_matchplayer,'project_position_id');
			if (isset($this->_convertProjectPositionID[$oldPositionID]))
			{
				$p_matchplayer->set('project_position_id',$this->_convertProjectPositionID[$oldPositionID]);
			}
			$p_matchplayer->set('came_in',$this->_getDataFromObject($import_matchplayer,'came_in'));
			if ($import_matchplayer->in_for > 0)
			{
				$oldPersonID=$this->_getDataFromObject($import_matchplayer,'in_for');
				if (isset($this->_convertPersonID[$oldPersonID]))
				{
					$p_matchplayer->set('in_for',$this->_convertTeamPlayerID[$oldPersonID]);
				}
			}
//TO BE FIXED if this is also a person id
			$p_matchplayer->set('out',$this->_getDataFromObject($import_matchplayer,'out'));
			$p_matchplayer->set('in_out_time',$this->_getDataFromObject($import_matchplayer,'in_out_time'));
			$p_matchplayer->set('ordering',$this->_getDataFromObject($import_matchplayer,'ordering'));

			if ($p_matchplayer->store()===false)
			{
				$my_text .= 'error on matchplayer import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importMatchPlayer<br />#$my_text#<br />#<pre>".print_r($p_matchplayer,true).'</pre>#';
				$this->_success_text['Importing matchplayer data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonFromTeamPlayer($p_matchplayer->teamplayer_id);
				$dPosName=(($p_matchplayer->project_position_id==0) ?
							'<span style="color:orange">'.JText::_('Has no position').'</span>' :
							JText::_($this->_getObjectName('position',$p_matchplayer->project_position_id)));
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new matchplayer data. MatchID: %1$s - Player: %2$s,%3$s - Position: %4$s',
											'</span><strong>'.$p_matchplayer->match_id.'</strong><span style="color:green">',
											'</span><strong>'.$dPerson->lastname,
											$dPerson->firstname.'</strong><span style="color:green">',
											"</span><strong>$dPosName</strong>");
				$my_text .= '<br />';
			}
		}
		$this->_success_text['Importing matchplayer data:']=$my_text;
		return true;
	}

	private function _importMatchStaff()
	{
		$my_text='';
		if (!isset($this->_datas['matchstaff']) || count($this->_datas['matchstaff'])==0){return true;}

		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}

		foreach ($this->_datas['matchstaff'] as $key => $matchstaff)
		{
			$import_matchstaff=$this->_datas['matchstaff'][$key];
			$oldID=$this->_getDataFromObject($import_matchstaff,'id');
			$p_matchstaff =& $this->getTable('matchstaff');
			$oldMatchID=$this->_getDataFromObject($import_matchstaff,'match_id');
			$oldPersonID=$this->_getDataFromObject($import_matchstaff,'staff_id');
			if (!isset($this->_convertMatchID[$oldMatchID]) ||
				!isset($this->_convertTeamStaffID[$oldPersonID]))
			{
				$my_text .= '<span style="color:red">';
				$my_text .= JText::sprintf(	'Skipping import of MatchStaff-ID [%1$s]. Old-MatchID: [%2$s] - Old-StaffID: [%3$s]',
											"</span><strong>$oldID</strong><span style='color:red'>",
											"</span><strong>$oldMatchID</strong><span style='color:red'>",
											"</span><strong>$oldPersonID</strong>").'<br />';
				continue;
			}
			$p_matchstaff->set('match_id',$this->_convertMatchID[$oldMatchID]);
			$p_matchstaff->set('staff_id',$this->_convertTeamStaffID[$oldPersonID]);
			$oldPositionID=$this->_getDataFromObject($import_matchstaff,'project_position_id');
			if (isset($this->_convertProjectPositionID[$oldPositionID]))
			{
				$p_matchstaff->set('project_position_id',$this->_convertProjectPositionID[$oldPositionID]);
			}
			$p_matchstaff->set('ordering',$this->_getDataFromObject($import_matchstaff,'ordering'));
			if ($p_matchstaff->store()===false)
			{
				$my_text .= 'error on matchstaff import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importMatchStaff<br />#$my_text#<br />#<pre>".print_r($p_matchstaff,true).'</pre>#';
				$this->_success_text['Importing matchstaff data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonFromTeamStaff($p_matchstaff->staff_id);
				$dPosName=(($p_matchstaff->project_position_id==0) ?
							'<span style="color:orange">'.JText::_('Has no position').'</span>' :
							JText::_($this->_getObjectName('position',$p_matchstaff->project_position_id)));
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new matchstaff data. MatchID: %1$s - Staff: %2$s,%3$s - Position: %4$s',
											'</span><strong>'.$p_matchstaff->match_id.'</strong><span style="color:green">',
											'</span><strong>'.$dPerson->lastname,
											$dPerson->firstname.'</strong><span style="color:green">',
											"</span><strong>$dPosName</strong>");
				$my_text .= '<br />';
			}
		}
		$this->_success_text['Importing matchstaff data:']=$my_text;
		return true;
	}

	private function _importMatchReferee()
	{
		$my_text='';
		if (!isset($this->_datas['matchreferee']) || count($this->_datas['matchreferee'])==0){return true;}

		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}

		foreach ($this->_datas['matchreferee'] as $key => $matchreferee)
		{
			$import_matchreferee=$this->_datas['matchreferee'][$key];
			$oldID=$this->_getDataFromObject($import_matchreferee,'id');
			$p_matchreferee =& $this->getTable('matchreferee');
			$oldMatchID=$this->_getDataFromObject($import_matchreferee,'match_id');
			$oldPersonID=$this->_getDataFromObject($import_matchreferee,'referee_id');
			if (!isset($this->_convertMatchID[$oldMatchID]) ||
				!isset($this->_convertProjectRefereeID[$oldPersonID]))
			{
				$my_text .= '<span style="color:red">';
				$my_text .= JText::sprintf(	'Skipping import of MatchReferee-ID [%1$s]. Old-MatchID: [%2$s] - Old-RefereeID: [%3$s]',
											"</span><strong>$oldID</strong><span style='color:red'>",
											"</span><strong>$oldMatchID</strong><span style='color:red'>",
											"</span><strong>$oldPersonID</strong>").'<br />';
				continue;
			}
			$p_matchreferee->set('match_id',$this->_convertMatchID[$oldMatchID]);
			$p_matchreferee->set('referee_id',$this->_convertProjectRefereeID[$oldPersonID]);
			$oldPositionID=$this->_getDataFromObject($import_matchreferee,'project_position_id'); 
			if (isset($this->_convertProjectPositionID[$oldPositionID]))
			{
				$p_matchreferee->set('project_position_id',$this->_convertProjectPositionID[$oldPositionID]);
			}
			$p_matchreferee->set('ordering',$this->_getDataFromObject($import_matchreferee,'ordering'));
			if ($p_matchreferee->store()===false)
			{
				$my_text .= 'error on matchreferee import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importMatchReferee<br />#$my_text#<br />#<pre>".print_r($p_matchreferee,true).'</pre>#';
				$this->_success_text['Importing matchreferee data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonName($p_matchreferee->referee_id);
				$dPosName=(($p_matchreferee->project_position_id==0) ?
							'<span style="color:orange">'.JText::_('Has no position').'</span>' :
							JText::_($this->_getObjectName('position',$p_matchreferee->project_position_id)));
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new matchreferee data. MatchID: %1$s - Referee: %2$s,%3$s - Position: %4$s',
											'</span><strong>'.$p_matchreferee->match_id.'</strong><span style="color:green">',
											'</span><strong>'.$dPerson->lastname,
											$dPerson->firstname.'</strong><span style="color:green">',
											"</span><strong>$dPosName</strong>");
				$my_text .= '<br />';
			}
		}
		$this->_success_text['Importing matchreferee data:']=$my_text;
		return true;
	}

	private function _importMatchEvent()
	{
		$my_text='';
		if (!isset($this->_datas['matchevent']) || count($this->_datas['matchevent'])==0){return true;}

		if (!isset($this->_datas['person']) || count($this->_datas['person'])==0){return true;}
		if ((!isset($this->_newpersonsid) || count($this->_newpersonsid)==0) &&
			(!isset($this->_dbpersonsid) || count($this->_dbpersonsid)==0)){return true;}
		if (!isset($this->_datas['event']) || count($this->_datas['event'])==0){return true;}
		if ((!isset($this->_neweventsid) || count($this->_neweventsid)==0) &&
			(!isset($this->_dbeventsid) || count($this->_dbeventsid)==0)){return true;}

		foreach ($this->_datas['matchevent'] as $key => $matchevent)
		{
			$import_matchevent=$this->_datas['matchevent'][$key];
			$oldID=$this->_getDataFromObject($import_matchevent,'id');

			$p_matchevent =& $this->getTable('matchevent');

			$p_matchevent->set('match_id',$this->_convertMatchID[$this->_getDataFromObject($import_matchevent,'match_id')]);
			$p_matchevent->set('projectteam_id',$this->_convertProjectTeamForMatchID[$this->_getDataFromObject($import_matchevent,'projectteam_id')]);
			if ($import_matchevent->teamplayer_id > 0)
			{
				$p_matchevent->set('teamplayer_id',$this->_convertTeamPlayerID[$this->_getDataFromObject($import_matchevent,'teamplayer_id')]);
			}
			else
			{
				$p_matchevent->set('teamplayer_id',0);
			}
			if ($import_matchevent->teamplayer_id2 > 0)
			{
				$p_matchevent->set('teamplayer_id2',$this->_convertTeamPlayerID[$this->_getDataFromObject($import_matchevent,'teamplayer_id2')]);
			}
			else
			{
				$p_matchevent->set('teamplayer_id2',0);
			}
			$p_matchevent->set('event_time',$this->_getDataFromObject($import_matchevent,'event_time'));
			$p_matchevent->set('event_type_id',$this->_convertEventID[$this->_getDataFromObject($import_matchevent,'event_type_id')]);
			$p_matchevent->set('event_sum',$this->_getDataFromObject($import_matchevent,'event_sum'));
			$p_matchevent->set('notice',$this->_getDataFromObject($import_matchevent,'notice'));
			$p_matchevent->set('notes',$this->_getDataFromObject($import_matchevent,'notes'));

			if ($p_matchevent->store()===false)
			{
				$my_text .= 'error on matchevent import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importMatchEvent<br />#$my_text#<br />#<pre>".print_r($p_matchevent,true).'</pre>#';
				$this->_success_text['Importing matchevent data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonFromTeamPlayer($p_matchevent->teamplayer_id);
				$dEventName=(($p_matchevent->event_type_id==0) ?
							'<span style="color:orange">'.JText::_('Has no event').'</span>' :
							JText::_($this->_getObjectName('eventtype',$p_matchevent->event_type_id)));
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new matchevent data. MatchID: %1$s - Player: %2$s,%3$s - Eventtime: %4$s - Event: %5$s',
											'</span><strong>'.$p_matchevent->match_id.'</strong><span style="color:green">',
											'</span><strong>'.$dPerson->lastname,
											$dPerson->firstname.'</strong><span style="color:green">',
											'</span><strong>'.$p_matchevent->event_time.'</strong><span style="color:green">',
											"</span><strong>$dEventName</strong>");
				$my_text .= '<br />';
			}
		}
		$this->_success_text['Importing matchevent data:']=$my_text;
		return true;
	}

	private function _importPositionStatistic()
	{
		$my_text='';
		if (!isset($this->_datas['positionstatistic']) || count($this->_datas['positionstatistic'])==0){return true;}

		if ((!isset($this->_newpositionsid) || count($this->_newpositionsid)==0) &&
			(!isset($this->_dbpositionsid) || count($this->_dbpositionsid)==0)){return true;}
		if ((!isset($this->_newstatisticsid) || count($this->_newstatisticsid)==0) &&
			(!isset($this->_dbstatisticsid) || count($this->_dbstatisticsid)==0)){return true;}

		if (!isset($this->_datas['statistic']) || count($this->_datas['statistic'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for position statistic data because there is no statistic data included!',count($this->_datas['positionstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing position statistic data:']=$my_text;
			return true;
		}

		if (!isset($this->_datas['position']) || count($this->_datas['position'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for position statistic data because there is no position data included!',count($this->_datas['positionstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing position statistic data:']=$my_text;
			return true;
		}

		foreach ($this->_datas['positionstatistic'] as $key => $positionstatistic)
		{
			$import_positionstatistic=$this->_datas['positionstatistic'][$key];
			$oldID=$this->_getDataFromObject($import_positionstatistic,'id');

			$p_positionstatistic =& $this->getTable('positionstatistic');

			$p_positionstatistic->set('position_id',$this->_convertPositionID[$this->_getDataFromObject($import_positionstatistic,'position_id')]);
			$p_positionstatistic->set('statistic_id',$this->_convertStatisticID[$this->_getDataFromObject($import_positionstatistic,'statistic_id')]);
			//$p_positionstatistic->set('ordering',$this->_getDataFromObject($import_positionstatistic,'ordering'));

			$query ="SELECT id
							FROM #__joomleague_position_statistic
							WHERE	position_id='$p_positionstatistic->position_id' AND
									statistic_id='$p_positionstatistic->statistic_id'";
			$this->_db->setQuery($query); $this->_db->query();
			if ($object=$this->_db->loadObject())
			{
				$my_text .= '<span style="color:orange">';
				$my_text .= JText::sprintf(	'Using existing positionstatistic data. Position: [%1$s] - Statistic: %2$s',
											'</span><strong>'.$this->_getObjectName('position',$p_positionstatistic->position_id).'</strong><span style="color:orange">',
											'</span><strong>'.$this->_getObjectName('statistic',$p_positionstatistic->statistic_id).'</strong>');
				$my_text .= '<br />';
			}
			else
			{
				if ($p_positionstatistic->store()===false)
				{
					$my_text .= 'error on positionstatistic import: ';
					$my_text .= $oldID;
					$my_text .= "<br />Error: _importPositionStatistic<br />#$my_text#<br />#<pre>".print_r($p_positionstatistic,true).'</pre>#';
					$this->_success_text['Importing position statistic data:']=$my_text;
					return false;
				}
				else
				{
					$my_text .= '<span style="color:green">';
					$my_text .= JText::sprintf(	'Created new position statistic data. Position: [%1$s] - Statistic: %2$s',
												'</span><strong>'.$this->_getObjectName('position',$p_positionstatistic->position_id).'</strong><span style="color:green">',
												'</span><strong>'.$this->_getObjectName('statistic',$p_positionstatistic->statistic_id).'</strong>');
					$my_text .= '<br />';
				}
			}
		}
		$this->_success_text['Importing position statistic data:']=$my_text;
		return true;
	}
	
	private function _importMatchStaffStatistic()
	{
		$my_text='';
		if (!isset($this->_datas['matchstaffstatistic']) || count($this->_datas['matchstaffstatistic'])==0){return true;}

		if ((!isset($this->_newstatisticsid) || count($this->_newstatisticsid)==0) &&
			(!isset($this->_dbstatisticsid) || count($this->_dbstatisticsid)==0)){return true;}

		if (!isset($this->_datas['statistic']) || count($this->_datas['statistic'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match staff statistic data because there is no statistic data included!',count($this->_datas['matchstaffstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match staff statistic data:']=$my_text;
			return true;
		}
		if (!isset($this->_datas['match']) || count($this->_datas['match'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match statistic data because there is no match data included!',count($this->_datas['matchstaffstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match staff statistic data:']=$my_text;
			return true;
		}
		if (!isset($this->_datas['projectteam']) || count($this->_datas['projectteam'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match statistic data because there is no projectteam data included!',count($this->_datas['matchstaffstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match staff statistic data:']=$my_text;
			return true;
		}
		if (!isset($this->_datas['teamstaff']) || count($this->_datas['teamstaff'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match statistic data because there is no teamstaff data included!',count($this->_datas['matchstaffstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match staff statistic data:']=$my_text;
			return true;
		}
		foreach ($this->_datas['matchstaffstatistic'] as $key => $matchstaffstatistic)
		{
			$import_matchstaffstatistic=$this->_datas['matchstaffstatistic'][$key];
			$oldID=$this->_getDataFromObject($import_matchstaffstatistic,'id');

			$p_matchstaffstatistic =& $this->getTable('matchstaffstatistic');

			$p_matchstaffstatistic->set('match_id',$this->_convertMatchID[$this->_getDataFromObject($import_matchstaffstatistic,'match_id')]);
			$p_matchstaffstatistic->set('projectteam_id',$this->_convertProjectTeamForMatchID[$this->_getDataFromObject($import_matchstaffstatistic,'projectteam_id')]);
			$p_matchstaffstatistic->set('staff_id',$this->_convertTeamStaffID[$this->_getDataFromObject($import_matchstaffstatistic,'staff_id')]);
			$p_matchstaffstatistic->set('statistic_id',$this->_convertStatisticID[$this->_getDataFromObject($import_matchstaffstatistic,'statistic_id')]);
			$p_matchstaffstatistic->set('value',$this->_getDataFromObject($import_matchstaffstatistic,'value'));

			if ($p_matchstaffstatistic->store()===false)
			{
				$my_text .= 'error on matchstaffstatistic import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importMatchStaffStatistic<br />#$my_text#<br />#<pre>".print_r($p_matchstaffstatistic,true).'</pre>#';
				$this->_success_text['Importing match staff statistic data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonFromTeamStaff($p_matchstaffstatistic->staff_id);
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new match staff statistic data. StatisticID: %1$s - MatchID: %2$s - Player: %3$s,%4$s - Team: %5$s - Value: %6$s',
											'</span><strong>'.$p_matchstaffstatistic->statistic_id.'</strong><span style="color:green">',
											'</span><strong>'.$p_matchstaffstatistic->match_id.'</strong><span style="color:green">',
											'</span><strong>'.$dPerson->lastname,
											$dPerson->firstname.'</strong><span style="color:green">',
											'</span><strong>'.$this->_getTeamName($p_matchstaffstatistic->projectteam_id).'</strong><span style="color:green">',
											'</span><strong>'.$p_matchstaffstatistic->value.'</strong>');
				$my_text .= '<br />';
			}
		}
		$this->_success_text['Importing match staff statistic data:']=$my_text;
		return true;
	}

	private function _importMatchStatistic()
	{
		$my_text='';
		if (!isset($this->_datas['matchstatistic']) || count($this->_datas['matchstatistic'])==0){return true;}

		if ((!isset($this->_newstatisticsid) || count($this->_newstatisticsid)==0) &&
			(!isset($this->_dbstatisticsid) || count($this->_dbstatisticsid)==0)){return true;}

		if (!isset($this->_datas['statistic']) || count($this->_datas['statistic'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match statistic data because there is no statistic data included!',count($this->_datas['matchstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match statistic data:']=$my_text;
			return true;
		}
		if (!isset($this->_datas['match']) || count($this->_datas['match'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match statistic data because there is no match data included!',count($this->_datas['matchstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match statistic data:']=$my_text;
			return true;
		}
		if (!isset($this->_datas['projectteam']) || count($this->_datas['projectteam'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match statistic data because there is no projectteam data included!',count($this->_datas['matchstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match statistic data:']=$my_text;
			return true;
		}
		if (!isset($this->_datas['teamplayer']) || count($this->_datas['teamplayer'])==0)
		{
			$my_text .= '<span style="color:red">';
			$my_text .= JText::sprintf('Warning: Skipped %1$s records for match statistic data because there is no teamplayer data included!',count($this->_datas['matchstatistic']));
			$my_text .= '</span>';
			$this->_success_text['Importing match statistic data:']=$my_text;
			return true;
		}
		foreach ($this->_datas['matchstatistic'] as $key => $matchstatistic)
		{
			$import_matchstatistic=$this->_datas['matchstatistic'][$key];
			$oldID=$this->_getDataFromObject($import_matchstatistic,'id');

			$p_matchstatistic =& $this->getTable('matchstatistic');

			$p_matchstatistic->set('match_id',$this->_convertMatchID[$this->_getDataFromObject($import_matchstatistic,'match_id')]);
			$p_matchstatistic->set('projectteam_id',$this->_convertProjectTeamForMatchID[$this->_getDataFromObject($import_matchstatistic,'projectteam_id')]);
			$p_matchstatistic->set('teamplayer_id',$this->_convertTeamPlayerID[$this->_getDataFromObject($import_matchstatistic,'teamplayer_id')]);
			$p_matchstatistic->set('statistic_id',$this->_convertStatisticID[$this->_getDataFromObject($import_matchstatistic,'statistic_id')]);
			$p_matchstatistic->set('value',$this->_getDataFromObject($import_matchstatistic,'value'));

			if ($p_matchstatistic->store()===false)
			{
				$my_text .= 'error on matchstatistic import: ';
				$my_text .= $oldID;
				$my_text .= "<br />Error: _importMatchStatistic<br />#$my_text#<br />#<pre>".print_r($p_matchstatistic,true).'</pre>#';
				$this->_success_text['Importing match statistic data:']=$my_text;
				return false;
			}
			else
			{
				$dPerson=$this->_getPersonFromTeamPlayer($p_matchstatistic->teamplayer_id);
				$my_text .= '<span style="color:green">';
				$my_text .= JText::sprintf(	'Created new match statistic data. StatisticID: %1$s - MatchID: %2$s - Player: %3$s,%4$s - Team: %5$s - Value: %6$s',
											'</span><strong>'.$p_matchstatistic->statistic_id.'</strong><span style="color:green">',
											'</span><strong>'.$p_matchstatistic->match_id.'</strong><span style="color:green">',
											'</span><strong>'.$dPerson->lastname,
											$dPerson->firstname.'</strong><span style="color:green">',
											'</span><strong>'.$this->_getTeamName($p_matchstatistic->projectteam_id).'</strong><span style="color:green">',
											'</span><strong>'.$p_matchstatistic->value.'</strong>');
				$my_text .= '<br />';
			}
		}
		$this->_success_text['Importing match statistic data:']=$my_text;
		return true;
	}	

	/**
	 * _getCountryByOldid
	 *
	 * Get ISO-Code for countries to convert in old jlg import file
	 *
	 * @param object $obj object where we find the key
	 * @param string $key key what we find in the object
	 *
	 * @access public
	 * @since  1.5
	 *
	 * @return void
	 */
	public function getCountryByOldid()
	{
		$country['0']='';
		$country['1']='AFG';
		$country['2']='ALB';
		$country['3']='DZA';
		$country['4']='ASM';
		$country['5']='AND';
		$country['6']='AGO';
		$country['7']='AIA';
		$country['8']='ATA';
		$country['9']='ATG';
		$country['10']='ARG';
		$country['11']='ARM';
		$country['12']='ABW';
		$country['13']='AUS';
		$country['14']='AUT';
		$country['15']='AZE';
		$country['16']='BHS';
		$country['17']='BHR';
		$country['18']='BGD';
		$country['19']='BRB';
		$country['20']='BLR';
		$country['21']='BEL';
		$country['22']='BLZ';
		$country['23']='BEN';
		$country['24']='BMU';
		$country['25']='BTN';
		$country['26']='BOL';
		$country['27']='BIH';
		$country['28']='BWA';
		$country['29']='BVT';
		$country['30']='BRA';
		$country['31']='IOT';
		$country['32']='BRN';
		$country['33']='BGR';
		$country['34']='BFA';
		$country['35']='BDI';
		$country['36']='KHM';
		$country['37']='CMR';
		$country['38']='CAN';
		$country['39']='CPV';
		$country['40']='CYM';
		$country['41']='CAF';
		$country['42']='TCD';
		$country['43']='CHL';
		$country['44']='CHN';
		$country['45']='CXR';
		$country['46']='CCK';
		$country['47']='COL';
		$country['48']='COM';
		$country['49']='COG';
		$country['50']='COK';
		$country['51']='CRI';
		$country['52']='CIV';
		$country['53']='HRV';
		$country['54']='CUB';
		$country['55']='CYP';
		$country['56']='CZE';
		$country['57']='DNK';
		$country['58']='DJI';
		$country['59']='DMA';
		$country['60']='DOM';
		$country['61']='TMP';
		$country['62']='ECU';
		$country['63']='EGY';
		$country['64']='SLV';
		$country['65']='GNQ';
		$country['66']='ERI';
		$country['67']='EST';
		$country['68']='ETH';
		$country['69']='FLK';
		$country['70']='FRO';
		$country['71']='FJI';
		$country['72']='FIN';
		$country['73']='FRA';
		$country['74']='FXX';
		$country['75']='GUF';
		$country['76']='PYF';
		$country['77']='ATF';
		$country['78']='GAB';
		$country['79']='GMB';
		$country['80']='GEO';
		$country['81']='DEU';
		$country['82']='GHA';
		$country['83']='GIB';
		$country['84']='GRC';
		$country['85']='GRL';
		$country['86']='GRD';
		$country['87']='GLP';
		$country['88']='GUM';
		$country['89']='GTM';
		$country['90']='GIN';
		$country['91']='GNB';
		$country['92']='GUY';
		$country['93']='HTI';
		$country['94']='HMD';
		$country['95']='HND';
		$country['96']='HKG';
		$country['97']='HUN';
		$country['98']='ISL';
		$country['99']='IND';
		$country['100']='IDN';
		$country['101']='IRN';
		$country['102']='IRQ';
		$country['103']='IRL';
		$country['104']='ISR';
		$country['105']='ITA';
		$country['106']='JAM';
		$country['107']='JPN';
		$country['108']='JOR';
		$country['109']='KAZ';
		$country['110']='KEN';
		$country['111']='KIR';
		$country['112']='PRK';
		$country['113']='KOR';
		$country['114']='KWT';
		$country['115']='KGZ';
		$country['116']='LAO';
		$country['117']='LVA';
		$country['118']='LBN';
		$country['119']='LSO';
		$country['120']='LBR';
		$country['121']='LBY';
		$country['122']='LIE';
		$country['123']='LTU';
		$country['124']='LUX';
		$country['125']='MAC';
		$country['126']='MKD';
		$country['127']='MDG';
		$country['128']='MWI';
		$country['129']='MYS';
		$country['130']='MDV';
		$country['131']='MLI';
		$country['132']='MLT';
		$country['133']='MHL';
		$country['134']='MTQ';
		$country['135']='MRT';
		$country['136']='MUS';
		$country['137']='MYT';
		$country['138']='MEX';
		$country['139']='FSM';
		$country['140']='MDA';
		$country['141']='MCO';
		$country['142']='MNG';
		$country['143']='MSR';
		$country['144']='MAR';
		$country['145']='MOZ';
		$country['146']='MMR';
		$country['147']='NAM';
		$country['148']='NRU';
		$country['149']='NPL';
		$country['150']='NLD';
		$country['151']='ANT';
		$country['152']='NCL';
		$country['153']='NZL';
		$country['154']='NIC';
		$country['155']='NER';
		$country['156']='NGA';
		$country['157']='NIU';
		$country['158']='NFK';
		$country['159']='MNP';
		$country['160']='NOR';
		$country['161']='OMN';
		$country['162']='PAK';
		$country['163']='PLW';
		$country['164']='PAN';
		$country['165']='PNG';
		$country['166']='PRY';
		$country['167']='PER';
		$country['168']='PHL';
		$country['169']='PCN';
		$country['170']='POL';
		$country['171']='PRT';
		$country['172']='PRI';
		$country['173']='QAT';
		$country['174']='REU';
		$country['175']='ROM';
		$country['176']='RUS';
		$country['177']='RWA';
		$country['178']='KNA';
		$country['179']='LCA';
		$country['180']='VCT';
		$country['181']='WSM';
		$country['182']='SMR';
		$country['183']='STP';
		$country['184']='SAU';
		$country['185']='SEN';
		$country['186']='SYC';
		$country['187']='SLE';
		$country['188']='SGP';
		$country['189']='SVK';
		$country['190']='SVN';
		$country['191']='SLB';
		$country['192']='SOM';
		$country['193']='ZAF';
		$country['194']='SGS';
		$country['195']='ESP';
		$country['196']='LKA';
		$country['197']='SHN';
		$country['198']='SPM';
		$country['199']='SDN';
		$country['200']='SUR';
		$country['201']='SJM';
		$country['202']='SWZ';
		$country['203']='SWE';
		$country['204']='CHE';
		$country['205']='SYR';
		$country['206']='TWN';
		$country['207']='TJK';
		$country['208']='TZA';
		$country['209']='THA';
		$country['210']='TGO';
		$country['211']='TKL';
		$country['212']='TON';
		$country['213']='TTO';
		$country['214']='TUN';
		$country['215']='TUR';
		$country['216']='TKM';
		$country['217']='TCA';
		$country['218']='TUV';
		$country['219']='UGA';
		$country['220']='UKR';
		$country['221']='ARE';
		$country['222']='GBR';
		$country['223']='USA';
		$country['224']='UMI';
		$country['225']='URY';
		$country['226']='UZB';
		$country['227']='VUT';
		$country['228']='VAT';
		$country['229']='VEN';
		$country['230']='VNM';
		$country['231']='VGB';
		$country['232']='VIR';
		$country['233']='WLF';
		$country['234']='ESH';
		$country['235']='YEM';
		$country['238']='ZMB';
		$country['239']='ZWE';
		$country['240']='ENG';
		$country['241']='SCO';
		$country['242']='WAL';
		$country['243']='ALA';
		$country['244']='NEI';
		$country['245']='MNE';
		$country['246']='SRB';
		return $country;
	}
  	
}


?>

