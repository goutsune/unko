<?php

spl_autoload_register(function($c) { @include_once strtr($c, '\\_', '//').'.php'; });
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

use \RSSWriter\Feed;
use \RSSWriter\Channel;
use \RSSWriter\Item;

error_reporting(E_ALL);
require_once('./VK/VK.php');
require_once('./VK/VKException.php');
require_once('./config.php');

try {
	$post_id  = isset($_GET['post_id'])  ? $_GET['post_id']  : NULL;
	$owner_id = isset($_GET['owner_id']) ? $_GET['owner_id'] : NULL;
	$count    = isset($_GET['count'])    ? $_GET['count']    : 100;
	$offset   = 0;

	$vk = new VK\VK($vk_config['app_id'], $vk_config['api_secret'], 
					$vk_config['access_token']); // Use your app_id and api_secret

	date_default_timezone_set('UTC');

	$feed = new Feed();

	$channel = new Channel();
	$channel
		->title        ("Comments on post {$post_id}")
		->description  ("Comments on post {$post_id}")
		->url          ("https://vk.com/{$owner_id}_{$post_id}")
		->pubdate	   (time())
		->lastBuildDate(time())
		->ttl(30)
		->appendTo($feed);

	$response = $vk->api('wall.getComments',
		array(
			'post_id'  => $post_id,
			'count'    => $count,
			'owner_id' => $owner_id,
			'offset'   => $offset,
			'sort'     => 'asc',
			'v'        => '5.67',
			'extended' => '1'));

	foreach($response['response']['profiles'] as $profile)
		$profiles[$profile['id']] = $profile['first_name'] . ' ' . $profile['last_name'];

	foreach($response['response']['groups'] as $profile)
		$profiles["-" . $profile['id']] = $profile['name'];

    foreach($response['response']['items'] as $post)
	{
		$item = new Item();
		$description = preg_replace('`#([^[:blank:]<])+([[:blank:](<br>)])*`', '', $post['text']);
		$description = preg_replace('`\[([0-9a-z]+)\|([^]]+)\]`', '<a href="https://vk.com/$1">$2</a>', $description);
        $posts[$post['id']] = "<i>{$description}</i>";
        $description = "<p style='white-space:pre-line;'>{$description}</p>";

        if (isset($post['reply_to_comment']))
            $description = $posts[$post['reply_to_comment']] . $description;

		if(isset($post['attachments']))
		foreach($post['attachments'] as $attachment)
		{
			switch ($attachment['type'])
			{
				case 'photo':
				{
					$img = $attachment['photo']['photo_604'];

					if     (isset($attachment['photo']['photo_1280']))
						$src = $attachment['photo']['photo_1280'];
					elseif (isset($attachment['photo']['photo_807']))
						$src = $attachment['photo']['photo_807'];
					elseif (isset($attachment['photo']['photo_604']))
						$src = $attachment['photo']['photo_604'];

					$description .= "<br/><a href='{$src}'><img src='{$img}'/></a>";
					break;
				}
				case 'sticker':
				{
					$description .= "<br/><img src='{$attachment['sticker']['photo_64']}'/>";
					break;
				}
				case 'audio': 
				{
					$plist = 1;
					$description .= "<p>{$attachment['audio']['artist']} — {$attachment['audio']['title']}</a></p>";
					$item->enclosure(substr($attachment['audio']['url'], 0, strpos($attachment['audio']['url'], '?')), 0, 'audio/mpeg');
					break;
				}
				case 'doc': 
				{
					$description .= "<br/><a href='{$attachment['doc']['url']}'>{$attachment['doc']['title']}</a>";
					$item->enclosure($attachment['doc']['url'], 0, 'application/octet-stream');
					break;
				}
				case 'link': 
				{
					$description .= "<br/><a href='{$attachment['link']['url']}'>{$attachment['link']['title']}</a>";
					break;
				}
				case 'video': 
				{
					$description .= "<br/><a href='http://vk.com/video{$attachment['video']['owner_id']}_{$attachment['video']['id']}'><img src='{$attachment['video']['photo_320']}'/></a>";
					break;
				}
			}
		}
		unset($attachment);
		$title = "#{$post['id']} {$profiles[$post['from_id']]}";
		if (isset($post['reply_to_comment']))
			$title .= " in reply to #{$post['reply_to_comment']}";
		$item
			->title($title)
			->description("{$description}")
			->url("https://vk.com/wall{$owner_id}_{$post_id}?reply={$post['id']}")
			->pubDate($post['date'])
			->guid("{$post['id']}", false)
			->appendTo($channel);

		unset($description);
	}

	preg_match_all('`#\K([^[:blank:]<]+)`', $post['text'],$matches);
	foreach($matches[0] as $match)
		$item->category($match);

	unset($match);
	unset($titleprep);
	unset($response);
   
	echo $feed;
    
} catch (VK\VKException $error)
{
    echo $error->getMessage();
}
