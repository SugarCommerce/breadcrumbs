<?php
/**
 * Breadcrumbs tests.
 *
 * @package SugarCommerce\Breadcrumbs
 */

declare( strict_types=1 );

namespace SugarCommerce\Breadcrumbs\Tests;

use SugarCommerce\Breadcrumbs\Breadcrumbs;
use SugarCommerce\Breadcrumbs\Models\Item;
use SugarCommerce\Plugin\Assets;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

/**
 * Building and rendering a breadcrumb trail.
 *
 * The output goes straight into an admin page, and every label, URL and
 * attribute in it can come from a caller passing through user input -- a
 * filename, a folder name someone typed. Most of what is asserted here is
 * therefore about escaping rather than markup.
 */
final class BreadcrumbsTest extends TestCase {

	/**
	 * Assert no element in the rendered markup carries an event handler.
	 *
	 * Checking for the substring "onfocus=" is the wrong test: esc_attr()
	 * renders a payload of `a" onfocus="alert(1)` as
	 * `a&quot; onfocus=&quot;alert(1)`, which is perfectly safe and still
	 * contains the substring. What matters is whether the payload broke out of
	 * its quoting and became an attribute, so parse the result and look.
	 *
	 * @param string $html Rendered markup.
	 *
	 * @return void
	 */
	private function assertNoEventHandlers( string $html ): void {
		$document = new DOMDocument();
		$loaded   = $document->loadHTML( '<meta charset="utf-8">' . $html, LIBXML_NOERROR );

		$this->assertTrue( $loaded, 'Rendered markup did not parse' );

		foreach ( ( new DOMXPath( $document ) )->query( '//*' ) as $element ) {
			foreach ( $element->attributes as $attribute ) {
				$this->assertStringStartsNotWith(
					'on',
					$attribute->nodeName,
					"Rendered an event handler: {$attribute->nodeName}"
				);
			}
		}
	}

	private function trail(): Breadcrumbs {
		return Breadcrumbs::create();
	}

	/**
	 * Forget what was registered and enqueued.
	 */
	protected function setUp(): void {
		$GLOBALS['bc_registered']   = [];
		$GLOBALS['bc_enqueued']     = [];
		// The package is served from a plugin's vendor folder, as on a site.
		$GLOBALS['wp_plugin_paths'] = [ WP_CONTENT_DIR . '/plugins/shop/vendor/sugarcommerce/breadcrumbs' => dirname( __DIR__ ) ];
		Assets::clear_cache();
	}

	// -- Building ---------------------------------------------------------

	public function test_an_empty_trail_renders_nothing(): void {
		$trail = $this->trail();

		$this->assertTrue( $trail->is_empty() );
		$this->assertSame( '', $trail->render() );
	}

	public function test_items_are_kept_in_order(): void {
		$trail = $this->trail()->add( 'One', '/one' )->add( 'Two', '/two' )->add_current( 'Three' );

		$this->assertSame( 3, $trail->count() );
		$this->assertSame( 'One', $trail->first()->label() );
		$this->assertSame( 'Three', $trail->last()->label() );
	}

	public function test_add_current_makes_an_item_without_a_link(): void {
		$item = $this->trail()->add_current( 'Here' )->first();

		$this->assertFalse( $item->is_link() );
		$this->assertNull( $item->url() );
	}

	public function test_reset_empties_the_trail(): void {
		$trail = $this->trail()->add( 'One', '/one' )->reset();

		$this->assertTrue( $trail->is_empty() );
		$this->assertNull( $trail->first() );
	}

	public function test_add_items_accepts_a_batch(): void {
		$trail = $this->trail()->add_items( [
			[ 'label' => 'One', 'url' => '/one' ],
			[ 'label' => 'Two' ],
		] );

		$this->assertSame( 2, $trail->count() );
	}

	// -- Structure --------------------------------------------------------

	public function test_the_last_item_is_the_current_page(): void {
		$html = $this->trail()->add( 'One', '/one' )->add( 'Two', '/two' )->render();

		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		// The last item is current even though it was given a URL.
		$this->assertStringNotContainsString( 'href="/two"', $html );
		$this->assertStringContainsString( 'href="/one"', $html );
	}

