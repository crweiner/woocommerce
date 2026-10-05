<?php
declare( strict_types = 1 );

/**
 * Tests for the tax rate CSV importer.
 */
class WC_Tax_Rate_Importer_Test extends WC_Unit_Test_Case {

	/**
	 * The System Under Test.
	 *
	 * @var WC_Tax_Rate_Importer
	 */
	private $sut;

	/**
	 * Load the importer and create its test instance.
	 */
	public function setUp(): void {
		parent::setUp();
		require_once ABSPATH . 'wp-admin/includes/class-wp-importer.php';
		require_once WC_ABSPATH . 'includes/admin/importers/class-wc-tax-rate-importer.php';
		$this->sut = new WC_Tax_Rate_Importer();
	}

	/**
	 * @testdox Should update matching rates without changing their IDs or order, and fire the update hook.
	 */
	public function test_updates_existing_rate_and_preserves_its_id(): void {
		$rate_id = $this->create_rate();
		WC_Tax::_update_tax_rate( $rate_id, array( 'tax_rate_order' => 42 ) );
		$lookup = array(
			'country'  => 'US',
			'state'    => 'PA',
			'postcode' => '15222',
			'city'     => 'PITTSBURGH',
		);
		$this->assertSame( 6.0, WC_Tax::find_rates( $lookup )[ $rate_id ]['rate'], 'Prime the cached tax lookup before importing the update.' );
		$updated_ids = array();
		add_action(
			'woocommerce_tax_rate_updated',
			static function ( $id ) use ( &$updated_ids ) {
				$updated_ids[] = $id;
			}
		);

		$this->sut->update_existing = true;
		$output                     = $this->import_rows( array( array( 'US', 'PA', '15222', 'Pittsburgh', '7', 'Updated tax', '1', '1', '0', '' ) ) );
		$rate                       = WC_Tax::_get_tax_rate( $rate_id );

		$this->assertSame( '7.0000', $rate['tax_rate'], 'The CSV rate should replace the matching rate.' );
		$this->assertSame( 'Updated tax', $rate['tax_rate_name'], 'The CSV name should replace the matching rate name.' );
		$this->assertSame( '1', $rate['tax_rate_compound'], 'The CSV should update the compound flag.' );
		$this->assertSame( '0', $rate['tax_rate_shipping'], 'The CSV should update the shipping flag.' );
		$this->assertSame( '42', $rate['tax_rate_order'], 'Updating a subset must preserve its relative order.' );
		$this->assertSame( 7.0, WC_Tax::find_rates( $lookup )[ $rate_id ]['rate'], 'Checkout tax lookups must see the updated rate after cache invalidation.' );
		$this->assertSame( array( $rate_id ), $updated_ids, 'Extensions should receive the existing update lifecycle hook.' );
		$this->assertSame( array( $rate_id ), $this->get_rate_ids(), 'Matching rates must retain their existing IDs without duplicates.' );
		$this->assertStringContainsString( 'updated <strong>1</strong>', $output, 'The summary should distinguish updated rates from additions.' );
	}

	/**
	 * @testdox Should preserve append-only imports unless the merchant opts into updating rates.
	 */
	public function test_default_import_adds_duplicate_without_updating_existing_rate(): void {
		$rate_id = $this->create_rate();
		$this->import_rows( array( array( 'US', 'PA', '15222', 'Pittsburgh', '7', 'New tax', '1', '0', '1', '' ) ) );

		$this->assertCount( 2, $this->get_rate_ids(), 'The default importer behavior must still append rows.' );
		$this->assertSame( '6.0000', WC_Tax::_get_tax_rate( $rate_id )['tax_rate'], 'Append-only imports must not change an existing rate.' );
	}

	/**
	 * @testdox Should match normalized postcode and city sets regardless of their order, duplicates, or case.
	 */
	public function test_matches_normalized_location_sets(): void {
		$rate_id                    = $this->create_rate( array(), '15222;15223;SW1A*;10000...10010', 'Pittsburgh;London' );
		$this->sut->update_existing = true;
		$this->import_rows( array( array( 'us', 'pa', ' sw1a* ;15223;10000…10010;15222;15222', ' london ;pittsburgh;London', '7', 'New tax', '01', '0', '1', 'standard' ) ) );

		$this->assertSame( array( $rate_id ), $this->get_rate_ids(), 'Equivalent location sets must match the same rate.' );
		$this->assertSame( '7.0000', WC_Tax::_get_tax_rate( $rate_id )['tax_rate'], 'A normalized match must update the existing rate.' );
	}

