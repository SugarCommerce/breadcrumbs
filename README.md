# Breadcrumbs

Build a breadcrumb trail that search engines can read and screen readers can
navigate.

## What it does

Breadcrumbs look like a list of links until you need them done properly: a
`<nav>` with a label, the current page marked with `aria-current` rather than
just styled differently, and Schema.org microdata so search results show the
trail instead of a bare URL.

This builds that markup from the items you add, so the accessibility and the
structured data come for free rather than being remembered.

## Features

* Build a trail by adding items, with the current page marked correctly
* Get Schema.org BreadcrumbList microdata without writing any of it
* Mark the current item with `aria-current`, not just a CSS class
* Add an icon to any item: a Dashicon, any icon set you can draw, or your own markup
* Look right in wp-admin and on a theme with the stylesheet it loads itself, or turn it off and style it yourself
* Cut a long crumb short with an ellipsis, its whole name kept in a title
* Set your own classes on the nav, the list, the items and the separator
* Render from an array, when the trail is already assembled elsewhere

## Installation

```bash
composer require sugarcommerce/breadcrumbs
```

## Quick start

```php
use SugarCommerce\Breadcrumbs\Breadcrumbs;

Breadcrumbs::create()
	->add( __( 'Shop', 'my-plugin' ), admin_url( 'admin.php?page=myshop' ), 'admin-home' )
	->add( __( 'Orders', 'my-plugin' ), admin_url( 'admin.php?page=myshop-orders' ) )
	->add_current( sprintf( __( 'Order #%d', 'my-plugin' ), $order->id ) )
	->display();
```

`add_current()` rather than a third `add()` -- the last crumb is the page you
are on, so it is not a link and is announced as the current one. A crumb in
the middle with no URL is plain text.

When the trail already exists as data:

```php
echo Breadcrumbs::from( [
	[ 'label' => 'Home', 'url' => '/' ],
	[ 'label' => 'Blog', 'url' => '/blog/' ],
	[ 'label' => 'This post' ],
] );
```

`render()` returns the markup, `display()` prints it, and the trail is also
a string, a JSON array of its items (`to_array()`), and countable
(`count()`, `first()`, `last()`, `items()`, `is_empty()`).

## Icons

The third argument to `add()`, `add_home()` and `add_current()`, and the
`icon` key of an item array, takes any of:

* a Dashicons name, bare or as the class -- `admin-home` or
  `dashicons-admin-home` -- drawn as the font's span
* `set:name` -- `heroicons-outline:home` -- drawn by the `icons` callable you
  give, and nothing without one: this library carries no icon set
* anything else, printed as given (raw HTML, so never untrusted input)

```php
Breadcrumbs::create( [ 'icons' => [ Icons::class, 'render' ] ] ); // or ->icons( $callable )
```

The callable gets the name and `[ 'class' => 'sugarcommerce-breadcrumbs__icon',
'style' => 'vertical-align:top' ]` and returns decorative markup (`aria-hidden`)
-- an icon library's own render function usually fits as it stands. Your own
markup is printed inside `<span class="sugarcommerce-breadcrumbs__icon"
aria-hidden="true">`, and the Dashicon span carries the class too, so the
stylesheet gives every kind the same box, centred on the label.

## The markup

```html
<nav class="sugarcommerce-breadcrumbs sugarcommerce-breadcrumbs--styled" aria-label="Breadcrumb">
  <ol class="sugarcommerce-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">
    <li class="sugarcommerce-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
      <a class="sugarcommerce-breadcrumbs__crumb" href="/" itemprop="item"><span class="dashicons dashicons-admin-home sugarcommerce-breadcrumbs__icon" aria-hidden="true"></span><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Home">Home</span></a>
      <meta itemprop="position" content="1">
    </li>
    <li class="sugarcommerce-breadcrumbs__item sugarcommerce-breadcrumbs__item--active" … aria-current="page">
      <span class="sugarcommerce-breadcrumbs__separator" aria-hidden="true">›</span>
      <span class="sugarcommerce-breadcrumbs__crumb"><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Order #42">Order #42</span></span>
      <meta itemprop="position" content="2">
    </li>
  </ol>
</nav>
```

(Printed without the whitespace shown here.) The list holds crumbs only, so a
screen reader counts the crumbs; each separator is inside the crumb it leads
to and hidden. Icon and label are separate elements with no space between
them, so a link's underline never runs under the icon. The label carries its
whole text as a `title`, because the stylesheet cuts a long one short.

