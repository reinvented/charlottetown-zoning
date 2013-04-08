#!/usr/bin/php
<?php
/**
  * merge-zoning-with-civic-address.php - A PHP script to merge the Charlottetown Zoning and 
  * Development Bylaw map zoning designations with PEI Civic Address data to create a JSON
  * object of the merged data suitable for building a standalone lookup tool.
  *
  * Requires shapelib -- http://download.osgeo.org/shapelib/
  *
  * This program is free software; you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation; either version 2 of the License, or (at
  * your option) any later version.
  *
  * This program is distributed in the hope that it will be useful, but
  * WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
  * General Public License for more details.
  * 
  * You should have received a copy of the GNU General Public License
  * along with this program; if not, write to the Free Software
  * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307
  * USA
  *
  * @version 0.1, April 8, 2013
  * @link https://github.com/reinvented/charlottetown-zoning/tree/master/lookup
  * @author Peter Rukavina <peter@rukavina.net>
  * @copyright Copyright &copy; 2013, Reinvented Inc.
  * @license http://www.fsf.org/licensing/licenses/gpl.txt GNU Public License
  */
  
downloadCivicAddressData();
makeCivicAddressDatabase();
makeZoningDatabase();
makeLookupDatabase();
dumpJSON();

/**
  * Download the entire province's civic address data.
  */
function downloadCivicAddressData() {

    $counties = array("QUN" => "Queens County",
                      "KNS" => "Kings County",
                      "PRN" => "Prince County");

    $catstring = "";
    foreach ($counties as $county => $description) {
		    $url = "http://www.gov.pe.ca/civicaddress/download/dodownload.php3?county=" . $county . "&downloadformat=tab&downloadfields[]=street_no&downloadfields[]=street_nm&downloadfields[]=comm_nm&downloadfields[]=apt_no&downloadfields[]=county&downloadfields[]=latitude&downloadfields[]=longitude&downloadfields[]=pid&downloadfields[]=unique_id&downloadfields[]=census";
		    $command = "wget \"$url\" --quiet -O /tmp/" . $county . ".txt";
				system($command);
        $catstring .= "/tmp/" . $county . ".txt ";
    }

    system("cat $catstring > /tmp/civicaddress.txt");
}

/**
  * Turn the text file we just downloaded into an SQLite3 table
  */
function makeCivicAddressDatabase() {

	$db = new SQLite3('zoning.db');
	$db->exec('CREATE TABLE civicaddress (`street_no` int(11),`street_nm` char(50),`comm_nm` char(30),`apt_no` char(10),`county` char(3),`latitude` real(11,5),`longitude` real(11,5),`pid` int(11),`unique_id` int(11),`census` int(11))');
	$fp = fopen("/tmp/civicaddress.txt",'r');
	while(!feof($fp)) {
		$data = chop(fgets($fp,4096));
		$parts = explode("\t",$data);
		$db->exec("INSERT into civicaddress values (
								'" . $parts[0] . "',
								'" . $parts[1] . "',
								'" . $parts[2] . "',
								'" . $parts[3] . "',
								'" . $parts[4] . "',
								'" . $parts[5] . "',
								'" . $parts[6] . "',
								'" . $parts[7] . "',
								'" . $parts[8] . "',
								'" . $parts[9] . "')");
	}
	fclose($fp);
}

/**
  * Turn the zoning dBASE file into an SQLite3 table
  * This requires shapelib from http://download.osgeo.org/shapelib/ and its dbfdump tool.
  */
function makeZoningDatabase() {
	system("dbfdump ../shapefiles/City_Zoning_Apr-2012.dbf > City_Zoning_Apr-2012.txt");

	$db = new SQLite3('zoning.db');
	$db->exec('CREATE TABLE zones (`pid` int(11),`area` real(16,5),`perimeter` real(16,5),`zoning` char(4))');
	$fp = fopen("City_Zoning_Apr-2012.txt",'r');
	$headerline = chop(fgets($fp,4096));
	while(!feof($fp)) {
		$data = chop(fgets($fp,4096));
		$pid = intval(substr($data,0,19));
		$area = substr($data,20,19);
		$perimeter = substr($data,40,19);
		$zoning = substr($data,60,4);
		$db->exec("INSERT into zones values (
								'" . $pid . "',
								'" . $area . "',
								'" . $perimeter . "',
								'" . $zoning . "')");
	}
}

/**
  * Match civic addresses to zoning entries, matching on PID.
  */
function makeLookupDatabase() {
	$db = new SQLite3('zoning.db');
	$db->exec('CREATE TABLE lookup (`pid` int(11),`area` real(16,5),`perimeter` real(16,5),`zoning` char(4),`street_no` int(11),`street_nm` char(50),`comm_nm` char(30),`apt_no` char(10),`county` char(3),`latitude` real(11,5),`longitude` real(11,5),`unique_id` int(11),`census` int(11))');

	$results = $db->query('SELECT * from zones');
	while ($row = $results->fetchArray()) {
		if ($row['pid'] > 0) {
			$results_address = $db->query("SELECT * from civicaddress where pid='" . $row['pid']. "'");
			$row_address = $results_address->fetchArray();
			if (count($row_address) == 0) {
				print "No match for " . $row['pid'] . "\n";
			}
			else {
				$db->exec("INSERT into lookup values (
								'" . $row['pid'] . "',
								'" . $row['area'] . "',
								'" . $row['perimeter'] . "',
								'" . $row['zoning'] . "',
								'" . $row_address ['street_no'] . "',
								'" . $row_address ['street_nm'] . "',
								'" . $row_address ['comm_nm'] . "',
								'" . $row_address ['apt_no'] . "',
								'" . $row_address ['county'] . "',
								'" . $row_address ['latitude'] . "',
								'" . $row_address ['longitude'] . "',
								'" . $row_address ['unique_id'] . "',
								'" . $row_address ['census'] . "')");
			}
		}
	}
}
	
/**
  * Make a JSON object out of the combined data.
  */	
function dumpJSON() {
	$db = new SQLite3('zoning.db');
	$results = $db->query('SELECT * from lookup where street_no <> "" order by comm_nm,street_nm,street_no ');
	while ($row = $results->fetchArray(SQLITE_ASSOC)) {
		$rows[] = $row;
	}
	$fp = fopen("zoning_lookup.js",'w');
	fwrite($fp,"var zoning = " . json_encode($rows));
	fclose($fp);
}

								