	public function test_separators_go_between_items_only(): void {
		$html = $this->trail()->add( 'One', '/one' )->add( 'Two', '/two' )->add( 'Three' )->render();

		$this->assertSame( 2, substr_count( $html, 'sugarcommerce-breadcrumbs__separator' ) );
	}

	public function test_positions_are_numbered_from_one(): void {
		$html = $this->trail()->add( 'One', '/one' )->add( 'Two', '/two' )->render();

		$this->assertStringContainsString( 'content="1"', $html );
		$this->assertStringContainsString( 'content="2"', $html );
	}

	public function test_the_trail_carries_schema_markup(): void {
		$html = $this->trail()->add( 'One', '/one' )->add( 'Two' )->render();

		$this->assertStringContainsString( 'https://schema.org/BreadcrumbList', $html );
		$this->assertStringContainsString( 'https://schema.org/ListItem', $html );
		$this->assertStringContainsString( 'itemprop="name"', $html );
	}

	/**
	 * The whole markup of a trail with every kind of crumb, pinned on
	 * purpose: a change to it is a change to every screen that has one, and
	 * to the stylesheet's selectors.
	 */
	public function test_the_markup_of_a_whole_trail(): void {
		$html = $this->trail()
			->add( 'Home', '/', 'admin-home' )
			->add( 'Reports' )
			->add_current( 'Sales' )
			->render();

		$this->assertSame(
			'<nav class="sugarcommerce-breadcrumbs sugarcommerce-breadcrumbs--styled" aria-label="Breadcrumb">'
			. '<ol class="sugarcommerce-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList">'
			. '<li class="sugarcommerce-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
			. '<a class="sugarcommerce-breadcrumbs__crumb" href="/" itemprop="item">'
			. '<span class="dashicons dashicons-admin-home sugarcommerce-breadcrumbs__icon" aria-hidden="true"></span>'
			. '<span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Home">Home</span></a>'
			. '<meta itemprop="position" content="1"></li>'
			. '<li class="sugarcommerce-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">'
			. '<span class="sugarcommerce-breadcrumbs__separator" aria-hidden="true">&#8250;</span>'
			. '<span class="sugarcommerce-breadcrumbs__crumb"><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Reports">Reports</span></span>'
			. '<meta itemprop="position" content="2"></li>'
			. '<li class="sugarcommerce-breadcrumbs__item sugarcommerce-breadcrumbs__item--active" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" aria-current="page">'
			. '<span class="sugarcommerce-breadcrumbs__separator" aria-hidden="true">&#8250;</span>'
			. '<span class="sugarcommerce-breadcrumbs__crumb"><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Sales">Sales</span></span>'
			. '<meta itemprop="position" content="3"></li>'
			. '</ol></nav>',
			$html
		);
	}

	/**
	 * The list holds crumbs only: a screen reader announces "list, 3 items",
	 * not five with two of them hidden. Each separator is inside the crumb
	 * it leads to, hidden, and the first crumb has none.
	 */
	public function test_separators_sit_inside_the_crumb_they_lead_to(): void {
		$xpath = new DOMXPath( self::dom( $this->trail()->add( 'One', '/one' )->add( 'Two', '/two' )->add( 'Three' )->render() ) );

		$this->assertSame( 3, $xpath->query( '//ol/li' )->length );
		$this->assertSame( 3, $xpath->query( '//ol/*' )->length );
		$this->assertSame( 0, $xpath->query( '//ol/li[1]//*[contains(@class,"__separator")]' )->length );

		foreach ( [ 2, 3 ] as $n ) {
			$first = $xpath->query( "//ol/li[{$n}]/*[1]" )->item( 0 );

			$this->assertStringContainsString( '__separator', $first->getAttribute( 'class' ) );
			$this->assertSame( 'true', $first->getAttribute( 'aria-hidden' ) );
		}
	}

