<?php
namespace nixn\hiccup;

abstract class Template_Base implements Template
{
	private ?Template $parent = null;
	private string $prefix = '';
	private string $suffix = '';

	public function set_parent(?Template $parent, string $prefix = '', string $suffix = ''): static
	{
		$this->parent = $parent;
		$this->prefix = $prefix;
		$this->suffix = $suffix;
		return $this;
	}

	public function __call(string $name, array $args): mixed
	{
		return $this->parent?->{$this->prefix . $name . $this->suffix}(...$args);
	}
}
