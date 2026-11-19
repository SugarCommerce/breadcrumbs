<?php
/**
 * A breadcrumb trail: a labelled nav, the current page marked, and Schema.org microdata.
 *
 * @package   SugarCommerce\Breadcrumbs
 * @copyright Copyright (c) 2026, ArrayPress Limited
 * @license   GPL-2.0-or-later
 * @since     1.0.0
 */

declare( strict_types=1 );

namespace SugarCommerce\Breadcrumbs;

use JsonSerializable;
use SugarCommerce\Plugin\Assets;
use Stringable;

/**
 * A fluent builder for a breadcrumb trail.
 *
 *     Breadcrumbs::create()
 *         ->add( 'Home', '/', 'admin-home' )
 *         ->add( 'Products', '/products/' )
 *         ->add_current( 'Widget Pro' )
 *         ->display();
 *
 * Every label, URL, class and attribute is escaped on the way out; the
 * separator and an icon given as markup are the caller's own HTML.
 *
 * Icons: a Dashicons name is drawn here, as core's font span. A `set:name`
 * icon (`heroicons-outline:home`) needs something that can draw it, given
 * as the `icons` callable -- this library carries no icon set.
 *
 * The package's stylesheet lays the trail out in a line, the same in
 * wp-admin and on a theme, and is enqueued by the first trail that renders.
 * `'stylesheet' => false` leaves the look entirely to the caller.
 */
class Breadcrumbs implements JsonSerializable, Stringable {

	/**
	 * What an icon given by name looks like: `set:name` or a bare Dashicons
	 * name, lower case. Anything else is raw HTML.
	 */
	private const ICON_NAME = '/^(?:[a-z0-9][a-z0-9-]*:)?[a-z0-9][a-z0-9_-]*$/';

	/**
	 * The collection of breadcrumb items.
	 *
	 * @var Item[]
	 */
	private array $items = [];

	/**
	 * The separator string between breadcrumb items.
	 *
	 * @var string
	 */
	private string $separator;

	/**
	 * The ARIA label for the nav element.
	 *
	 * @var string
	 */
	private string $aria_label;

	/**
	 * CSS class for the nav wrapper element.
	 *
	 * @var string
	 */
	private string $nav_class;

	/**
	 * CSS class for the ordered list element.
	 *
	 * @var string
	 */
	private string $list_class;

	/**
	 * CSS class for each list item element.
	 *
	 * @var string
	 */
	private string $item_class;

	/**
	 * CSS class for the separator element.
	 *
	 * @var string
	 */
	private string $separator_class;

	/**
	 * CSS class for the currently active item.
	 *
	 * @var string
	 */
	private string $active_class;

	/**
	 * What draws a `set:name` icon, or null for nothing.
	 *
	 * @var callable|null
	 */
	private $icons = null;

	/**
	 * Whether rendering loads the package's stylesheet.
	 *
	 * @var bool
	 */
	private bool $stylesheet;

	/**
	 * The class every drawn icon carries, so one rule sizes any kind.
	 */
	public const ICON_CLASS = 'sugarcommerce-breadcrumbs__icon';

	/**
	 * The class of a crumb's icon-and-label pair: the link, or the plain
	 * text of a crumb that has no link.
	 */
	public const CRUMB_CLASS = 'sugarcommerce-breadcrumbs__crumb';

	/**
	 * The class of a crumb's label, the only part a link underlines.
	 */
	public const LABEL_CLASS = 'sugarcommerce-breadcrumbs__label';

	/**
	 * The class a trail carries when it loads the stylesheet, and the root
	 * of every rule in it: a trail that opted out is left alone even on a
	 * page where another trail loaded the stylesheet.
	 */
	public const STYLED_CLASS = 'sugarcommerce-breadcrumbs--styled';

	/**
	 * The stylesheet, within the package's assets folder.
	 */
	private const STYLESHEET = 'css/breadcrumbs.css';

