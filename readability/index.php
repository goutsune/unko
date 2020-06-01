<?php

$url = isset($_GET['url']) ? $_GET['url'] : NULL;
if ($url == NULL)
    exit(1);

$clean   = isset($_GET['clean'])   ? $_GET['clean']   : false;
$chars   = isset($_GET['chars'])   ? $_GET['chars']   : 300;
$abs_url = isset($_GET['abs_url']) ? $_GET['abs_url'] : true;
$remjs   = isset($_GET['remjs'])   ? $_GET['remjs']   : true;
$subst   = isset($_GET['subst'])   ? $_GET['subst']   : true;
$norm    = isset($_GET['norm'])    ? $_GET['norm']    : true;

require_once __DIR__ . '/vendor/autoload.php';

use andreskrey\Readability\Readability;
use andreskrey\Readability\Configuration;
use andreskrey\Readability\ParseException;

$configuration = new Configuration([
    'FixRelativeURLs'    => $abs_url,
    'CleanConditionally' => $clean,
    'SummonCtulhu'       => $remjs,
    'CharThresold'       => $chars,
    'SubstituteEntities' => $subst,
    'NormalizeEntities'  => $norm,
    'OriginalURL'        => $url,
]);

$readability = new Readability($configuration);
$html = file_get_contents($url);

if ($html === false)
{
    echo "<br>Error fetching URL: " . $url;
    exit(1);
}

try
{
    $readability->parse($html);
}
catch (ParseException $e)
{
    echo sprintf('Error processing text: %s', $e->getMessage());
}
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 TRANSITIONAL//EN">
<html>
<head>
<title><?= $readability->getTitle(); ?></title>
<style>
    body {font-family: Georgia; text-align:justify;}
    body {color: #5b4636; background-color:#f4ecd8;}
    img {max-width: 100%;}
</style>
</head>
<body>
    <h2 style="text-align:center;"><?= $readability->getTitle(); ?></h2>
    <h4>
        <span style="margin-right:12px;"><?= $readability->getAuthor()?></span>
        <span><?= $readability->getExcerpt(); ?></span>
    </h4>
    <div style="padding:0px 32px;">
        <img src="<?= $readability->getImage(); ?>"/>
        <?= $readability->getContent();?>
    </div>
</body>
</html>
