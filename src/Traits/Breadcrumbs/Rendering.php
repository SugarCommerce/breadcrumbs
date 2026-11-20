<?php
/**
 * A breadcrumb trail: a labelled nav, the current page marked, and Schema.org microdata.: Rendering
 *
 * @package   SugarCommerce\Breadcrumbs
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Breadcrumbs\Traits\Breadcrumbs;

use SugarCommerce\Breadcrumbs\Item;
use SugarCommerce\Plugin\Assets;

/**
 * Drawing the trail: each crumb, its separator and icon, and their attributes.
 *
 * Part of Breadcrumbs: only the class uses it.
 */
trait Rendering {

	/**
	 * Render the breadcrumbs as an HTML string.
	 *
	 * Generates a semantic nav > ol > li structure with ARIA attributes
	 * and Schema.org BreadcrumbList microdata.
	 *
	 * @return string The rendered HTML.
	 */
	public function render(): string {
		if ( $this->is_empty() ) {
			return '';
		}

		if ( $this->stylesheet ) {
			$this->enqueue();
		}

		$items_html = [];
		$total      = count( $this->items );

		foreach ( $this->items as $index => $item ) {
			// Only the last crumb is the page you are on. One in the middle
			// without a URL is a step with no page of its own: plain text,
			// not a second "current page".
			$items_html[] = $this->render_item( $item, $index + 1, $index === $total - 1 );
		}

		return sprintf(
			'<nav class="%s" aria-label="%s"><ol class="%s" itemscope itemtype="https://schema.org/BreadcrumbList">%s</ol></nav>',
			esc_attr( trim( $this->nav_class . ( $this->stylesheet ? ' ' . self::STYLED_CLASS : '' ) ) ),
			esc_attr( $this->aria_label ),
			esc_attr( $this->list_class ),
			implode( '', $items_html )
		);
	}

	/**
	 * Load the stylesheet.
	 *
	 * render() does this itself unless the trail opted out. Call it early
	 * to have the stylesheet in the head rather than printed late.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! function_exists( 'wp_enqueue_style' ) ) {
			return;
		}

		$handle = self::handle();

		if ( ! wp_style_is( $handle, 'registered' ) && ! Assets::register_style( $handle, __FILE__, self::STYLESHEET ) ) {
			return;
		}

		wp_enqueue_style( $handle );
	}

	/**
	 * Render and echo the breadcrumbs.
	 *
	 * @return void
	 */
	public function display(): void {
		// render() escapes every label, URL, class and attribute it emits, and
		// BreadcrumbsTest asserts that by parsing the result. Escaping again
		// here would double-encode the markup it deliberately produces.
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $this->render();
	}

	/**
	 * Render a single breadcrumb item.
	 *
	 *     <li …><span separator aria-hidden>›</span><a crumb><icon/><span label>Orders</span></a><meta position></li>
	 *
	 * The separator sits inside the crumb it leads to, so the list holds
	 * only crumbs -- a screen reader counts three, not five -- and a trail
	 * that wraps starts its new line with "› Orders", not ends the old one
	 * with a dangling "›". The icon and the label are separate elements with
	 * no space between them: the gap is the stylesheet's, so a link's
	 * underline never runs under the icon. The label carries its full text
	 * as a title, because the stylesheet cuts a long one short.
	 *
	 * @param Item $item     The breadcrumb item.
	 * @param int  $position The position (1-based) for Schema.org.
	 * @param bool $active   Whether this is the active/current item.
	 *
	 * @return string The rendered list item HTML.
	 */
	protected function render_item( Item $item, int $position, bool $active ): string {
		$classes = $this->item_class;
		if ( $active ) {
			$classes .= ' ' . $this->active_class;
		}

		$icon_html = $item->has_icon() ? $this->render_icon( (string) $item->icon() ) : '';
		$label     = sprintf(
			'<span class="%1$s" itemprop="name" title="%2$s">%3$s</span>',
			self::LABEL_CLASS,
			esc_attr( $item->label() ),
			esc_html( $item->label() )
		);

		if ( $item->is_link() && ! $active ) {
			$inner = sprintf(
				'<a class="%s" href="%s" itemprop="item">%s%s</a>',
				self::CRUMB_CLASS,
				esc_url( $item->url() ),
				$icon_html,
				$label
			);
		} else {
			$inner = sprintf( '<span class="%s">%s%s</span>', self::CRUMB_CLASS, $icon_html, $label );
		}

		return sprintf(
			'<li class="%s" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"%s%s>%s%s<meta itemprop="position" content="%d"></li>',
			esc_attr( $classes ),
			$active ? ' aria-current="page"' : '',
			self::build_attributes( $item->attributes() ),
			$position > 1 ? $this->render_separator() : '',
			$inner,
			$position
		);
	}