	/**
	 * Default configuration values.
	 *
	 * @var array<string, mixed>
	 */
	protected static array $defaults = [
		'separator'       => '&#8250;',
		'aria_label'      => 'Breadcrumb',
		'nav_class'       => 'sugarcommerce-breadcrumbs',
		'list_class'      => 'sugarcommerce-breadcrumbs__list',
		'item_class'      => 'sugarcommerce-breadcrumbs__item',
		'separator_class' => 'sugarcommerce-breadcrumbs__separator',
		'active_class'    => 'sugarcommerce-breadcrumbs__item--active',
		'icons'           => null,
		'stylesheet'      => true,
	];

	/**
	 * Create a new Breadcrumbs instance.
	 *
	 * @param array<string, mixed> $config Optional configuration overrides: `separator`,
	 *                                     `aria_label`, the five classes, `icons`
	 *                                     (see icons()) and `stylesheet` (see
	 *                                     stylesheet()).
	 */
	public function __construct( array $config = [] ) {
		$config = array_merge( self::$defaults, $config );

		$this->separator       = (string) $config['separator'];
		$this->aria_label      = (string) $config['aria_label'];
		$this->nav_class       = (string) $config['nav_class'];
		$this->list_class      = (string) $config['list_class'];
		$this->item_class      = (string) $config['item_class'];
		$this->separator_class = (string) $config['separator_class'];
		$this->active_class    = (string) $config['active_class'];
		$this->icons           = is_callable( $config['icons'] ) ? $config['icons'] : null;
		$this->stylesheet      = (bool) $config['stylesheet'];
	}

	/**
	 * Create a new Breadcrumbs instance with fluent API.
	 *
	 * @param array<string, mixed> $config Optional configuration overrides.
	 *
	 * @return static New Breadcrumbs instance.
	 */
	public static function create( array $config = [] ): static {
		return new static( $config );
	}

	/**
	 * A trail from items already assembled elsewhere.
	 *
	 *     echo Breadcrumbs::from( [
	 *         [ 'label' => 'Home', 'url' => '/' ],
	 *         [ 'label' => 'This post' ],
	 *     ] );
	 *
	 * @param array<int, array<string, mixed>> $items  Item definitions, as add_items().
	 * @param array<string, mixed>             $config Optional configuration overrides.
	 *
	 * @return static
	 */
	public static function from( array $items, array $config = [] ): static {
		return static::create( $config )->add_items( $items );
	}

	/** -------------------------------------------------------------------------
	 * Builder Methods
	 * ------------------------------------------------------------------------ */

	/**
	 * Add a breadcrumb item.
	 *
	 * @param string      $label      The display text.
	 * @param string|null $url        Optional URL. Null for the current/active item.
	 * @param string|null $icon       Optional icon: a `set:name` icon
	 *                                ('heroicons-outline:home', drawn by the `icons`
	 *                                callable), a Dashicons name ('admin-home' or
	 *                                'dashicons-admin-home'), or raw HTML. A name is escaped; anything else
	 *                                is emitted as raw HTML, so never pass
	 *                                untrusted input here.
	 * @param array       $attributes Optional extra HTML attributes for the item.
	 *                                Names are validated and event handlers dropped;
	 *                                values are escaped.
	 *
	 * @return static Self for chaining.
	 */
	public function add( string $label, ?string $url = null, ?string $icon = null, array $attributes = [] ): static {
		$this->items[] = new Item( $label, $url, $icon, $attributes );

		return $this;
	}

	/**
	 * Add a home/root breadcrumb item.
	 *
	 * @param string      $label The display text.
	 * @param string      $url   The URL for the home item.
	 * @param string|null $icon  Optional icon: `set:name`, a Dashicons name, or raw
	 *                           HTML (see add()); never pass untrusted input.
	 *
	 * @return static Self for chaining.
	 */
	public function add_home( string $label = 'Home', string $url = '/', ?string $icon = null ): static {
		return $this->add( $label, $url, $icon );
	}

	/**
	 * Add the current/active breadcrumb item (no link).
	 *
	 * @param string      $label The display text.
	 * @param string|null $icon  Optional icon: `set:name`, a Dashicons name, or raw
	 *                           HTML (see add()); never pass untrusted input.
	 *
	 * @return static Self for chaining.
	 */
	public function add_current( string $label, ?string $icon = null ): static {
		return $this->add( $label, null, $icon );
	}