## Styling

The first trail that renders loads the package's stylesheet (through
`SugarCommerce\Plugin\Assets`, so it is found wherever the package is
bundled). It lays the trail out in a line and looks native in wp-admin and on
a theme alike:

* a wrapping flex row, no list markers or padding, even gaps
* links not underlined until hovered or focused, and then only the label
* a focus ring in core's `--wp-admin-theme-color`, the text colour outside wp-admin
* icons of every kind in a 1.15em box, centred on the label
* separators centred and muted; the current crumb in the text colour, medium weight
* a long crumb cut with an ellipsis, its title keeping the whole name
* logical properties throughout, so it reads right to left (the default `›`
  is a Unicode mirrored character and turns round by itself)
* colours from `currentColor` and the context's own link colour, so a dark
  theme needs nothing of its own

Rendering in an admin page happens after the head is printed, so the
stylesheet arrives in the footer. Call `enqueue()` from
`admin_enqueue_scripts` (or `wp_enqueue_scripts`) to have it in the head:

```php
add_action( 'admin_enqueue_scripts', static fn() => Breadcrumbs::create()->enqueue() );
```

Tune it without overriding a rule:

```css
.sugarcommerce-breadcrumbs--styled {
	--sugarcommerce-breadcrumbs-gap: .5em;          /* separator to crumb */
	--sugarcommerce-breadcrumbs-icon-size: 1.15em;  /* an icon's box */
	--sugarcommerce-breadcrumbs-crumb-width: 22em;  /* where a long crumb is cut */
}
```

To style a trail yourself, turn the stylesheet off:

```php
Breadcrumbs::create( [ 'stylesheet' => false ] ); // or ->stylesheet( false )
```

It is then not loaded by that trail, and the trail drops the
`sugarcommerce-breadcrumbs--styled` class every rule hangs from -- so it stays
bare even on a page where another trail loaded the stylesheet. A nav class of
your own keeps the look; a list, item or separator class of your own is yours
to style. The handle is `Breadcrumbs::handle()`, derived from the namespace so
each Strauss-prefixed copy registers its own.

## Configuration

`separator` (`&#8250;`, raw HTML), `aria_label` (`Breadcrumb` -- pass it
translated), `nav_class`, `list_class`, `item_class`, `separator_class`,
`active_class`, `icons` and `stylesheet` (`true`), to `create()` or as fluent
setters of the same names. Extra attributes on an item are escaped, and names that are not
attribute names, or are event handlers, are dropped.

## Moving from arraypress/wp-*

From `arraypress/wp-breadcrumbs` (`ArrayPress\Breadcrumbs`). The global
functions are gone -- a global function is the one thing Strauss cannot
prefix -- and it no longer looks for `wp-field-kit`.

| Old | New |
|---|---|
| `ArrayPress\Breadcrumbs\Breadcrumbs` | `SugarCommerce\Breadcrumbs\Breadcrumbs` |
| `ArrayPress\Breadcrumbs\Item` | `SugarCommerce\Breadcrumbs\Item` (now `final`) |
| `create_breadcrumbs( $config )` | `Breadcrumbs::create( $config )` |
| `render_breadcrumbs( $items, $config )` | `Breadcrumbs::from( $items, $config )->render()` |
| `display_breadcrumbs( $items, $config )` | `Breadcrumbs::from( $items, $config )->display()` |
| `set:name` icons drawn by wp-field-kit when installed | drawn by the `icons` callable: pass the kit's `Icons::render` to keep them |
| classes `arraypress-breadcrumbs`, `arraypress-breadcrumbs__*`, `arraypress-icon` | `sugarcommerce-breadcrumbs`, `sugarcommerce-breadcrumbs__*`, `sugarcommerce-breadcrumbs__icon` |
| a middle crumb with no URL was a second `aria-current="page"` | plain text; only the last crumb is current |
| `render_icon()` protected static | protected instance method |
| separator an `aria-hidden` `<li>` between items | an `aria-hidden` `<span>` inside the crumb it leads to |
| icon and label separated by a space | separate elements; the link is `__crumb`, the label `__label` with a `title` |
| no stylesheet | the package's stylesheet, loaded by the first trail; `'stylesheet' => false` to style it yourself |

New: `Breadcrumbs::from()`, `icons()`, `stylesheet()`, `enqueue()`, `handle()`,
`ICON_CLASS`, `CRUMB_CLASS`, `LABEL_CLASS`, `STYLED_CLASS`.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
