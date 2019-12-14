<?php
error_reporting(E_ALL);

$q = isset($_GET['q']) ? $_GET['q'] : NULL;

if ($q == NULL)
    exit(0);

$eq = urlencode($q);

$ch = curl_init("https://anidb.net/perl-bin/animedb.pl?show=json&action=search&type=anime&query=$eq");

curl_setopt_array($ch,
                  array(
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64; rv:60.9)',
                    CURLOPT_HTTPHEADER => array('X-LControl: x-no-cache'),
                    CURLOPT_RETURNTRANSFER => true)
                  );

$res = curl_exec($ch);

if ($res === FALSE)
	exit(1);

$obj = json_decode($res, TRUE);

$suggestions[0] = $q;

foreach($obj as $elem)
{
    $suggestions[1][] = $elem["name"];
    $suggestions[2][] = $elem["desc"];
    $suggestions[3][] = $elem["link"];
}

echo json_encode($suggestions);

?>
