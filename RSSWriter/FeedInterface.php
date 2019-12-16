<?php

namespace RSSWriter;

use \RSSWriter\ChannelInterface;

interface FeedInterface
{
	/**
	 * Add channel
	 * @param \Suin\RSSWriter\ChannelInterface $channel
	 * @return $thisJ
	 */
	public function addChannel(ChannelInterface $channel);

	/**
	 * Render XML
	 * @return string
	 */
	public function render();

	/**
	 * Render XML
	 * @return string
	 */
	public function __toString();
}