	/**
	 * Inside a link there is the icon and the label and nothing else -- no
	 * text node, so no space for an underline to run along from the icon.
	 * Every kind of icon is hidden from screen readers.
	 */
	public function test_a_link_holds_an_icon_and_a_label_and_no_text(): void {
		$html  = $this->trail()
			->icons( [ self::class, 'draw' ] )
			->add( 'Dash', '/d', 'admin-home' )
			->add( 'Set', '/s', 'heroicons-outline:home' )
			->add( 'Raw', '/r', '&#9835;' )
			->add( 'Plain', '/p' )
			->add_current( 'End', 'category' )
			->render();
		$xpath = new DOMXPath( self::dom( $html ) );

		foreach ( $xpath->query( '//a' ) as $link ) {
			foreach ( $link->childNodes as $child ) {
				$this->assertSame( XML_ELEMENT_NODE, $child->nodeType, 'A text node inside a link: ' . json_encode( $child->textContent ) );
			}

			$this->assertSame( 'sugarcommerce-breadcrumbs__label', $link->lastChild->getAttribute( 'class' ) );
		}

		$icons = $xpath->query( '//*[contains(concat(" ", @class, " "), " sugarcommerce-breadcrumbs__icon ")]' );

		$this->assertSame( 4, $icons->length );
		foreach ( $icons as $icon ) {
			$this->assertSame( 'true', $icon->getAttribute( 'aria-hidden' ) );
		}
	}

	/**
	 * The current crumb is not a link, is marked current, and keeps its
	 * place in the structured data.
	 */
	public function test_the_current_crumb_is_text_marked_current_with_its_microdata(): void {
		$xpath   = new DOMXPath( self::dom( $this->trail()->add( 'One', '/one' )->add_current( 'Two' )->render() ) );
		$current = $xpath->query( '//li[@aria-current="page"]' );

		$this->assertSame( 1, $current->length );
		$this->assertSame( 0, $xpath->query( './/a', $current->item( 0 ) )->length );
		$this->assertSame( 'Two', $xpath->query( './/*[@itemprop="name"]', $current->item( 0 ) )->item( 0 )->textContent );
		$this->assertSame( '2', $xpath->query( './/meta[@itemprop="position"]', $current->item( 0 ) )->item( 0 )->getAttribute( 'content' ) );
	}

	/**
	 * The stylesheet cuts a long crumb short, so the label keeps the whole
	 * text where it can be read: nothing is lost without a trace.
	 */
	public function test_a_label_keeps_its_full_text_in_a_title(): void {
		$long  = str_repeat( 'A very long folder name ', 6 ) . '"quoted" & <b>';
		$xpath = new DOMXPath( self::dom( $this->trail()->add( $long, '/x' )->add_current( $long )->render() ) );
		$names = $xpath->query( '//*[@itemprop="name"]' );

		$this->assertSame( 2, $names->length );
		foreach ( $names as $name ) {
			$this->assertSame( $long, $name->getAttribute( 'title' ) );
			$this->assertSame( $long, $name->textContent );
		}
	}

	// -- Stylesheet -------------------------------------------------------

	public function test_rendering_a_trail_loads_the_stylesheet(): void {
		$this->trail()->add( 'One', '/one' )->render();

		$this->assertArrayHasKey( Breadcrumbs::handle(), $GLOBALS['bc_registered'] );
		$this->assertContains( Breadcrumbs::handle(), $GLOBALS['bc_enqueued'] );
		$this->assertSame( [], $GLOBALS['bc_registered'][ Breadcrumbs::handle() ]['deps'] );
	}

	/**
	 * A theme's visitor has no admin bar, so nothing else loads the font.
	 */
	public function test_a_dashicon_loads_the_dashicons_font(): void {
		$this->trail()->stylesheet( false )->add( 'One', '/one', 'heroicons-outline:home' )->add( 'Two', '/two', '<b>x</b>' )->render();

		$this->assertNotContains( 'dashicons', $GLOBALS['bc_enqueued'] );

		$this->trail()->stylesheet( false )->add( 'One', '/one', 'admin-home' )->render();

		$this->assertContains( 'dashicons', $GLOBALS['bc_enqueued'] );
	}

	public function test_an_empty_trail_loads_nothing(): void {
		$this->trail()->render();

		$this->assertSame( [], $GLOBALS['bc_enqueued'] );
	}