	/**
	 * Render the separator element.
	 *
	 * @return string The separator HTML.
	 */
	protected function render_separator(): string {
		return sprintf(
			'<span class="%s" aria-hidden="true">%s</span>',
			esc_attr( $this->separator_class ),
			$this->separator
		);
	}

	/**
	 * Render an icon: a named icon, or raw HTML.
	 *
	 * A name -- `set:name` (`heroicons-outline:home`), a bare Dashicons name
	 * (`admin-home`) or the whole class (`dashicons-admin-home`) -- is drawn
	 * by icon(). Anything starting `dashicons-` counts as a name even when it
	 * is not a valid one, so it is never printed raw. Everything else is the
	 * caller's own markup, printed as given inside a decorative span with
	 * the icon class, so it is sized and centred like the others and kept
	 * from screen readers: the label beside it says what the crumb is.
	 *
	 * @param string $icon Icon name or HTML.
	 *
	 * @return string The rendered icon HTML.
	 */
	protected function render_icon( string $icon ): string {
		$value = trim( $icon );

		if ( ! str_starts_with( $value, 'dashicons-' ) && 1 !== preg_match( self::ICON_NAME, $value ) ) {
			return '' === $value ? '' : sprintf( '<span class="%s" aria-hidden="true">%s</span>', self::ICON_CLASS, $icon );
		}

		return $this->icon( $value );
	}

	/**
	 * Draw a named icon.
	 *
	 * A `set:name` icon goes to the `icons` callable, given the class and
	 * core's `.dashicons` alignment (`vertical-align: top`) inline, so a
	 * trail without the stylesheet does not sit an SVG on the baseline and
	 * push the label down; with no callable it draws nothing. A Dashicons
	 * name is the font's span, drawn here.
	 *
	 * @param string $value Icon name.
	 *
	 * @return string Markup, or '' when there is nothing to draw.
	 */
	private function icon( string $value ): string {
		if ( str_contains( $value, ':' ) ) {
			return null === $this->icons ? '' : (string) call_user_func(
				$this->icons,
				$value,
				[
					'class' => self::ICON_CLASS,
					'style' => 'vertical-align:top',
				]
			);
		}

		$name = str_starts_with( $value, 'dashicons-' ) ? substr( $value, strlen( 'dashicons-' ) ) : $value;

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9_-]*$/', $name ) ) {
			return '';
		}

		// Core loads the font in wp-admin and with the admin bar; a visitor
		// to a theme has neither, and the span would draw nothing.
		if ( function_exists( 'wp_enqueue_style' ) ) {
			wp_enqueue_style( 'dashicons' );
		}

		return sprintf(
			'<span class="dashicons dashicons-%s %s" aria-hidden="true"></span>',
			esc_attr( $name ),
			self::ICON_CLASS
		);
	}

	/**
	 * Build an HTML attributes string from an associative array.
	 *
	 * @param array $attributes Key/value pairs of attributes.
	 *
	 * @return string The attributes string (with leading space if non-empty).
	 */
	protected static function build_attributes( array $attributes ): string {
		if ( empty( $attributes ) ) {
			return '';
		}

		$parts = [];
		foreach ( $attributes as $key => $value ) {
			$key = self::sanitize_attribute_name( (string) $key );

			if ( '' === $key ) {
				continue;
			}

			$parts[] = sprintf( '%s="%s"', $key, esc_attr( $value ) );
		}

		return empty( $parts ) ? '' : ' ' . implode( ' ', $parts );
	}

	/**
	 * Validate an HTML attribute name, or reject it.
	 *
	 * esc_attr() escapes attribute *values*; it is the wrong tool for a name.
	 * It leaves spaces and '=' untouched, so a key of
	 * `x onfocus=alert(1) autofocus` renders as three separate attributes, and
	 * it considers `onmouseover` entirely safe because nothing in it requires
	 * escaping. Either turns caller-supplied attributes into script execution.
	 *
	 * Names are therefore checked against the HTML attribute-name grammar, and
	 * event handlers refused outright.
	 *
	 * @param string $name Raw attribute name.
	 *
	 * @return string The name if acceptable, or '' to drop the attribute.
	 */
	protected static function sanitize_attribute_name( string $name ): string {
		$name = strtolower( trim( $name ) );

		// Letters, digits, hyphen, underscore and colon only, starting with a
		// letter. Nothing matching this can contain whitespace, '=', '/', a
		// quote or '>', so it cannot break out of its own attribute.
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_:-]*$/', $name ) ) {
			return '';
		}

		// Event handlers execute script, and no breadcrumb needs one.
		if ( str_starts_with( $name, 'on' ) ) {
			return '';
		}

		return $name;
	}
}
