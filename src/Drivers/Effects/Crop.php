<?php

namespace Marcoboom\MediaManager\Drivers\Effects;

use Marcoboom\MediaManager\Imagestyle;
use Marcoboom\MediaManager\Contracts\ImagestyleAction;

class Crop implements ImagestyleAction
{
	protected $config;
	protected $style;

	public function __construct(Imagestyle $style, array $config=[])
	{
		$this->style = $style;
		$this->config = $config;
	}

	public function handle()
	{
		$this->style->img->crop($this->config['width'], $this->config['height']);
	}

}