	public function test_a_trail_can_opt_out_of_the_stylesheet(): void {
		Breadcrumbs::create( [ 'stylesheet' => false ] )->add( 'One', '/one' )->render();
		ob_start();
		$this->trail()->stylesheet( false )->add( 'One', '/one' )->display();
		ob_end_clean();

		$this->assertSame( [], $GLOBALS['bc_enqueued'] );
		$this->assertSame( [], $GLOBALS['bc_registered'] );

		$this->trail()->stylesheet( false )->stylesheet( true )->add( 'One', '/one' )->render();

		$this->assertContains( Breadcrumbs::handle(), $GLOBALS['bc_enqueued'] );
	}

	/**
	 * Opting out drops the class every rule hangs from, and nothing else:
	 * a trail that styles itself is left alone on a page where another
	 * trail loaded the stylesheet.
	 */
	public function test_opting_out_drops_only_the_styled_class(): void {
		$styled   = $this->trail()->add( 'One', '/one', 'admin-home' )->add_current( 'Two' )->render();
		$unstyled = $this->trail()->stylesheet( false )->add( 'One', '/one', 'admin-home' )->add_current( 'Two' )->render();

		$this->assertStringContainsString( '<nav class="sugarcommerce-breadcrumbs sugarcommerce-breadcrumbs--styled"', $styled );
		$this->assertStringNotContainsString( 'sugarcommerce-breadcrumbs--styled', $unstyled );
		$this->assertSame( str_replace( ' sugarcommerce-breadcrumbs--styled', '', $styled ), $unstyled );
		$this->assertStringContainsString( '<nav class="my-nav sugarcommerce-breadcrumbs--styled"', $this->trail()->nav_class( 'my-nav' )->add( 'One' )->render() );
		$this->assertStringContainsString( '<nav class="" ', $this->trail()->stylesheet( false )->nav_class( '' )->add( 'One' )->render() );
	}

	/**
	 * Every rule hangs from the styled class, so none reaches a trail that
	 * opted out.
	 */
	public function test_every_rule_is_scoped_to_the_styled_class(): void {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/breadcrumbs.css' ) );

		preg_match_all( '/([^{}]+)\{/', $css, $matches );

		$this->assertNotEmpty( $matches[1] );
		foreach ( $matches[1] as $selectors ) {
			foreach ( explode( ',', $selectors ) as $selector ) {
				$this->assertStringStartsWith( '.' . Breadcrumbs::STYLED_CLASS, trim( $selector ) );
			}
		}
	}

	public function test_the_stylesheet_url_follows_the_package(): void {
		$this->trail()->enqueue();

		$this->assertSame(
			'https://example.test/wp-content/plugins/shop/vendor/sugarcommerce/breadcrumbs/assets/css/breadcrumbs.css',
			$GLOBALS['bc_registered'][ Breadcrumbs::handle() ]['src']
		);
		$this->assertMatchesRegularExpression( '/^\d+$/', $GLOBALS['bc_registered'][ Breadcrumbs::handle() ]['ver'] );
	}

	/**
	 * Strauss renames namespaces and not strings: two plugins each bundling
	 * a copy would otherwise share a handle, and whichever registered first
	 * would decide which CSS the other got.
	 */
	public function test_the_stylesheet_handle_is_derived_from_the_namespace(): void {
		$this->assertSame( 'sugarcommerce-breadcrumbs', Breadcrumbs::handle() );
		$this->assertSame( 'playground-lib-breadcrumbs', $this->prefixed_handle( 'Playground\\Lib\\' ) );
		$this->assertSame( 'myshop-vendor-sugarcommerce-breadcrumbs', $this->prefixed_handle( 'MyShop\\Vendor\\SugarCommerce\\' ) );
	}

	/**
	 * The handle of a copy of the class compiled the way Strauss prefixes it.
	 *
	 * @param string $prefix What replaces "SugarCommerce\" in the namespace.
	 *
	 * @return string
	 */
	private function prefixed_handle( string $prefix ): string {
		$copy = sys_get_temp_dir() . '/breadcrumbs-prefixed-' . bin2hex( random_bytes( 4 ) ) . '.php';

		file_put_contents(
			$copy,
			str_replace(
				[ 'namespace SugarCommerce\\' ],
				[ 'namespace ' . $prefix ],
				(string) file_get_contents( dirname( __DIR__ ) . '/src/Breadcrumbs.php' )
			)
		);

		try {
			require $copy;
		} finally {
			unlink( $copy );
		}

		return ( $prefix . 'Breadcrumbs\\Breadcrumbs' )::handle();
	}

