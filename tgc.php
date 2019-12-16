<?php
error_reporting(E_WARNING);
spl_autoload_register(function($c)
{
	@include_once strtr($c, '\\_', '//') . '.php';
});
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__ . '/Source');

use \Suin\RSSWriter\Feed;
use \Suin\RSSWriter\Channel;
use \Suin\RSSWriter\Item;

require('./phpQuery/phpQuery.php');

$username = isset($_GET['username']) ? $_GET['username'] : NULL;
$count    = isset($_GET['count'])    ? $_GET['count']    : 2;
$link_fw  = isset($_GET['link_fw'])  ? $_GET['link_fw']  : FALSE;

if (!$username)
	return;

$ch = curl_init("https://t.me/s/${username}");

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

$page = curl_exec($ch);
if ($page == FALSE)
	return;

$doc  = phpQuery::newDocument($page);

//replace emoji with images with just unicode emoji
foreach(pq('')->find('.emoji') as $elem)
    pq($elem)->replaceWith(pq($elem)->text());

$chan_title = pq('div.tgme_channel_info_header_title')->text();
$chan_desc  = pq('div.tgme_channel_info_description')->wrapInner('<p></p>')->html();
$header_img = pq('i.tgme_page_photo_image > img')->attr('src');

$feed = new Feed();
$channel = new Channel();

$channel
	->title("{$chan_title}")
	->description("<img src='{$header_img}'></img><br/{$chan_desc}")
	->url("https://t.me/{$username}")
	->pubdate(time())
	->lastBuildDate(time())
	->ttl(30)
	->appendTo($feed);

request:

if (strstr(pq('a.tme_messages_more')->attr('href'), '?before=') != FALSE)
    $prev_page = "https://t.me" . pq('a.tme_messages_more')->attr('href');
else
    $prev_page = FALSE;

