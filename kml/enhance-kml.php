#!/usr/bin/php
<?php
/**
  * enhance-kml.php - A PHP script to take a KML version of the Charlottetown Zoning
  * Map created by exporting the canonical ESRI shapefile as a WGS 84 KML using QGis
  * and to enhance it by adding civic addresses, more human-readable version of the
  * perimeter and area information, and colour-coded polygons that map the city's
  * official zoning map PDF as closely as possible.
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
  * @version 0.1, April 9, 2013
  * @link https://github.com/reinvented/charlottetown-zoning/tree/master/kml
  * @author Peter Rukavina <peter@rukavina.net>
  * @copyright Copyright &copy; 2013, Reinvented Inc.
  * @license http://www.fsf.org/licensing/licenses/gpl.txt GNU Public License
  */

$fp = fopen("../zones/zones.csv","r");
while(!feof($fp)) {
	list($abbrev,$fullname,$colour) = explode(",",chop(fgets($fp,4096)));
	$zonename[$abbrev] = $fullname;
	$zonecolour[$abbrev] = $colour;
}
fclose($fp);

$counter = 0;
$xml = simplexml_load_file('charlottetown_zoning.kml');
foreach($xml->Document->Folder->Placemark as $pm) {
	$zoning = (string)$pm->ExtendedData->SchemaData->SimpleData[3];
	$output[$zoning] .= "<Placemark>\n";
	$output[$zoning] .= "<styleUrl>#zoning_" . $zoning . "_colour</styleUrl>\n";
	if (intval($pm->ExtendedData->SchemaData->SimpleData[0]) == 0) {
		$output[$zoning] .= "<name>NO PID</name>\n";
	}
	else {
		$address = getCivicAddress(intval($pm->ExtendedData->SchemaData->SimpleData[0]));
		$output[$zoning] .= "<name>PID " . intval($pm->ExtendedData->SchemaData->SimpleData[0]) . "</name>\n";
	}
	$abbrev = (string)$pm->ExtendedData->SchemaData->SimpleData[3];
	$perimeter = (double)$pm->ExtendedData->SchemaData->SimpleData[2];
	$perimeter = number_format($perimeter,0);
	$area = (double)$pm->ExtendedData->SchemaData->SimpleData[1];
	$acres = number_format($area * 0.00024711,2);
	$area = number_format($area,0);
	print $counter . " - " . $address . "\n";
	$counter++;
	$output[$zoning] .= "<description><![CDATA[";
	if ($address) {
		$output[$zoning] .= "<h3>$address</h3><h4>Zoned \"" . $zonename[$abbrev] . "\" ($abbrev)</h4><ul><li>Perimeter: $perimeter m</li><li>Area: $area m² ($acres acres)</li></ul>\n";
	}
	else {
		$output[$zoning] .= "<h4>Zoned \"" . $zonename[$abbrev] . "\" ($abbrev)</h4><ul><li>Perimeter: $perimeter m</li><li>Area: $area m² ($acres acres)</li></ul>\n";
	}
	$output[$zoning] .= "<h4>Geolinc Plus (Requires Subscription)</h4>";
	$output[$zoning] .= "<ul>";
	$output[$zoning] .= "<li><a href=\"http://eservices.gov.pe.ca/pei-icis/secure/assessment/view.do?parcelNumber=" . intval($pm->ExtendedData->SchemaData->SimpleData[0]). "&leaseCode=0\">Assessment</a></li>";
	$output[$zoning] .= "<li><a href=\"http://eservices.gov.pe.ca/pei-icis/secure/assessment/viewRegistry.do?parcelNumber=" . intval($pm->ExtendedData->SchemaData->SimpleData[0]). "&leaseCode=0&registry=true\">Registry</a></li>";
	$output[$zoning] .= "<li><a href=\"http://eservices.gov.pe.ca/pei-icis/secure/assessment/viewTaxValues.do?parcelNumber=" . intval($pm->ExtendedData->SchemaData->SimpleData[0]). "&leaseCode=0\">Tax Value</a></li>";
	$output[$zoning] .= "</ul>";
	$output[$zoning] .= "]]></description>";
	$output[$zoning] .= $pm->Polygon->asXML();
	$output[$zoning] .= "</Placemark>\n";
}

$fp = fopen("charlottetown_zoning_enhanced.kml","w");
fwrite($fp,"<" . "?xml version=\"1.0\" encoding=\"utf-8\" ?" . ">\n");
fwrite($fp,"<kml xmlns=\"http://www.opengis.net/kml/2.2\">\n");
fwrite($fp,"<Document><name>Charlottetown Zoning Map</name>\n");
fwrite($fp,file_get_contents("kml-styles.xml") . "\n");
foreach($output as $key => $value) {
	fwrite($fp,"<Folder>\n");
	fwrite($fp,"<name>" . $zonename[$key] . "</name>\n");
	fwrite($fp,$output[$key]);
	fwrite($fp,"</Folder>\n");
}
fwrite($fp,"</Document></kml>\n");
fclose($fp);
	

/**
  * Match civic addresses to zoning entries, matching on PID.
  */
function getCivicAddress($pid) {
	$db = new SQLite3('../lookup/zoning.db');
	$results = $db->query("SELECT * from civicaddress where pid='$pid'");
	$row = $results->fetchArray();
	if (count($row) == 0) {
		return false;
	}
	else {
		return $row['street_no'] . " " . $row['street_nm'];
	}
}