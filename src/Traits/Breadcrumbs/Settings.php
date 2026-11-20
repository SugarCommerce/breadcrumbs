<?php
/**
 * A breadcrumb trail: a labelled nav, the current page marked, and Schema.org microdata.: Settings
 *
 * @package   SugarCommerce\Breadcrumbs
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Breadcrumbs\Traits\Breadcrumbs;

/**
 * The trail's markup settings: separator, label, classes, icons and stylesheet.
 *
 * Part of Breadcrumbs: only the class uses it.
 */
trait Settings {

	/**
	 * Set the separator between items.
	 *
	 * @param string $separator The separator HTML string. Emitted raw so markup and
	 *                          entities work, so never pass untrusted input here.
	 *
	 * @return static Self for chaining.
	 */
	public function separator( string $separator ): static {
		$this->separator = $separator;

		return $this;
	}

	/**
	 * Set the ARIA label for the nav element.
	 *
	 * @param string $label The ARIA label.
	 *
	 * @return static Self for chaining.
	 */
	public function aria_label( string $label ): static {
		$this->aria_label = $label;

		return $this;
	}

	/**
	 * Set the CSS class for the nav wrapper.
	 *
	 * @param string $name The CSS class.
	 *
	 * @return static Self for chaining.
	 */
	public function nav_class( string $name ): static {
		$this->nav_class = $name;

		return $this;
	}

	/**
	 * Set the CSS class for the ordered list.
	 *
	 * @param string $name The CSS class.
	 *
	 * @return static Self for chaining.
	 */
	public function list_class( string $name ): static {
		$this->list_class = $name;

		return $this;
	}

	/**
	 * Set the CSS class for list items.
	 *
	 * @param string $name The CSS class.
	 *
	 * @return static Self for chaining.
	 */
	public function item_class( string $name ): static {
		$this->item_class = $name;

		return $this;
	}

	/**
	 * Set the CSS class for separator elements.
	 *
	 * @param string $name The CSS class.
	 *
	 * @return static Self for chaining.
	 */
	public function separator_class( string $name ): static {
		$this->separator_class = $name;

		return $this;
	}

	/**
	 * Set what draws a `set:name` icon.
	 *
	 * Called with the name and `[ 'class' => ICON_CLASS, 'style' => 'vertical-align:top' ]`,
	 * and returns markup -- the signature of an icon library's own render
	 * function, so one can be passed as it stands. Without one, a `set:name`
	 * icon draws nothing. Its output is printed as given.
	 *
	 * @param callable|null $icons fn( string $name, array $args ): string.
	 *
	 * @return static Self for chaining.
	 */
	public function icons( ?callable $icons ): static {
		$this->icons = $icons;

		return $this;
	}

	/**
	 * Whether rendering loads the package's stylesheet.
	 *
	 * On by default. Off, the trail is bare markup -- an ordered list -- for
	 * a plugin or theme that styles it itself: it does not load the
	 * stylesheet, and it does not carry STYLED_CLASS, so it is left alone on
	 * a page where another trail did load it. The stylesheet styles the
	 * default class names of the list, items and separator; parts given
	 * classes of their own are their caller's to style.
	 *
	 * @param bool $load Whether to load it.
	 *
	 * @return static Self for chaining.
	 */
	public function stylesheet( bool $load ): static {
		$this->stylesheet = $load;

		return $this;
	}

	/**
	 * Set the CSS class for the active item.
	 *
	 * @param string $name The CSS class.
	 *
	 * @return static Self for chaining.
	 */
	public function active_class( string $name ): static {
		$this->active_class = $name;

		return $this;
	}
}
