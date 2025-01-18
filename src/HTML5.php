<?php
namespace nixn\hiccup;

use nixn\hiccup\Hiccup as H;
use nixn\php\Util;

class HTML5 extends Template_Base
{
	public function hiccup(): iterable
	{
		return H::lines(
			H::raw('<!DOCTYPE html>'),
			['html', ['lang' => $this->html_lang() ?? 'en'], H::lines('',
				['head', H::lines('',
					$this->head_start(),
					Util::map($this->meta_charset(), fn($charset) => ['meta', ['charset' => $charset]]),
					Util::map($this->meta_viewport(), fn($content) => ['meta [name]viewport', ['content' => $content]]),
					Util::map($this->title(), fn(mixed $title) => ['title', $title], empty: true),
					$this->head_end(),
					'',
				)],
				['body', $this->body_attrs() ?? [], $this->body()],
				'',
			)],
		);
	}

	protected function meta_charset(): ?string
	{
		return 'UTF-8';
	}

	protected function meta_viewport(): ?string
	{
		return 'width=device-width; initial-scale=1';
	}
}
