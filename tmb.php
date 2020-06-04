<?php

spl_autoload_register(function($c) { @include_once strtr($c, '\\_', '//').'.php'; });
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

use \RSSWriter\Feed;
use \RSSWriter\Channel;
use \RSSWriter\Item;

error_reporting(E_WARNING);

$username = isset($_GET['username']) ? $_GET['username'] : NULL;
$count    = isset($_GET['count'])    ? $_GET['count']    : 25;
$offset   = isset($_GET['offset'])   ? $_GET['offset']   : 0;

if (!$username)
    return;

$ch = curl_init();

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

do
{
    curl_setopt($ch, CURLOPT_URL, "https://${username}.tumblr.com/api/read/json/?start=${offset}&num=${count}");
    $page = curl_exec($ch);
    if ($page == FALSE)
        return;

    //remove JS declaration
    $len = strlen($page);
    $page = substr($page, 22, $len-24);

    $blog = json_decode($page, true);
    $posts = (object)array_merge((array)$posts, (array)$blog['posts']);

    if ($count > $blog['posts-total'])
        $count = $blog['posts-total'];

    $offset += 50;
    $count  -= 50;
} while ($count >= 50);

date_default_timezone_set($blog['tumblelog']['timezone']);

$feed = new Feed();
$channel = new Channel();

$channel
	->title("{$blog['tumblelog']['title']}")
	->description("<img src=\"{$blog['posts'][0]['tumblelog']['avatar_url_512']}\" />" . "{$blog['tumblelog']['description']}")
	->url($blog['tumblelog']['cname'] ? "https://{$blog['tumblelog']['cname']}/"
                                      : "https://{$username}.tumblr.com")
	->pubdate($blog['posts'][0]['unix-timestamp'])
	->lastBuildDate(time())
	->ttl(30)
	->appendTo($feed);

foreach($posts as $post)
{
    switch ($post['type'])
    {
        case 'photo':
            if ($post["photo-caption"] != "")
                $item_title = strip_tags($post["photo-caption"]);
            else
                $item_title = $post["date"];

            unset($image);
            if (empty($post['photos']))
                $image = "<img src=\"{$post['photo-url-1280']}\" />";
            else foreach($post['photos'] as $photo)
                $image .= "<img src=\"{$photo['photo-url-1280']}\" />";

            $item_body = $image . $post["photo-caption"];
        break;

        case 'audio':
            if ($post['id3-title'] != "")
                $item_title = $post['id3-title'];
            else if ($post['audio-caption'] != "")
                $item_title = $post['audio-caption'];
            else
                $item_title = $post["date"];

            $item_body = $post['audio-player'] . $post['audio-caption'];
            
        break;
        
        case 'video':
            if ($post["video-caption"] != "")
                $item_title = strip_tags($post["video-caption"]);
            else
                $item_title = $post["date"];

            if (strpos($post["video-player"], 'hdUrl":"'))
            {
                $st = strpos($post["video-player"], 'hdUrl":"') + 8;
                $ed = strpos($post["video-player"], '"', $st+1);
                $video = substr($post["video-player"], $st, $ed-$st);
                
                $item_body = "<p><video width=\"100%\" controls=\"true\">
                                <source src=\"{$video}\"/>
                              </video></p>"
                           . $post["video-caption"];
            }
            else if (strpos($post["video-player"], 'src="'))
            {
                $st = strpos($post["video-player"], 'src="') + 5;
                $ed = strpos($post["video-player"], '"', $st+1);
                $video = substr($post["video-player"], $st, $ed-$st);
                
                $item_body = "<p><video width=\"100%\" controls=\"true\">
                                <source src=\"{$video}\"/>
                              </video></p>"
                           . $post["video-caption"];
            }
            else
                $item_body = $post["video-player"] . $post["video-caption"];
        break;

        case 'answer':
            $item_title = $post['question'];
            $item_body  = "<blockquote>{$post['question']}</blockquote>" . $post['answer'];
        break;

        case 'quote':
            $item_title = strip_tags($post['quote-source']);
            $item_body  = "<p>{$post['quote-source']}</p><blockquote>{$post['quote-text']}</blockquote>";
        break;

        case 'link':
            $item_title = "<p>{$post['link-text']}</p>";
            $item_body  = "<a href=\"{$post['link-url']}\">{$post['link-text']}</a>" . $post['link-description'];
        break;

        case 'regular':
            $item_title = $post['regular-title'] ? $post['regular-title']
                                                 : $post["date"];
            $item_body  = $post['regular-body'];
        break;

        default:
            $item_title = "FIXME: {$post['type']}";
            $item_body = "<b>Unknown post type: {$post['type']}</b>";
        break;
    }

    if (strlen($item_title) > 120)
        $item_title = mb_substr($item_title, 0, 120) . '…';
    
    $item = new Item();
	$item
		->title($item_title)
        ->description("$item_body")
		->author($post['reblogged-root-title'] ? $post['reblogged-root-title']
                                               : $post['tumblelog']['title'])
		->url($post['url-with-slug'])
		->pubDate($post['unix-timestamp'])
		->guid("{$username}/{$post['id']}", false)
		->appendTo($channel);

   	if (isset($post['tags']))
    foreach($post['tags'] as $tag)
		$item->category($tag);
}

echo $feed;

?>