foreach ( $msgs = pq('.tgme_container')->find('.tgme_widget_message_wrap') as $msg)
{

	$doc = phpQuery::newDocument(pq($msg)->html());

	if (pq('div')->hasClass('tgme_widget_message_error'))
	{
		if (strpos(pq('.tgme_widget_message_error')->text(), "Post not found") !== FALSE)
		{
			$debug .= "<!-- Err post #{$i} not found ({$errcnt}/{$limit} in chain) -->\n";
			$errcnt++;

			if ($errcnt > $limit)
				break;
			continue;
		} else if (strpos(pq('.tgme_widget_message_error')->text(), "Channel with username") !== FALSE)
		{
			sleep(5);
			continue;
		} else
		{
			$debug .= "<!-- Uh huh? -->\n";
		}
			continue;
	} else

    if (! pq('div')->hasClass('tgme_widget_message_poll_question'))
        $checksum = sprintf('/%08x', crc32(pq('.tgme_widget_message_bubble > div.tgme_widget_message_text')->html()));
    else
        $checksum = '/00000000'; 

    $titleprep = pq('.tgme_widget_message_bubble > div.tgme_widget_message_text')->html();
    $titleprep = preg_replace('`<br>`', "\n", $titleprep);
    $titleprep = preg_replace('`</?.+?>`', "", $titleprep);
    $titleprep = strtok($titleprep,"\n");
	$item_title = mb_strimwidth($titleprep, 0, 70, "…");
    if (pq('div')->hasClass('tgme_widget_message_poll_question'))
        $item_title = pq('.tgme_widget_message_poll_question')->text();

	$item_body = '';

    //////replace <i> tags which are normally interpretted as italic with proper <img>
    foreach(pq('')->find('i[style^=background-image]') as $image)
    {
        $img = pq($image)->attr('style');
        $img = preg_replace('`.*background-image:url\(\'(.+?)\'\).*`', '<img src="$1" /><br/>', $img);
        pq($image)->replaceWith($img);
    }

    if (pq('a')->hasClass('tgme_widget_message_reply'))
    {
        $reply_link = pq('a.tgme_widget_message_reply')->attr('href');
        pq('.tgme_widget_message_author')->wrapInner("<a href=\"$reply_link\"></a>");
        $item_body .= pq('a.tgme_widget_message_reply')->wrapInner('<blockquote></blockquote>')->html();
    }

    if (pq('div')->hasClass('tgme_widget_message_grouped_layer'))
    {
        foreach(pq('')->find('.tgme_widget_message_photo_wrap') as $elem)
        {
		    $body_img = pq($elem)->attr('style');
            $body_img = preg_replace('`.*background-image:url\(\'(.+?)\'\).*`', '<img src="$1" /><br>', $body_img);
            $item_body .= $body_img;
        }
        
    } else if (pq('div')->hasClass('tgme_widget_message_photo'))
    {
		$body_img = pq('a.tgme_widget_message_photo_wrap')->attr('style');
		$body_img = preg_replace('`.*background-image:url\(\'(.+?)\'\).*`', '<img src="$1" /><br>', $body_img);
		$item_body .= $body_img;
	}

    if (pq('a')->hasClass('tgme_widget_message_service_photo'))
        $item_body .= pq('.tgme_widget_message_service_photo')->html();

	if (pq('div')->hasClass('tgme_widget_message_video_wrap'))
	{
		pq('div.link_preview_video_wrap')->removeAttr('style');
		pq('video')->removeAttr('id');
		pq('video')->attr('width', '100%');
		//pq('video')->attr('height', '650vh');
		pq('video')->attr('controls', "true");
		pq('video')->removeAttr('class');
		$item_body .= pq('div.tgme_widget_message_video_wrap')->wrapInner('<p></p>')->html();
	}

	if (pq('div')->hasClass('tgme_widget_message_roundvideo_wrap'))
	{
		pq('div.link_preview_roundvideo_wrap')->removeAttr('style');
		pq('video')->removeAttr('id');
		pq('video')->removeAttr('width');
		pq('video')->removeAttr('height');
		pq('video')->removeAttr('muted');
		pq('video')->attr('controls', "true");
		pq('video')->removeAttr('class');
		$item_body .= pq('div.tgme_widget_message_roundvideo_wrap')->wrapInner('<p></p>')->html();
	}

    if (pq('a')->hasClass('tgme_widget_message_voice_player'))
    {
        pq('audio')->removeAttr('id');
		pq('audio')->attr('controls', "true");
		pq('audio')->removeAttr('class');
		pq('audio')->removeAttr('data-ogg');
		pq('audio')->removeAttr('data-waveform');
		pq('audio')->removeAttr('width');
        $item_body .= pq('audio');
    }

	//////////////////////
	$item_body .= pq('div.tgme_widget_message_bubble > div.tgme_widget_message_text')->wrapInner('<p></p>')->html();
	//////////////////////

	if (pq('div')->hasClass('tgme_widget_message_sticker_wrap'))
		$item_body .= pq('.tgme_widget_message_sticker_wrap a')->html();

	if (pq('a')->hasClass('tgme_widget_message_link_preview'))
	{
		$pghref = pq('div.link_preview_site_name')->text();
		if (strstr($pghref, 'Telegraph'))
		{
			$ch = curl_init(pq('a.tgme_widget_message_link_preview')->attr('href'));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
			$tmppage = curl_exec($ch);
            if ($tmppage != FALSE)
            {
                $tmppage = str_replace('<head>', '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>', $tmppage);
                
                $tmpdoc = new DOMDocument('1.0');
                @$tmpdoc->loadHTML($tmppage);

                if ($tmpdoc->getElementById('tl_article_header')->lastChild != null)
                    $tmpdoc->getElementById('tl_article_header')->removeChild($tmpdoc->getElementById('tl_article_header')->lastChild);
                if ($tmpdoc->getElementById('_tl_editor')->childNodes != null)
                    $tmpdoc->getElementById('_tl_editor')->removeChild($tmpdoc->getElementById('_tl_editor')->childNodes->item(0));

                
                foreach ($tmpdoc->getElementsByTagName('img') as $image)
                    if ($image->getAttribute('src')[0] == '/')
                        $image->setAttribute('src', 'https://telegra.ph' . $image->getAttribute('src'));
                
                $item_body .= '<hr/>' . $tmpdoc->saveHTML($tmpdoc->getElementsByTagName('header')->item(0))
                                      . $tmpdoc->saveHTML($tmpdoc->getElementsByTagName('article')->item(0));
            }
		}
		else
		{
			if (pq('div')->hasClass('link_preview_video_wrap'))
				pq('div.link_preview_video_wrap')->removeAttr('style');
	
			if (pq('div')->hasClass('link_preview_embed_wrap'))
			{
				pq('div.link_preview_embed_wrap')->removeAttr('style');
				pq('div.link_preview_embed_wrap > iframe')->attr('width', '640');
				pq('div.link_preview_embed_wrap > iframe')->attr('height', '360');
			}
	
			$item_body .= pq('a.tgme_widget_message_link_preview')->wrapInner('<blockquote></blockquote>')->html();
		}
	}

	if (pq('div')->hasClass('tgme_widget_message_document'))
		$item_body .= pq('div.tgme_widget_message_document')->wrapInner('<blockquote></blockquote>')->html();

    if (pq('div')->hasClass('tgme_widget_message_poll'))
    {
        pq('a.tgme_widget_message_poll_options')->removeAttr('href');
        $item_body .= "<blockquote>" . pq('.tgme_widget_message_poll_question')->text() ."<br/>"
                            . pq('.tgme_widget_message_poll_type')->text() . "</blockquote><p>";
        foreach( pq('.tgme_widget_message_poll_options')->find('.tgme_widget_message_poll_option') as $entry )
            $item_body .= pq($entry)->find('.tgme_widget_message_poll_option_text')->text() .
                          " — " . pq($entry)->find('.tgme_widget_message_poll_option_percent')->text() . "<br/>";
        $item_body .= "</p>";
    }

    if (pq('*')->hasClass('tgme_widget_message_forwarded_from_name'))
        $item_body = pq('.tgme_widget_message_forwarded_from')->html() . '<blockquote>' . $item_body . '</blockquote>';
    
    /////////////
	if (pq('*')->hasClass('tgme_widget_message_forwarded_from_name'))
		$item_author = pq('.tgme_widget_message_forwarded_from_name')->text();
	else if (pq('*')->hasClass('tgme_widget_message_from_author'))
        $item_author = pq('.tgme_widget_message_from_author')->text();
    else
		$item_author = pq('.tgme_widget_message_owner_name')->text();

	if (pq('*')->hasClass('tgme_widget_message_forwarded_from_author'))
		$item_author .= ' (' . pq('.tgme_widget_message_forwarded_from_author')->text() . ')';
    /////////////

	$dateprep  = pq('.tgme_widget_message_date time')->attr('datetime');
	$item_date = strtotime($dateprep);
	if ($item_title == "")
		$item_title = pq('.tgme_widget_message_date > time')->attr('datetime');

    if ($link_fw)
    {
        if (pq('.tgme_widget_message_forwarded_from_name')->attr('href') != "")
            $item_guid = str_replace('https://t.me/', '', pq('.tgme_widget_message_forwarded_from_name')->attr('href'));
        else
            $item_guid = pq('.tgme_widget_message')->attr('data-post');
    } else
        $item_guid = pq('.tgme_widget_message')->attr('data-post') . $checksum;

	$item = new Item();
	$item
		->title($item_title)
		->author($item_author)
		->description("$item_body")
		->url(pq('.tgme_widget_message_date')->attr('href'))
		->pubDate($item_date)
		->guid($item_guid, false)
		->appendTo($channel);

	preg_match_all('`#\K([^[:blank:],.<"\']+)`', $item_body, $matches);

	foreach ($matches[0] as $match)
		$item->category($match);

}

if (is_numeric($count))
    $count--;

if (((is_numeric($count) and $count > 0) or $count == "all") and $prev_page != FALSE)
{
    curl_setopt($ch, CURLOPT_URL, $prev_page);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

    $page = curl_exec($ch);
    if ($page != FALSE)
    {
        $doc  = phpQuery::newDocument($page);
        //replace emoji with images with just unicode emoji, again
        foreach(pq('')->find('.emoji') as $elem)
            pq($elem)->replaceWith(pq($elem)->text());
        goto request;
    }
}

echo $feed;