	/**
	 * Add multiple items at once from an array.
	 *
	 * Each item should be an associative array with keys:
	 * - 'label' (required)
	 * - 'url' (optional)
	 * - 'icon' (optional)
	 * - 'attributes' (optional)
	 *
	 * @param array $items Array of item definitions.
	 *
	 * @return static Self for chaining.
	 */
	public function add_items( array $items ): static {
		foreach ( $items as $item ) {
			if ( is_array( $item ) && isset( $item['label'] ) ) {
				$this->add(
					$item['label'],
					$item['url'] ?? null,
					$item['icon'] ?? null,
					$item['attributes'] ?? []
				);
			}
		}

		return $this;
	}

	/**
	 * Remove all items and start fresh.
	 *
	 * @return static Self for chaining.
	 */
	public function reset(): static {
		$this->items = [];

		return $this;
	}

	/** -------------------------------------------------------------------------
	 * Configuration Methods
	 * ------------------------------------------------------------------------ */

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

	/** -------------------------------------------------------------------------
	 * Getters
	 * ------------------------------------------------------------------------ */

	/**
	 * Get all breadcrumb items.
	 *
	 * @return Item[] Array of breadcrumb items.
	 */
	public function items(): array {
		return $this->items;
	}

	/**
	 * Get the number of breadcrumb items.
	 *
	 * @return int Item count.
	 */
	public function count(): int {
		return count( $this->items );
	}

	/**
	 * Check if there are any items.
	 *
	 * @return bool True if items exist.
	 */
	public function has_items(): bool {
		return ! empty( $this->items );
	}

	/**
	 * Check if the breadcrumbs are empty.
	 *
	 * @return bool True if no items exist.
	 */
	public function is_empty(): bool {
		return empty( $this->items );
	}

	/**
	 * Get the first item.
	 *
	 * @return Item|null The first item or null.
	 */
	public function first(): ?Item {
		return $this->items[0] ?? null;
	}

	/**
	 * Get the last item.
	 *
	 * @return Item|null The last item or null.
	 */
	public function last(): ?Item {
		$count = count( $this->items );

		return $count > 0 ? $this->items[ $count - 1 ] : null;
	}

	/** -------------------------------------------------------------------------
	 * Rendering Methods
	 * ------------------------------------------------------------------------ */

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
	 * The stylesheet handle for this copy of the library.
	 *
	 * Derived from the namespace rather than written as a literal, because
	 * Strauss renames namespaces and does not rename strings: two plugins
	 * each bundling a prefixed copy would otherwise share one handle, and
	 * whichever registered first would decide the other's CSS.
	 *
	 * @return string `sugarcommerce-breadcrumbs`, or e.g.
	 *                `myplugin-lib-breadcrumbs` once prefixed.
	 */
	public static function handle(): string {
		$root = substr( __NAMESPACE__, 0, (int) strrpos( __NAMESPACE__, '\\' ) );

		return trim( strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $root ) ), '-' ) . '-breadcrumbs';
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

	/** -------------------------------------------------------------------------
	 * Output Methods
	 * ------------------------------------------------------------------------ */

	/**
	 * Get all items as an array.
	 *
	 * @return array Array of item data arrays.
	 */
	public function to_array(): array {
		return array_map( static fn( Item $item ): array => $item->to_array(), $this->items );
	}

	/** -------------------------------------------------------------------------
	 * Interface Implementations
	 * ------------------------------------------------------------------------ */

	/**
	 * String representation returns the rendered HTML.
	 *
	 * @return string The rendered breadcrumbs HTML.
	 */
	public function __toString(): string {
		return $this->render();
	}

	/**
	 * JSON serialization returns the items array.
	 *
	 * @return array Data for JSON encoding.
	 */
	public function jsonSerialize(): array {
		return $this->to_array();
	}

	/** -------------------------------------------------------------------------
	 * Internal Helpers
	 * ------------------------------------------------------------------------ */

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
