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
use Stringable;
use SugarCommerce\Breadcrumbs\Models\Item;
use SugarCommerce\Breadcrumbs\Traits\Breadcrumbs\Settings;
use SugarCommerce\Breadcrumbs\Traits\Breadcrumbs\Rendering;

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

	use Settings;
	use Rendering;

	/**
	 * What an icon given by name looks like: `set:name` or a bare Dashicons
	 * name, lower case. Anything else is raw HTML.
	 */
	private const string ICON_NAME = '/^(?:[a-z0-9][a-z0-9-]*:)?[a-z0-9][a-z0-9_-]*$/';

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
	public const string ICON_CLASS = 'sugarcommerce-breadcrumbs__icon';

	/**
	 * The class of a crumb's icon-and-label pair: the link, or the plain
	 * text of a crumb that has no link.
	 */
	public const string CRUMB_CLASS = 'sugarcommerce-breadcrumbs__crumb';

	/**
	 * The class of a crumb's label, the only part a link underlines.
	 */
	public const string LABEL_CLASS = 'sugarcommerce-breadcrumbs__label';

	/**
	 * The class a trail carries when it loads the stylesheet, and the root
	 * of every rule in it: a trail that opted out is left alone even on a
	 * page where another trail loaded the stylesheet.
	 */
	public const string STYLED_CLASS = 'sugarcommerce-breadcrumbs--styled';

	/**
	 * The stylesheet, within the package's assets folder.
	 */
	private const string STYLESHEET = 'css/breadcrumbs.css';

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
	#[\Override]
	public function __toString(): string {
		return $this->render();
	}

	/**
	 * JSON serialization returns the items array.
	 *
	 * @return array Data for JSON encoding.
	 */
	#[\Override]
	public function jsonSerialize(): array {
		return $this->to_array();
	}

	/** -------------------------------------------------------------------------
	 * Internal Helpers
	 * ------------------------------------------------------------------------ */

	/**
	 * The stylesheet handle for this copy of the library.
	 *
	 * Derived from the namespace rather than written as a literal, because
	 * Strauss renames namespaces and does not rename strings: two plugins
	 * each bundling a prefixed copy would otherwise share one handle, and
	 * whichever registered first would decide the other's CSS.
	 *
	 * Here, not in a trait: a trait's __NAMESPACE__ is its own.
	 *
	 * @return string `sugarcommerce-breadcrumbs`, or e.g.
	 *                `myplugin-lib-breadcrumbs` once prefixed.
	 */
	public static function handle(): string {
		$root = substr( __NAMESPACE__, 0, (int) strrpos( __NAMESPACE__, '\\' ) );

		return trim( strtolower( (string) preg_replace( '/[^A-Za-z0-9]+/', '-', $root ) ), '-' ) . '-breadcrumbs';
	}
}