	/**
	 * @testdox Should match unrestricted locations when the CSV uses wildcards instead of empty values.
	 */
	public function test_matches_unrestricted_locations(): void {
		$rate_id                    = $this->create_rate(
			array(
				'tax_rate_country' => '',
				'tax_rate_state'   => '',
			),
			'',
			''
		);
		$this->sut->update_existing = true;
		$this->import_rows( array( array( '*', '*', '*', '*', '7', 'New tax', '1', '0', '1', '' ) ) );

		$this->assertSame( array( $rate_id ), $this->get_rate_ids(), 'Wildcards and empty location restrictions must identify the same rate.' );
		$this->assertSame( '7.0000', WC_Tax::_get_tax_rate( $rate_id )['tax_rate'], 'The unrestricted rate should be updated.' );
	}

	/**
	 * @testdox Should keep rates with different location restrictions, priorities, and tax classes separate.
	 * @testWith [0, "CA"]
	 *           [1, "NY"]
	 *           [2, "15223"]
	 *           [2, "15222;15223"]
	 *           [2, "152*"]
	 *           [3, "Philadelphia"]
	 *           [3, "Pittsburgh;Philadelphia"]
	 *           [6, "2"]
	 *           [9, "reduced-rate"]
	 *
	 * @param int    $column The identity column to change.
	 * @param string $value The new identity value.
	 */
	public function test_does_not_update_different_identity( int $column, string $value ): void {
		WC_Tax::create_tax_class( 'Reduced rate' );
		$rate_id        = $this->create_rate();
		$row            = array( 'US', 'PA', '15222', 'Pittsburgh', '7', 'New tax', '1', '0', '1', '' );
		$row[ $column ] = $value;

		$this->sut->update_existing = true;
		$this->import_rows( array( $row ) );

		$this->assertCount( 2, $this->get_rate_ids(), 'Different identities should be added without replacing existing rates.' );
		$this->assertSame( '6.0000', WC_Tax::_get_tax_rate( $rate_id )['tax_rate'], 'Different identities must not update the existing rate.' );
	}

	/**
	 * @testdox Should reuse rates added earlier in the same CSV and on repeated imports.
	 */
	public function test_repeated_csv_rows_and_imports_do_not_add_duplicates(): void {
		$rows                       = array(
			array( 'US', 'PA', '19103', 'Philadelphia', '6', 'Initial tax', '1', '0', '1', '' ),
			array( 'US', 'PA', '19103', 'Philadelphia', '7', 'Final tax', '1', '0', '1', '' ),
		);
		$this->sut->update_existing = true;
		$this->import_rows( $rows );
		$ids = $this->get_rate_ids();
		$this->import_rows( $rows );

		$this->assertCount( 1, $ids, 'Repeated CSV identities must not add multiple rates.' );
		$this->assertSame( $ids, $this->get_rate_ids(), 'Reimporting matching CSV identities must retain IDs.' );
		$this->assertSame( '7.0000', WC_Tax::_get_tax_rate( $ids[0] )['tax_rate'], 'The last matching CSV row should supply the updated rate.' );
	}

	/**
	 * @testdox Should skip ambiguous matches without updating, deleting, or adding another duplicate.
	 */
	public function test_skips_ambiguous_existing_rates(): void {
		$first_id                   = $this->create_rate();
		$second_id                  = $this->create_rate( array( 'tax_rate' => '8' ) );
		$this->sut->update_existing = true;
		$output                     = $this->import_rows( array( array( 'US', 'PA', '15222', 'Pittsburgh', '7', 'New tax', '1', '0', '1', '' ) ) );

		$this->assertSame( array( $first_id, $second_id ), $this->get_rate_ids(), 'An ambiguous match must not create or delete rates.' );
		$this->assertSame( '6.0000', WC_Tax::_get_tax_rate( $first_id )['tax_rate'], 'An ambiguous match must preserve the first existing rate.' );
		$this->assertSame( '8.0000', WC_Tax::_get_tax_rate( $second_id )['tax_rate'], 'An ambiguous match must preserve the second existing rate.' );
		$this->assertStringContainsString( 'Skipped <strong>1</strong> rows', $output, 'Ambiguous matches should be reported for merchant review.' );
	}

	/**
	 * @testdox Should skip malformed CSV rows and continue importing valid rows.
	 */
	public function test_skips_malformed_rows(): void {
		$this->sut->update_existing = true;
		$output                     = $this->import_rows(
			array(
				array( 'US', 'PA' ),
				array( 'US', 'PA', '15222', 'Pittsburgh', '7', 'New tax', '1', '0', '1', '' ),
			)
		);

		$this->assertCount( 1, $this->get_rate_ids(), 'Only valid CSV rows should be saved.' );
		$this->assertStringContainsString( 'Skipped <strong>1</strong> rows', $output, 'Malformed rows should be reported for merchant review.' );
	}

