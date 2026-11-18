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
'style' => 'vertical-align:top' ]` and returns markup -- an icon library's own
render function usually fits as it stands. Both kinds carry that class, so
one rule sizes either:

```css
.sugarcommerce-breadcrumbs__icon { width: 16px; height: 16px; font-size: 16px; }
```

## Configuration

`separator` (`&#8250;`, raw HTML), `aria_label` (`Breadcrumb` -- pass it
translated), `nav_class`, `list_class`, `item_class`, `separator_class`,
`active_class` and `icons`, to `create()` or as fluent setters of the same
names. Extra attributes on an item are escaped, and names that are not
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

New: `Breadcrumbs::from()`, `icons()`, `ICON_CLASS`.

## Requirements

* PHP 8.3 or later
* WordPress 7.1 or later

## License

GPL-2.0-or-later
