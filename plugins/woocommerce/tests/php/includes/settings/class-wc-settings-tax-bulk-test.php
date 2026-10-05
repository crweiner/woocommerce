<?php
/**
 * Tax rate table preferences tests.
 *
 * @package WooCommerce\Tests\Settings
 */

declare( strict_types = 1 );

/**
 * Tests for tax rate table screen options.
 */
class WC_Settings_Tax_Bulk_Test extends WC_Unit_Test_Case {
	/**
	 * @testdox Shows screen options only for valid tax rate tables.
	 * @testWith ["standard", true]
	 *           ["reduced-rate", true]
	 *           ["", false]
	 *           ["unknown", false]
	 *
	 * @param string $section Selected settings section.
	 * @param bool   $expected Whether the option should be registered.
	 */
	public function test_screen_option_registration( string $section, bool $expected ): void {
		global $current_tab, $current_section;
		$previous = array( $current_tab, $current_section );
		try {
			WC_Admin_Settings::get_settings_pages();
			WC_Tax::create_tax_class( 'Reduced rate' );
			wp_set_current_user( $this->factory->user->create( array( 'role' => 'shop_manager' ) ) );
			set_current_screen( 'woocommerce_page_wc-settings' );
			get_current_screen()->add_option( 'per_page', null );
			$current_tab     = 'tax';
			$current_section = $section;
			$sut             = new WC_Settings_Tax();
			$sut->handle_woocommerce_settings_page_init();
			$option = get_current_screen()->get_option( 'per_page' );
			$this->assertSame( $expected, null !== $option, 'Only tax rate tables should offer this preference.' );
		} finally {
			list( $current_tab, $current_section ) = $previous;
		}
	}

	/**
	 * @testdox Accepts only integer table sizes within WordPress screen option bounds.
	 * @testWith ["500", 500]
	 *           [1, 1]
	 *           [999, 999]
	 *           [0, false]
	 *           [-1, false]
	 *           [1000, false]
	 *           ["10.5", false]
	 *           [[], false]
	 *
	 * @param mixed $value Submitted preference.
	 * @param mixed $expected Expected accepted value.
	 */
	public function test_screen_option_validation( $value, $expected ): void {
		$sut = new WC_Admin_Menus();
		$this->assertSame( $expected, $sut->set_screen_option( false, 'woocommerce_tax_rates_per_page', $value ), 'Invalid or unbounded preferences must not be saved.' );
		$this->assertSame( 7, $sut->set_screen_option( 7, 'unrelated_option', $value ), 'Other screen options should retain their previous filter result.' );
	}

	/**
	 * @testdox Uses the current user's preference and falls back for invalid values.
	 * @testWith [500, 500]
	 *           [999, 999]
	 *           [0, 100]
	 *           [1000, 100]
	 *           ["invalid", 100]
	 *
	 * @param mixed $value Stored preference.
	 * @param int   $expected Expected table size.
	 */
	public function test_localized_table_size( $value, int $expected ): void {
		WC_Admin_Settings::get_settings_pages();
		wp_set_current_user( $this->factory->user->create( array( 'role' => 'shop_manager' ) ) );
		update_user_option( get_current_user_id(), 'woocommerce_tax_rates_per_page', $value );
		wp_register_script( 'wc-settings-tax', '/tax-table.js', array(), 'test', true );
		$sut = new WC_Settings_Tax();
		ob_start();
		$sut->output_tax_rates();
		ob_end_clean();
		$this->assertStringContainsString( '"limit":"' . $expected . '"', wp_scripts()->get_data( 'wc-settings-tax', 'data' ), 'The table should use the current merchant preference.' );
	}
}