	/**
	 * The stylesheet works in either direction and on any background: no
	 * physical side is named, and no colour but core's admin theme colour,
	 * which falls back to the text colour.
	 */
	public function test_the_stylesheet_names_no_side_and_no_colour(): void {
		$css = (string) preg_replace( '#/\*.*?\*/#s', '', (string) file_get_contents( dirname( __DIR__ ) . '/assets/css/breadcrumbs.css' ) );

		$this->assertDoesNotMatchRegularExpression( '/\b(left|right)\b/i', $css );
		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-f]{3,8}\b|\b(rgb|hsl)a?\(/i', $css );
		$this->assertStringContainsString( 'var(--wp-admin-theme-color, currentColor)', $css );
	}

	// -- Escaping ---------------------------------------------------------

	public function test_a_label_is_escaped(): void {
		$html = $this->trail()->add( '<script>alert(1)</script>', '/x' )->add( 'End' )->render();

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function test_a_label_cannot_break_out_of_an_attribute(): void {
		$this->assertNoEventHandlers(
			$this->trail()->add( 'a" onmouseover="alert(1)', '/x' )->add( 'End' )->render()
		);
	}

	public function test_a_url_is_escaped(): void {
		$html = $this->trail()->add( 'Link', '/path?a=1&b=2' )->add( 'End' )->render();

		$this->assertStringContainsString( 'a=1&amp;b=2', $html );
	}

	/**
	 * A folder name arriving from storage can be anything, and it reaches the
	 * href as a path segment.
	 */
	public function test_a_javascript_url_does_not_reach_the_href(): void {
		$html = $this->trail()->add( 'Click', 'javascript:alert(1)' )->add( 'End' )->render();

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_a_config_class_is_escaped(): void {
		$this->assertNoEventHandlers(
			Breadcrumbs::create( [ 'nav_class' => 'x" onload="alert(1)' ] )->add( 'One' )->render()
		);
	}

	// -- Caller-supplied attributes ---------------------------------------

	public function test_a_plain_attribute_is_rendered(): void {
		$html = $this->trail()->add( 'One', '/one', null, [ 'data-bucket' => 'my-bucket' ] )->add( 'End' )->render();

		$this->assertStringContainsString( 'data-bucket="my-bucket"', $html );
	}

	public function test_an_attribute_value_is_escaped(): void {
		$this->assertNoEventHandlers(
			$this->trail()->add( 'One', '/one', null, [ 'data-x' => 'a" onfocus="alert(1)' ] )->add( 'End' )->render()
		);
	}

	/**
	 * esc_attr() escapes attribute *values* and is the wrong tool for a name:
	 * it leaves spaces and '=' untouched, so this key would otherwise render
	 * as three separate attributes, one of them an event handler.
	 */
	public function test_an_attribute_name_cannot_smuggle_in_a_second_attribute(): void {
		$html = $this->trail()
			->add( 'One', '/one', null, [ 'x onfocus=alert(1) autofocus' => 'y' ] )
			->add( 'End' )
			->render();

		$this->assertStringNotContainsString( 'autofocus', $html );
		$this->assertNoEventHandlers( $html );
	}

	/**
	 * Nothing in a breadcrumb needs an event handler, and esc_attr() considers
	 * one entirely safe because no character in it requires escaping.
	 */
	public function test_event_handler_attributes_are_refused(): void {
		$html = $this->trail()
			->add( 'One', '/one', null, [ 'onmouseover' => 'alert(1)', 'onclick' => 'alert(2)' ] )
			->add( 'End' )
			->render();

		$this->assertNoEventHandlers( $html );
	}

	public function test_a_malformed_attribute_name_is_dropped(): void {
		$html = $this->trail()
			->add( 'One', '/one', null, [ '9bad' => 'x', '' => 'y', 'has space' => 'z' ] )
			->add( 'End' )
			->render();

		$this->assertStringNotContainsString( '9bad', $html );
		$this->assertStringNotContainsString( 'has space', $html );
	}

	// -- Icons ------------------------------------------------------------

	public function test_a_dashicon_becomes_a_span(): void {
		$html = $this->trail()->add( 'One', '/one', 'dashicons-database' )->add( 'End' )->render();

		$this->assertStringContainsString( '<span class="dashicons dashicons-database sugarcommerce-breadcrumbs__icon" aria-hidden="true"></span><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="One">One', $html );
	}

	public function test_a_bare_dashicons_name_becomes_the_same_span(): void {
		$this->assertSame(
			$this->trail()->add( 'One', '/one', 'dashicons-database' )->render(),
			$this->trail()->add( 'One', '/one', 'database' )->render()
		);
	}

	/**
	 * A stand-in icon library: draws `set:name` it knows, '' otherwise.
	 *
	 * @param string               $name Icon name.
	 * @param array<string, mixed> $args What the trail asked for.
	 *
	 * @return string
	 */
	public static function draw( string $name, array $args ): string {
		return in_array( $name, [ 'heroicons-outline:home', 'heroicons-outline:folder' ], true )
			? sprintf( '<svg class="%s" style="%s" aria-hidden="true" data-icon="%s"></svg>', $args['class'], $args['style'], $name )
			: '';
	}

	public function test_a_set_icon_is_drawn_by_the_callable(): void {
		$html = $this->trail()->icons( [ self::class, 'draw' ] )->add( 'Home', '/', 'heroicons-outline:home' )->add_current( 'End' )->render();

		$this->assertStringContainsString( '<svg class="sugarcommerce-breadcrumbs__icon" style="vertical-align:top" aria-hidden="true" data-icon="heroicons-outline:home"></svg><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Home">Home', $html );
		$this->assertStringNotContainsString( 'dashicons', $html );
	}

	public function test_a_set_icon_works_on_the_current_item_and_from_an_array(): void {
		$current = Breadcrumbs::create( [ 'icons' => [ self::class, 'draw' ] ] )->add_current( 'End', 'heroicons-outline:folder' )->render();
		$array   = Breadcrumbs::from( [ [ 'label' => 'End', 'icon' => 'heroicons-outline:folder' ] ], [ 'icons' => [ self::class, 'draw' ] ] )->render();

		$this->assertStringContainsString( 'data-icon="heroicons-outline:folder"', $current );
		$this->assertStringContainsString( 'data-icon="heroicons-outline:folder"', $array );
	}

	public function test_an_icon_the_callable_cannot_draw_draws_nothing(): void {
		$html = $this->trail()->icons( [ self::class, 'draw' ] )->add( 'One', '/one', 'heroicons-outline:not-an-icon' )->add( 'End' )->render();

		$this->assertStringNotContainsString( 'not-an-icon', $html );
		$this->assertStringContainsString( 'itemprop="item"><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="One">One', $html );
	}

	/**
	 * With nothing to draw it, a set icon draws nothing rather than printing
	 * its name; a Dashicons name is still the span.
	 */
	public function test_without_an_icon_callable(): void {
		$html = $this->trail()
			->add( 'Home', '/', 'dashicons-admin-home' )
			->add( 'Bare', '/b', 'category' )
			->add( 'Set', '/s', 'heroicons-outline:home' )
			->add_current( 'End' )
			->render();

		$this->assertStringContainsString( '<span class="dashicons dashicons-admin-home sugarcommerce-breadcrumbs__icon" aria-hidden="true"></span><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Home">Home', $html );
		$this->assertStringContainsString( '<span class="dashicons dashicons-category sugarcommerce-breadcrumbs__icon" aria-hidden="true"></span><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Bare">Bare', $html );
		$this->assertStringContainsString( 'itemprop="item"><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Set">Set', $html );
		$this->assertStringNotContainsString( 'heroicons', $html );
	}

	public function test_from_builds_the_same_trail_as_add_items(): void {
		$items = [ [ 'label' => 'Home', 'url' => '/' ], [ 'label' => 'This post' ] ];

		$this->assertSame( Breadcrumbs::create()->add_items( $items )->render(), Breadcrumbs::from( $items )->render() );
		$this->assertSame( 'x-nav sugarcommerce-breadcrumbs--styled', ( new \DOMXPath( self::dom( Breadcrumbs::from( $items, [ 'nav_class' => 'x-nav' ] )->render() ) ) )->query( '//nav' )->item( 0 )->getAttribute( 'class' ) );
	}

	/**
	 * Parse markup.
	 *
	 * @param string $html Markup.
	 *
	 * @return DOMDocument
	 */
	private static function dom( string $html ): DOMDocument {
		$document = new DOMDocument();
		$document->loadHTML( '<meta charset="utf-8">' . $html, LIBXML_NOERROR );

		return $document;
	}

	/**
	 * Markup is printed as given, inside a decorative box with the icon
	 * class: sized and centred like the named kinds, and kept from screen
	 * readers, since the label says what the crumb is.
	 */
	public function test_markup_is_still_printed_as_given(): void {
		$this->assertStringContainsString(
			'<span class="sugarcommerce-breadcrumbs__icon" aria-hidden="true"><img src="/i.png" alt=""></span><span class="sugarcommerce-breadcrumbs__label"',
			$this->trail()->add( 'One', '/one', '<img src="/i.png" alt="">' )->render()
		);

		// Text that is not lower-case name-shaped is the caller's, not a name.
		$this->assertStringContainsString( 'aria-hidden="true">Go &raquo;</span><span', $this->trail()->add( 'One', '/one', 'Go &raquo;' )->render() );
		$this->assertStringContainsString( 'aria-hidden="true">New</span><span', $this->trail()->add( 'One', '/one', 'New' )->render() );
	}

	public function test_an_empty_icon_draws_no_box(): void {
		$this->assertStringNotContainsString( 'sugarcommerce-breadcrumbs__icon', $this->trail()->add( 'One', '/one', '  ' )->render() );
	}

	public function test_a_middle_crumb_without_a_url_is_not_the_current_page(): void {
		$html = $this->trail()->add( 'Home', '/' )->add( 'Reports' )->add_current( 'Sales' )->render();

		$this->assertSame( 1, substr_count( $html, 'aria-current="page"' ) );
		$this->assertSame( 1, substr_count( $html, 'sugarcommerce-breadcrumbs__item--active' ) );
		$this->assertStringContainsString( '<li class="sugarcommerce-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem"><span class="sugarcommerce-breadcrumbs__separator" aria-hidden="true">&#8250;</span><span class="sugarcommerce-breadcrumbs__crumb"><span class="sugarcommerce-breadcrumbs__label" itemprop="name" title="Reports">Reports</span></span>', $html );
	}

	public function test_a_dashicons_name_that_is_not_one_draws_nothing(): void {
		$html = $this->trail()->add( 'One', '/one', 'dashicons-Not_A Name' )->add( 'End' )->render();

		$this->assertStringNotContainsString( 'dashicons', $html );
	}

	public function test_a_dashicon_class_is_escaped(): void {
		$this->assertNoEventHandlers(
			$this->trail()->add( 'One', '/one', 'dashicons-x" onload="alert(1)' )->add( 'End' )->render()
		);
	}

	// -- Output forms -----------------------------------------------------

	public function test_display_echoes_what_render_returns(): void {
		$trail = $this->trail()->add( 'One', '/one' )->add( 'Two' );

		ob_start();
		$trail->display();

		$this->assertSame( $trail->render(), (string) ob_get_clean() );
	}

	public function test_string_casting_renders( ): void {
		$trail = $this->trail()->add( 'One', '/one' )->add( 'Two' );

		$this->assertSame( $trail->render(), (string) $trail );
	}

	public function test_json_serialization_returns_the_items(): void {
		$trail = $this->trail()->add( 'One', '/one' )->add_current( 'Two' );

		$decoded = json_decode( (string) json_encode( $trail ), true );

		$this->assertCount( 2, $decoded );
		$this->assertSame( 'One', $decoded[0]['label'] );
		$this->assertNull( $decoded[1]['url'] );
	}

	public function test_an_item_stringifies_to_its_label(): void {
		$this->assertSame( 'Label', (string) new Item( 'Label', '/x' ) );
	}
}