	/**
	 * @testdox Should reject invalid values that could zero existing rates or map unknown classes to standard rates.
	 * @testWith [4, ""]
	 *           [4, "invalid"]
	 *           [4, "-1"]
	 *           [4, "1e309"]
	 *           [4, "1000"]
	 *           [4, "999.99999"]
	 *           [6, "invalid"]
	 *           [9, "unknown-class"]
	 *
	 * @param int    $column The invalid CSV column.
	 * @param string $value The invalid value.
	 */
	public function test_skips_invalid_update_values( int $column, string $value ): void {
		$rate_id                    = $this->create_rate();
		$row                        = array( 'US', 'PA', '15222', 'Pittsburgh', '7', 'New tax', '1', '0', '1', '' );
		$row[ $column ]             = $value;
		$this->sut->update_existing = true;
		$output                     = $this->import_rows( array( $row ) );

		$this->assertSame( array( $rate_id ), $this->get_rate_ids(), 'Invalid CSV values must not add tax rates.' );
		$this->assertSame( '6.0000', WC_Tax::_get_tax_rate( $rate_id )['tax_rate'], 'Invalid CSV values must not overwrite existing rates.' );
		$this->assertStringContainsString( 'Skipped <strong>1</strong> rows', $output, 'Invalid CSV values should be reported for merchant review.' );
	}

	/**
	 * @testdox Should reject tax imports by users without permission even when the upload nonce is valid.
	 */
	public function test_dispatch_requires_permission_to_manage_tax_rates(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->sut = $this->getMockBuilder( WC_Tax_Rate_Importer::class )->onlyMethods( array( 'header', 'handle_upload' ) )->getMock();
		$this->sut->expects( $this->never() )->method( 'handle_upload' );
		$get     = $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve the request context before simulating an import.
		$post    = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Preserve the request context before simulating an import.
		$request = $_REQUEST; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Preserve the request context before simulating an import.
		$this->expectException( WPDieException::class );
		$this->expectExceptionMessage( 'You do not have permission to manage tax rates.' );

		try {
			$_GET['step']             = '1';
			$_POST['update_existing'] = '1';
			$_REQUEST['_wpnonce']     = wp_create_nonce( 'import-upload' );
			$this->sut->dispatch();
		} finally {
			$_GET     = $get;
			$_POST    = $post;
			$_REQUEST = $request;
		}
	}

	/**
	 * Create a tax rate and its location restrictions.
	 *
	 * @param array  $overrides Tax rate values to override.
	 * @param string $postcodes Postcodes to assign.
	 * @param string $cities Cities to assign.
	 * @return int
	 */
	private function create_rate( array $overrides = array(), string $postcodes = '15222', string $cities = 'Pittsburgh' ): int {
		$rate_id = WC_Tax::_insert_tax_rate(
			array_merge(
				array(
					'tax_rate_country'  => 'US',
					'tax_rate_state'    => 'PA',
					'tax_rate'          => '6',
					'tax_rate_name'     => 'Original tax',
					'tax_rate_priority' => '1',
					'tax_rate_compound' => 0,
					'tax_rate_shipping' => 1,
					'tax_rate_order'    => 0,
					'tax_rate_class'    => '',
				),
				$overrides
			)
		);
		WC_Tax::_update_tax_rate_postcodes( $rate_id, $postcodes );
		WC_Tax::_update_tax_rate_cities( $rate_id, $cities );

		return $rate_id;
	}

	/**
	 * Import temporary CSV rows and capture the completion notice.
	 *
	 * @param array $rows CSV rows.
	 * @return string
	 */
	private function import_rows( array $rows ): string {
		$file   = wp_tempnam( 'tax-rates.csv' );
		$handle = fopen( $file, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- CSV fixtures need a stream for fputcsv.
		fputcsv( $handle, array( 'Country code', 'State code', 'ZIP/Postcode', 'City', 'Rate %', 'Tax name', 'Priority', 'Compound', 'Shipping', 'Tax class' ) );
		foreach ( $rows as $row ) {
			fputcsv( $handle, $row );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the fixture CSV stream before importing it.

		ob_start();
		try {
			$this->sut->import( $file );
			return (string) ob_get_contents();
		} finally {
			ob_end_clean();
			wp_delete_file( $file );
		}
	}

	/**
	 * Get the stored tax rate IDs in insertion order.
	 *
	 * @return int[]
	 */
	private function get_rate_ids(): array {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col( "SELECT tax_rate_id FROM {$wpdb->prefix}woocommerce_tax_rates ORDER BY tax_rate_id" ) );
	}
}
