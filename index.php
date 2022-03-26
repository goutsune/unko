<?php

spl_autoload_register(function($c)
{
	@include_once strtr($c, '\\_', '//') . '.php';
});
set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

use \RSSWriter\Feed;
use \RSSWriter\Channel;
use \RSSWriter\Item;

error_reporting(E_WARNING);
require_once('./VK/VK.php');
require_once('./VK/VKException.php');
require_once('./config.php');

try {
	$domain   = isset($_GET['domain'])   ? $_GET['domain']   : NULL;
	$owner_id = isset($_GET['owner_id']) ? $_GET['owner_id'] : NULL;
	$count    = isset($_GET['count'])    ? $_GET['count']    : 50;
	$nocopy   = isset($_GET['nocopy'])   ? $_GET['nocopy']   : false;
	$offset   = isset($_GET['offset'])   ? $_GET['offset']   : 0;
	$comments = isset($_GET['comments']) ? $_GET['comments'] : false;
  $watch    = isset($_GET['watch'])    ? $_GET['watch']    : false;

	$vk = new VK\VK($vk_config['app_id'], $vk_config['api_secret'], $vk_config['access_token']); // Use your app_id and api_secret

	date_default_timezone_set('UTC');

	if (isset($domain))
			$group_id = $domain;
	else
			$group_id = -$owner_id;

	$groupinfo = $vk->api('groups.getById',
	array(
			'group_ids' => $group_id,
			'fields' => 'description,status,cover',
			'v' => '5.81'
	));

	if (!isset($groupinfo['response'][0]['description']))
	{
			header('HTTP/1.1 403 Forbidden');
			var_dump($groupinfo);
			exit(0);
	}

	$cover = $groupinfo['response'][0]['cover']['enabled'] ? $groupinfo['response'][0]['cover']['images'][count($groupinfo['response'][0]['cover']['images'])-1]['url'] : $groupinfo['response'][0]['photo_200'];

	$feed = new Feed();

	$channel = new Channel();
	$channel->title("{$groupinfo['response'][0]['name']}")
            ->description("<img src='{$cover}'/>
<p style='white-space:pre-line;'>{$groupinfo['response'][0]['description']}</p>")
            ->url("https://vk.com/{$domain}")
            ->pubdate(time())
            ->lastBuildDate(time())
            ->ttl(30)
            ->appendTo($feed);



	if ($count == 0) {
		$fcount = $response['response']['wall'][0] - $offset;
		$count  = 100;
	} elseif ($count > 100) {
		$fcount = $count;
		$count  = 100;
	} else {
		$fcount = -1;
	}

	unset($groupinfo);
	unset($response);

	$offset = isset($_GET['offset']) ? $_GET['offset'] : 0;

	do {
		$fcount -= $count;
		do {
			$response = $vk->api('wall.get',
            array(
				'domain' => $domain,
				'count' => $count,
				'owner_id' => $owner_id,
				'offset' => $offset,
				'extended' => '1',
                'v' => '5.81'
			));

			if (!isset($response['response']['items'][0]))
            {
                header('HTTP/1.1 403 Forbidden');
                var_dump($response);
                exit(0);
            }
		} while (!isset($response['response']['items'][1]));

        foreach($response['response']['profiles'] as $profile)
            $profiles[$profile['id']] = $profile['first_name'] . ' ' . $profile['last_name'];

        foreach($response['response']['groups'] as $profile)
            $profiles["-" . $profile['id']] = $profile['name'];

		for ($i = 1; $i <= count($response['response']['items']) - 1; $i++) {
			$post = $response['response']['items'][$i];
			if (isset($post['copy_history']) && $nocopy)
				continue;

			if (isset($post['copy_history'])) {

				if (strcmp($post['text'], "") != 0)
					$rawtext .= '<p style="white-space:pre-line;">' . $post['text'] . '</p>';

				$rawtext .= "<a href=\"https://vk.com/wall{$post['copy_history'][0]['owner_id']}_{$post['copy_history'][0]['id']}\">{$profiles[$post['copy_history'][0]['owner_id']]}:</a>";

				if (strcmp($post['copy_history'][0]['text'], "") != 0)
					$rawtext .= '<blockquote style="white-space:pre-line;">' . $post['copy_history'][0]['text'] . '</blockquote>';

        $post['attachments'] = (object)array_merge((array)$post['attachments'],
        											                     (array)$post['copy_history'][0]['attachments']); //TODO: replace with proper recursive parsing
			} else if (strcmp($post['text'], "") != 0)
				$rawtext = '<p style="white-space:pre-line;">' . $post['text'] . '</p>';
			else
				$rawtext = "";

      $checksum = sprintf('/%08x', crc32($rawtext));

			$rawtext = preg_replace('`<br>`', '<br/>', $rawtext);
			$rawtext = preg_replace('`<hr>`', '<hr/>', $rawtext);

			$item    = new Item();

			$description = preg_replace('`^(<br/>|<hr/>)*`', '', $rawtext);
			$description = preg_replace('`((?<!://)(vk.cc/[0-9A-Za-z]+))`', '<a href="https://$2">$2</a>', $description);
			$description = preg_replace('`((?<!")(https?|ftp)://([a-zA-Z._-]+?)/[a-zA-Z0-9?&_%.=/;\-@]*)`', '<a href="$1">$1</a>', $description);
			$description = preg_replace('`(?<![?/!.])#([^[:blank:],.<():\n\r]+)`', '<b>#$1</b> ', $description);
			$description = preg_replace('`\[([0-9a-z_?=#:/-]+)\|([^]]+)\]`', '<a href="https://vk.com/$1">$2</a>', $description);

			$plist = 0;

			if (isset($post['attachments']))
			foreach ($post['attachments'] as $attachment)
			{
				switch ($attachment['type'])
				{
					case 'photo':
					{
						$base_size = $attachment['photo']['sizes'][0];

						$key = ($base_size['width'] > $base_size['height']) ? 'width' : 'height';
						$img_size_index = 0;
						$img_size_o = $base_size[$key];


						foreach ($attachment['photo']['sizes'] as $photo_key=>$photo_size) {
							if ($photo_size[$key] > $img_size_o) {
								$img_size_index = $photo_key;
								$img_size_o = $photo_size[$key];
							}
						}

						$src = $attachment['photo']['sizes'][$img_size_index]['url'];

						$description .= "<img src='{$src}'/>";

						break;
					}

					case 'audio':
					{
						if ($plist == 0)
							if (isset($post['copy_history']))
								$description .= "<br/><a href=\"http://coropata:9000/m3u.php?post={$post['copy_history'][0]['id']}&amp;owner_id={$post['copy_history'][0]['from_id']}&amp;all=1\">Playlist</a> contents:";
							else
								$description .= "<br/><a href=\"http://coropata:9000/m3u.php?post={$post['id']}&amp;owner_id={$post['from_id']}&amp;all=1\">Playlist</a> contents:";

						$plist = 1;

						$description .= "<br/>{$attachment['audio']['artist']} &ndash; {$attachment['audio']['title']}";
						break;
					}

					case 'doc':
					{
						if ($attachment['doc']['ext'] == "gif" or $attachment['doc']['ext'] == "png" or $attachment['doc']['ext'] == "jpg")
							$description .= "<br/><img src=\"{$attachment['doc']['url']}\"/>";
						else
							$description .= "<br/><a href='{$attachment['doc']['url']}'>{$attachment['doc']['title']}</a>";
						$item->enclosure($attachment['doc']['url'], 0, 'application/octet-stream');
						break;
					}

					case 'link':
					{
                        $description .= "<br/><a href=\"{$attachment['link']['url']}\">{$attachment['link']['title']}</a>";
						break;
					}

					case 'video': {
                        if (isset($attachment['video']['photo_800']))
                            $img = $attachment['video']['photo_800'];
                        else
                            $img = $attachment['video']['photo_320'];

						$description .= "<p><a href='http://vk.com/video{$attachment['video']['owner_id']}_{$attachment['video']['id']}'><img src=\"{$img}\" /</a><br/>{$attachment['video']['title']}</p>";
						if ($attachment['video']['description'] != "")
							$description .= "<blockquote style=\"white-space:pre-line;\">{$attachment['video']['description']}</blockquote>";
						break;
					}

					case 'page': {
						do {
							$pages = $vk->api('pages.get',
                            array(
															'need_html' => '1',
															'gid' => $attachment['page']['gid'],
															'pid' => $attachment['page']['pid'],
															'v' => '5.81'
														));

							if (!isset($pages['response']['html']))
								sleep(5);
						} while (!isset($pages['response']['html']));

						$tmphtml = $pages['response']['html'];

						$tmphtml = preg_replace('`<br>`', '<br/>', $tmphtml);
						$tmphtml = preg_replace('`<hr>`', '<hr/>', $tmphtml);

						$description .= '<hr/><h3>' . $pages['response']['title'] . '</h3><br/>' . $tmphtml;

						unset($pages);
						unset($tmphtml);
						break;
					}

					case 'audio_playlist': {
							$description .= "<br/><a href=\"http://coropata:9000/m3u.php?album_id={$attachment['audio_playlist']['id']}&amp;owner_id={$attachment['audio_playlist']['owner_id']}\">{$attachment['audio_playlist']['title']}:</a>";

							foreach($attachment['audio_playlist']['audios'] as $audio)
									$description .= "<br/>{$audio['artist']} &ndash; {$audio['title']}";
					}
				}
      }
			unset($attachment);

			if ($plist == 1)
				$item->enclosure("http://coropata:9000/m3u.php?post={$post['id']}&owner_id={$post['from_id']}&all=1", 0, 'application/x-mpegurl');

			if (!isset($title))
			{
				$titleprep = preg_replace('`(*UTF) *\#[\p{L}0-9_@]+(\n|\r\n| )*|</?blockquote.*?>|</?p.*?>|<a href=.+?>.+?</a>|</?img>|<img.+?/>`', ' ', $rawtext);
				$titleprep = preg_replace('`<br/?>|<hr/?>`', "\n", $titleprep);
				$titleprep = preg_replace('`\[([0-9a-z]+)\|([^]]+)\]`', '$2', $titleprep);
				$titleprep = preg_replace('`^ +`mu', '', $titleprep);
				$titleprep = preg_replace('` +`', ' ', $titleprep);
				$titleprep = str_replace(" \n", "\n", $titleprep);

				preg_match_all('`(Alnum|Album|Title|Альбом|Название) ?: ?.+`', $titleprep, $matches);
				if (isset($matches[0][0]) && !strstr($matches[0][0], 'vk.'))
					$title = $matches[0][0];
				else
				{
					preg_match_all('/^([^.!?。…]{6,120})[.!?。…]|.{1,120}$|^.{1,120}\b/mu', $titleprep, $matches);
					if (isset($matches[0][0]) && strlen($matches[0][0]) >= 3)
						$title = $matches[0][0];
					elseif (isset($matches[0][1]))
						$title = $matches[0][0] . " " . $matches[0][1];
					else
						$title = date('d.m.Y H:i:s', $post['date']);
				}
			}

			unset($matches);

            if ($watch)
                $guid = "{$post['from_id']}_{$post['id']}{$checksum}";
            else
                $guid = "{$post['from_id']}_{$post['id']}";

			$item
				->title($title)
				->description($description)
				->url("https://vk.com/wall-{$response['response']['groups'][0]['id']}_{$post['id']}")
				->pubDate($post['date'])
				->guid($guid, false)
				->appendTo($channel);
			if ($comments)
				$item -> comments("http://127.0.0.1:9000/comments.php?owner_id=-{$response['response']['groups'][0]['id']}&post_id={$post['id']}");

			unset($description);

            preg_match_all('`#\K([^[:blank:],.<()@:\n\r]+)`', $rawtext, $matches);

			foreach ($matches[0] as $match)
				$item->category($match);

			unset($match);
			unset($titleprep);
			unset($title);
			unset($rawtext);
		}

		$offset += $count;
		unset($response);

	} while ($fcount >= 0);

	echo $feed;

}
catch (VK\VKException $error) {
	echo $error->getMessage();
}
