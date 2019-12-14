<?php
error_reporting(E_ALL);

$q = isset($_GET['q']) ? $_GET['q'] : NULL;

if ($q == NULL)
    exit(0);

$eq = urlencode($q);

$ch = curl_init("https://catalog.api.2gis.ru/2.0/suggest/list?key=ruhmxf0953&q=$eq&region_id=3&types=adm_div.city,adm_div.district,adm_div.division,adm_div.living_area,adm_div.place,adm_div.settlement,attraction,building,crossroad,foreign_city,road,route_type,route,station.metro,station,street,user_queries");

curl_setopt_array($ch,
                  array(
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64; rv:60.9)',
                    CURLOPT_RETURNTRANSFER => true)
                  );

$res = curl_exec($ch);

if ($res === FALSE)
	exit(1);

$obj = json_decode($res, TRUE);

$response[0] = $q;

foreach($obj["result"]["items"] as $elem)
{
    $response[1][] = $elem["hint"]["text"];
}

echo json_encode($response);

?>
