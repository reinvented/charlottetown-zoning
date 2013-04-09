#!/usr/bin/php
<?php

$fp = fopen("../zones/zones.csv","r");
$fo = fopen("kml-styles.xml","w");
while(!feof($fp)) {
	list($abbrev,$fullname,$colour) = explode(",",chop(fgets($fp,4096)));
	fwrite($fo,"<Style id=\"zoning_" . $abbrev . "_hl\">\n");
	fwrite($fo,"	<IconStyle>\n");
	fwrite($fo,"		<scale>1.3</scale>\n");
	fwrite($fo,"		<Icon>\n");
	fwrite($fo,"			<href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href>\n");
	fwrite($fo,"		</Icon>\n");
	fwrite($fo,"		<hotSpot x=\"20\" y=\"2\" xunits=\"pixels\" yunits=\"pixels\"/>\n");
	fwrite($fo,"	</IconStyle>\n");
	fwrite($fo,"	<LineStyle>\n");
	fwrite($fo,"		<color>ff000000</color>\n");
	fwrite($fo,"		<width>0.5</width>\n");
	fwrite($fo,"	</LineStyle>\n");
	fwrite($fo,"	<PolyStyle>\n");
	fwrite($fo,"		<color>$colour</color>\n");
	fwrite($fo,"	</PolyStyle>\n");
	fwrite($fo,"</Style>\n");
	fwrite($fo,"<Style id=\"zoning_" . $abbrev . "\">\n");
	fwrite($fo,"	<IconStyle>\n");
	fwrite($fo,"		<scale>1.1</scale>\n");
	fwrite($fo,"		<Icon>\n");
	fwrite($fo,"			<href>http://maps.google.com/mapfiles/kml/pushpin/ylw-pushpin.png</href>\n");
	fwrite($fo,"		</Icon>\n");
	fwrite($fo,"		<hotSpot x=\"20\" y=\"2\" xunits=\"pixels\" yunits=\"pixels\"/>\n");
	fwrite($fo,"	</IconStyle>\n");
	fwrite($fo,"	<LineStyle>\n");
	fwrite($fo,"		<color>ff000000</color>\n");
	fwrite($fo,"		<width>0.5</width>\n");
	fwrite($fo,"	</LineStyle>\n");
	fwrite($fo,"	<PolyStyle>\n");
	fwrite($fo,"		<color>$colour</color>\n");
	fwrite($fo,"	</PolyStyle>\n");
	fwrite($fo,"</Style>\n");
	fwrite($fo,"<StyleMap id=\"zoning_" . $abbrev . "_colour\">\n");
	fwrite($fo,"	<Pair>\n");
	fwrite($fo,"		<key>normal</key>\n");
	fwrite($fo,"		<styleUrl>#zoning_" . $abbrev . "</styleUrl>\n");
	fwrite($fo,"	</Pair>\n");
	fwrite($fo,"	<Pair>\n");
	fwrite($fo,"		<key>highlight</key>\n");
	fwrite($fo,"		<styleUrl>#zoning_" . $abbrev . "_hl</styleUrl>\n");
	fwrite($fo,"	</Pair>\n");
	fwrite($fo,"</StyleMap>\n");
}
fclose($fp);
fclose($fo);