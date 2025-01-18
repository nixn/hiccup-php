<?php
namespace nixn\hiccup;

use nixn\php\{Arr, Util};

final class Hiccup
{
	private const VOID_TAGS = ['area', 'base', 'br', 'col', 'command', 'embed', 'frame', 'hr', 'img', 'input', 'keygen',
		'link', 'menuitem', 'meta', 'param', 'source', 'track', 'wbr'];

	private function __construct(private readonly string $html) {}

	public static function raw(?string $html): self
	{
		return new self($html ?? '');
	}

	private static function handle_tag(string $tag_spec, mixed ...$children): string
	{
		if (!preg_match('/^\s*(\S+?)\s*(?=[#.[]|$)/', $tag_spec, $match))
			throw new \InvalidArgumentException("invalig tag name ($tag_spec)");
		$tag = $match[1];
		$offset = strlen($match[0]);
		$matches = [$match[0]];
		$attrs = ['id' => null, 'class' => []];
		for ($len = strlen($tag_spec); $offset < $len;)
		{
			if (preg_match('/^#(\S*?)\s*(?=[#.[]|$)/', substr($tag_spec, $offset), $match))
			{
				if ($match[1] !== '')
					$attrs['id'] = $match[1];
				$offset += strlen($match[0]);
				$matches[] = $match[0];
			}
			elseif (preg_match('/^\.(\S*?)\s*(?=[#.[]|$)/', substr($tag_spec, $offset), $match))
			{
				if ($match[1] !== '')
					$attrs['class'][strtolower($match[1])] = true;
				$offset += strlen($match[0]);
				$matches[] = $match[0];
			}
			elseif (preg_match('/^\[\s*(\S+?)\s*](.*?)\s*(?=\[|$)/', substr($tag_spec, $offset), $match))
			{
				$name = strtolower($match[1]);
				if ($name === 'class') throw new \InvalidArgumentException("[class]... syntax not allowed");
				$attrs[$name] = $match[2];
				$offset += strlen($match[0]);
				$matches[] = $match[0];
			}
			else
				throw new \InvalidArgumentException("invalid tag spec (".join('|', $matches).'|HERE>'.substr($tag_spec, $offset).")");
		}
		if (!empty($children) && is_array($children[0]) && (empty($children[0]) || is_string(array_key_first($children[0]))))
		{
			$attrs2 = array_shift($children);
			if (array_key_exists('class', $attrs2))
			{
				$attrs2['class'] = Util::map($attrs2['class'], fn($class) => match(true) {
					is_string($class) => preg_split('/ +/', $class, flags: PREG_SPLIT_NO_EMPTY),
					!is_array($class) => throw new \RuntimeException(sprintf("invalid type of 'class' attribute: %s", gettype($class))),
					default => $class
				}) ?? [];
				$attrs2['class'] = Arr::reduce($attrs2['class'], function($class, $v, $k) {
					if (is_string($k) && ($k = trim($k)) !== '')
						$class[$k] = $v;
					elseif (is_int($k) && is_string($v) && ($v = trim($v)) !== '')
						$class[$v] = true;
					return $class;
				}, []);
			}
			$attrs = array_replace_recursive($attrs, $attrs2);
		}
		$html = "<$tag";
		$add_attr = function(string $name, string|bool|null $value = null, bool $unset = false) use (&$attrs, &$html) {
			$value ??= $attrs[$name];
			match($value) {
				true => $html .= sprintf(' %s', $name),
				null, false => null,
				default => $html .= sprintf(' %s="%s"', $name, htmlentities($value))
			};
			if ($unset)
				unset($attrs[$name]);
		};
		$add_attr('id', unset: true);
		$class = [];
		foreach ($attrs['class'] as $name => $use)
			if ($use)
				$class[] = $name;
		$add_attr('class', empty($class) ? false : join(' ', $class), unset: true);
		foreach ($attrs as $name => $value)
			$add_attr($name, $value);
		$html .= ">";
		if (!in_array($tag, self::VOID_TAGS, true))
		{
			if (!empty($children))
				$html .= self::html(...$children);
			$html .= "</$tag>";
		}
		elseif (!empty($children))
			throw new \InvalidArgumentException("children found for self-closing (void) tag ($tag)");
		return $html;
	}

	public static function html(mixed ...$elements): string
	{
		$html = "";
		foreach ($elements as $key => $elem)
		{
			if (is_array($elem))
				$html .= self::handle_tag(...$elem);
			elseif (is_iterable($elem))
				foreach ($elem as $item)
					$html .= self::html($item);
			elseif ($elem instanceof self)
				$html .= $elem->html;
			elseif ($elem instanceof Template)
				$html .= self::html($elem->hiccup());
			elseif (is_scalar($elem) || $elem instanceof \Stringable)
				$html .= htmlspecialchars("$elem");
			elseif (!is_null($elem))
				throw new \InvalidArgumentException(sprintf("Element %s is not string(able): %s[%s]", $key, gettype($elem), is_object($elem) ? get_class($elem) : '-'));
		}
		return $html;
	}

	/**
	 * @template T
	 * @template R
	 * @param iterable|null $items (iterable<T>|null)
	 * @param callable(T, string|int): R $action
	 * @return iterable<R>
	 */
	public static function foreach(?iterable $items, callable $action): iterable
	{
		if ($items == null)
			return;
		foreach ($items as $key => $item)
			yield $action($item, $key);
	}

	public static function each(mixed ...$elements): iterable
	{
		foreach ($elements as $item)
			yield $item;
	}

	public static function join(mixed $separator, mixed ...$elements): iterable
	{
		$first = true;
		foreach ($elements as $elem)
		{
			if ($elem === null)
				continue;
			if ($first) $first = false;
			else yield $separator;
			yield $elem;
		}
	}

	public static function lines(mixed ... $lines): iterable
	{
		return self::join(self::raw("\n"), ...$lines);
	}
}
