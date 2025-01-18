# hiccup-php
An HTML rendering and templating engine, based on PHP code (strings, arrays, classes and inheritance).
It is a (sophisticated) port of [Hiccup for Clojure](https://github.com/weavejester/hiccup).

##  Installation
Via composer:
```
composer require nixn/hiccup
```

## Usage

The library consists of two main parts: the `Hiccup` class and the `Template` interface with its counterpart,
the abstract class `Template_Base`.

### Hiccup

This is the class which renders HTML from a number of data structures (including `Template`).
It has a single main method: `static function html(mixed ...$elements): string` (and several helper methods),
and it interprets the different elements passed to it as HTML structure(s) (recursively), joins them together
and returns the resulting HTML string.

Possible elements (PHP types):

#### null
A null value renders nothing, it effectively is ignored (equivalent to an empty string as result).

#### string
A string is taken as the leaf value of an HTML structure, best described as textual tag content.
It is automatically escaped, unless you use `Hiccup::raw()` to protect it; then it is passed as-is to the output string.

#### (another) scalar or `\Stringable` value
That includes values like booleans, integers, floats, etc., all of which have a natural string representation,
or objects of classes implementing `\Stringable` but not `Template`. They are converted to an escaped string too,
but without the possibility to be protected by `Hiccup::raw()` directly. If you want that, you have to convert it
yourself, e.g. by calling `Hiccup::raw("$my_stringable_object")`.

#### `Template`s
These objects are handled differently. The method (Template::)`hiccup(): mixed` is called on them, which can return any
Hiccup element, which is then rendered recursively. Due to the nature of PHP, which can return only one value from a function,
often it will be the result of the call `Hiccup::each()`, which is an iterable (see below), but it can also be any other
element from this list.

#### iterable (except array)
An iterable is taken as a list of sibling elements. They are rendered behind one another, calling `html()` recursively
on them. An array is explicitely not handled as an iterable, because it is ... (see below).

#### array
Arrays are the heart of the Hiccup "language", they denote HTML tags and their children and make up the structure of HTML.
They start with a string, which is the tag name; optionally followed by an associative array, which is rendered as the
attributes of this tag; and finally followed by zero or more child elements, which are rendered as siblings
(just like in `Hiccup::each()` or with an iterable) below this tag in the result.

The tag name can have modifications (suffixes), which are interpreted as attributes, to enable easy and fast declaration
of the tag attributes. In detail that are:

* `#<id>` - renders to the attribute `id="<id>"`
* `.<class>` - renders to the attribute `class="<class>"`, repeatable (all classes are set)
* `[<name>]<value>` - renders to the attribute `<name>="<value>"` (all attributes but `class` allowed)<br>
  These can only be last, after any id or class modifications.

For better readability the values (`<id>`, `<class>`, `<name>`, `<value>` and the tag name itself) can have whitespace
after them, but not before (except `<name>`) and also not in between.

Example:
```php
['form #the-form .form.pretty [method]post [action]/my-script.php', ['title' => 'Please fill out!'],
  ['label', /*...*/],
  ['input', /*...*/],
]
```
(The title could not be written into the tag name string, because the value has whitespace!)

In the attributes array the elements have some different value possibilities:

* boolean value
  * `true` - renders to just `<name>`<br>
    (e.g. `['option', ['selected' => true]]` => `<option selected></option>`)
  * `false` - not rendered (just omitted)
* for the `class` attribute there are other possibilities:
  * sequential strings array - all non-falsy values are rendered as class names<br>
    (`['class' => ['a', 'b', $c ? 'c' : null, 'd']]` with falsy `$c` => `class="a b d"`)
  * associative array - all keys with a non-falsy value are rendered as class names<br>
    (`['class' => ['a' => true, 'b' => false, 'c' => 'something']]` => `class="a c"`)

Hiccup has some helper functions to ease templating:
* `static function raw(?string $html): self`<br>
  Wraps a string, and outputs it as-is (not HTML-escaped) on rendering.
* `static function foreach(?iterable $items, callable $action): iterable`<br>
  Takes `$items` (which may be null), calls `$action` on every item and renders the results as siblings.
* `static function each(mixed ...$elements): iterable`<br>
  Effectively just returns `$elements` as an iterable, most useful as a return value from a `Template` class.
* `static function join(mixed $separator, mixed ...$elements): iterable`<br>
  Like the usual join(), but this `$separator` may be any Hiccup element.
* `static function lines(mixed ... $lines): iterable`<br>
  A convenience method. Calls `Hiccup::join(Hiccup::raw("\n"), ...$lines)`.

### `Template`

The `Template` interface is the connection between the rendering and the class based templating. As described above
a class which implements `Template` is a valid Hiccup element and will be called for its Hiccup code for rendering.
Through inheritance and composition (usual class operations) it is possible to create the most complex templates,
still as easy managable as a class structure, even with more power than with other templating engines, which always try
to resemble the class structure in some way. Here it can be used directly (and is demonstrated below).

#### `Template_Base`
This abstract class is a helper class to make the template classes a breeze. It has two main functions:

##### Dynamic Methods
It uses the PHP magic method `__call(string $name, array $args): mixed`. That means every template class (which derives
from `Template_Base`) can just use a call like `$this->content()` and if a subclass overrides it, it can deliver that
content. If not, `null` is returned and rendered into nothing.

##### Namespacing
Every derived template class can be assigned a parent and optional affixes, which is used in the dynamic call:
If a parent is set, the call is redirected to it with the affixes appended, so the parent has control over its
subtemplates and their calls.

Both is best explained by an example:

```php
class Log extends Template_Base
{
    public function hiccup(): mixed
    {
        return Hiccup::each(
            (new OutputLine('debug message'))->set_parent($this, 'debug_'),
            (new OutputLine('usual message'))->set_parent($this, 'usual_'),
            (new OutputLine('error message'))->set_parent($this, 'error_'),
        );
    }
    
    protected function debug_p_class(): string
    {
        return 'gray';
    }
    
    protected function error_p_class(): string
    {
        return 'red';
    }
}

class OutputLine extends Template_Base
{
    public function __construct(private string $message) {}

    public function hiccup(): mixed
    {
        return ['p', ['class' => $this->p_class()], $this->message];
    }
}
```

### `HTML5`
`nixn\hiccup\HTML5` is a pre-defined template class which can be used as a base for HTML5 pages. It contains the basic
boilerplate with default but overridable values and can be the base of every generated template in a web page,
which has to display HTML content. You may start with just overriding `body(): mixed` and put your content there,
optionally `title(): string` too, and you are good to go for a basic page.

```php
class IndexPage extends HTML5
{
    protected function title(): string
    {
        return 'Hello, world!';
    }

    protected function body(): mixed
    {
        return ['h1', 'Welcome, everyone! ', ['small', ':-)']];
    }
}

echo Hiccup::html(new IndexPage());
```

### Bootstrap example

With a really tiny bit more effort we can build the base page for a [Bootstrap](https://getbootstrap.com) layout.

```php
class Page extends HTML5
{
    public function __construct(
        private bool $include_popper,
    )
    {}

    protected function head_end(): mixed
    {
        return ['link [rel]stylesheet [crossorigin]anonymous', [
            'href' => "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css",
            'integrity' => "sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH",
        ]];
    }
    
    protected function body(): mixed
    {
        return Hiccup::each(
            $this->content(),
            $this->include_popper ? ['script [crossorigin]anonymous', [
                'src' => 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js',
                'integrity' => "sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r",
            ]] : null,
            ['script [crossorigin]anonymous', [
                'src' => "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.min.js",
                'integrity' => "sha384-0pUGZvbkm6XF6gxjEnlmuGrJXVbNuzT9qBBavbLwCsOGabYfZo0T0to5eqruptLy",
            ]],
            $this->body_end(),
        );
    }
}
```

And use it:
```php
class IndexPage extends Page
{
    public function __construct()
    {
        parent::__construct(false);
    }
    
    protected function content(): mixed
    {
        return ['h1.bg-secondary', 'Hello, world!'];
    }
}

echo Hiccup::html(new IndexPage());
```

## License
Copyright © 2025 nix <https://keybase.io/nixn>

Distributed under the MIT license, available in the file [LICENSE](LICENSE).

## Donations
If you like hiccup-php, please consider dropping some bitcoins to `1nixn9rd4ns8h5mQX3NmUtxwffNZsbDTP`.
