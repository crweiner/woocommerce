<?php
/**
 * Tax importer class file
 *
 * @version 2.3.0
 * @package WooCommerce\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_Importer' ) ) {
	return;
}

/**
 * Tax Rates importer - import tax rates and local tax rates into WooCommerce.
 *
 * @package     WooCommerce\Admin\Importers
 * @version     2.3.0
 */
class WC_Tax_Rate_Importer extends WP_Importer {

	/**
	 * The current file id.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * The current file url.
	 *
	 * @var string
	 */
	public $file_url;

	/**
	 * The current import page.
	 *
	 * @var string
	 */
	public $import_page;

	/**
	 * The current delimiter.
	 *
	 * @var string
	 */
	public $delimiter;

	/**
	 * Error message for import.
	 *
	 * @var string
	 */
	public $import_error_message;

	/**
	 * Whether matching tax rates should be updated instead of added.
	 *
	 * @since 11.3.0
	 * @var bool
	 */
	public $update_existing = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->import_page = 'woocommerce_tax_rate_csv';
		$this->delimiter   = empty( $_POST['delimiter'] ) ? ',' : (string) wc_clean( wp_unslash( $_POST['delimiter'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Constructor only sets the delimiter; import dispatch handles authorization.
	}

	/**
	 * Registered callback function for the WordPress Importer.
	 *
	 * Manages the three separate stages of the CSV import process.
	 */
	public function dispatch() {

		$this->header();

		$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];

		switch ( $step ) {

			case 0:
				$this->greet();
				break;

			case 1:
				check_admin_referer( 'import-upload' );
				if ( ! current_user_can( 'manage_woocommerce' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage tax rates.', 'woocommerce' ) );
				}
				$this->update_existing = '1' === wc_clean( wp_unslash( $_POST['update_existing'] ?? '' ) );

				if ( $this->handle_upload() ) {

					if ( $this->id ) {
						$file = get_attached_file( $this->id );
					} else {
						$file = ABSPATH . $this->file_url;
					}

					add_filter( 'http_request_timeout', array( $this, 'bump_request_timeout' ) );

					$this->import( $file );
				} else {
					$this->import_error( $this->import_error_message );
				}
				break;
		}

		$this->footer();
	}

	/**
	 * Import is starting.
	 */
	private function import_start() {
		if ( function_exists( 'gc_enable' ) ) {
			gc_enable(); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.gc_enableFound
		}
		wc_set_time_limit( 0 );
		@ob_flush();
		@flush();
	}

	/**
	 * UTF-8 encode the data if `$enc` value isn't UTF-8.
	 *
	 * @param mixed  $data Data.
	 * @param string $enc Encoding.
	 * @return string
	 */
	public function format_data_from_csv( $data, $enc ) {
		return ( 'UTF-8' === $enc ) ? $data : utf8_encode( $data );
	}

	/**
	 * Import the file if it exists and is valid.
	 *
	 * @param mixed $file File.
	 */
	public function import( $file ) {
		if ( ! is_file( $file ) ) {
			$this->import_error( __( 'The file does not exist, please try again.', 'woocommerce' ) );
		}

		$this->import_start();

		$loop           = 0;
		$imported       = 0;
		$updated        = 0;
		$skipped        = 0;
		$existing_rates = $this->update_existing ? $this->get_existing_tax_rates() : array();
		$tax_classes    = $this->update_existing ? array_merge( array( '', 'standard' ), WC_Tax::get_tax_class_slugs() ) : array();
		$handle         = fopen( $file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream CSV rows without loading the entire import into memory.

		if ( false !== $handle ) {

			$header = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );
			$count  = is_countable( $header ) ? count( $header ) : 0;
			if ( 10 === $count ) {

				$row = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );

				while ( false !== $row ) {
					if ( ! is_array( $row ) || 10 !== count( $row ) ) {
						++$skipped;
						$row = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );
						continue;
					}

					list( $country, $state, $postcode, $city, $rate, $name, $priority, $compound, $shipping, $class ) = array_map( 'strval', $row );
					$postcode = sanitize_text_field( $postcode );
					$city     = sanitize_text_field( $city );

					$tax_rate = array(
						'tax_rate_country'  => $country,
						'tax_rate_state'    => $state,
						'tax_rate'          => $rate,
						'tax_rate_name'     => $name,
						'tax_rate_priority' => $priority,
						'tax_rate_compound' => $compound ? 1 : 0,
						'tax_rate_shipping' => $shipping ? 1 : 0,
						'tax_rate_order'    => $loop++,
						'tax_rate_class'    => $class,
					);
					if ( $this->update_existing && ! self::is_valid_tax_rate( $tax_rate, $tax_classes ) ) {
						++$skipped;
						$row = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );
						continue;
					}

					$key   = $this->update_existing ? self::get_tax_rate_key( $tax_rate, explode( ';', $postcode ), explode( ';', $city ) ) : '';
					$match = $existing_rates[ $key ] ?? null;
					if ( $this->update_existing && false === $match ) {
						++$skipped;
						$row = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );
						continue;
					}

					if ( $this->update_existing && is_int( $match ) ) {
						$tax_rate_id = $match;
						unset( $tax_rate['tax_rate_order'] );
						WC_Tax::_update_tax_rate( $tax_rate_id, $tax_rate );
						++$updated;
					} else {
						$tax_rate_id = WC_Tax::_insert_tax_rate( $tax_rate );
						if ( ! $tax_rate_id ) {
							++$skipped;
							$row = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );
							continue;
						}
						++$imported;
						if ( $this->update_existing ) {
							$existing_rates[ $key ] = $tax_rate_id;
						}
						WC_Tax::_update_tax_rate_postcodes( $tax_rate_id, $postcode );
						WC_Tax::_update_tax_rate_cities( $tax_rate_id, $city );
					}

					$row = fgetcsv( $handle, 0, $this->delimiter, '"', '\\' );
				}
			} else {
				$this->import_error( __( 'The CSV is invalid.', 'woocommerce' ) );
			}

			fclose( $handle );
		}

		// Show Result.
		echo '<div class="updated settings-error"><p>';
		if ( $this->update_existing ) {
			printf(
				/* translators: 1: Imported tax rates count, 2: Updated tax rates count. */
				esc_html__( 'Import complete - imported %1$s and updated %2$s tax rates.', 'woocommerce' ),
				'<strong>' . absint( $imported ) . '</strong>',
				'<strong>' . absint( $updated ) . '</strong>'
			);
		} else {
			printf(
				/* translators: %s: tax rates count */
				esc_html__( 'Import complete - imported %s tax rates.', 'woocommerce' ),
				'<strong>' . absint( $imported ) . '</strong>'
			);
		}
		echo '</p></div>';
		if ( $skipped ) {
			echo '<div class="notice notice-warning"><p>';
			printf(
				/* translators: %s: Skipped CSV rows count. */
				esc_html__( 'Skipped %s rows because they are invalid, could not be saved, or match multiple existing tax rates. Review these rows before importing again.', 'woocommerce' ),
				'<strong>' . absint( $skipped ) . '</strong>'
			);
			echo '</p></div>';
		}

		$this->import_end();
	}

	/**
	 * Index existing rates by their location, priority, and class.
	 *
	 * @return array<string, int|false> Matching IDs, or false for ambiguous matches.
	 */
	private function get_existing_tax_rates(): array {
		global $wpdb;

		$rates     = $wpdb->get_results( "SELECT tax_rate_id, tax_rate_country, tax_rate_state, tax_rate_priority, tax_rate_class FROM {$wpdb->prefix}woocommerce_tax_rates", ARRAY_A );
		$locations = $wpdb->get_results( "SELECT tax_rate_id, location_type, location_code FROM {$wpdb->prefix}woocommerce_tax_rate_locations", ARRAY_A );
		$by_id     = array();
		$matches   = array();
		if ( null === $rates || null === $locations ) {
			$this->import_error( __( 'Unable to read existing tax rates. Please try again.', 'woocommerce' ) );
		}

		foreach ( $locations as $location ) {
			$by_id[ $location['tax_rate_id'] ][ $location['location_type'] ][] = $location['location_code'];
		}
		foreach ( $rates as $rate ) {
			$rate_locations  = $by_id[ $rate['tax_rate_id'] ] ?? array();
			$key             = self::get_tax_rate_key( $rate, $rate_locations['postcode'] ?? array(), $rate_locations['city'] ?? array() );
			$matches[ $key ] = array_key_exists( $key, $matches ) ? false : (int) $rate['tax_rate_id'];
		}

		return $matches;
	}

	/**
	 * Build an exact match key using the same normalization as WC_Tax writes.
	 *
	 * @param array $rate Tax rate data.
	 * @param array $postcodes Postcode restrictions.
	 * @param array $cities City restrictions.
	 * @return string
	 */
	private static function get_tax_rate_key( array $rate, array $postcodes, array $cities ): string {
		$country = strtoupper( $rate['tax_rate_country'] );
		$state   = strtoupper( sanitize_key( $rate['tax_rate_state'] ) );

		return (string) wp_json_encode(
			array(
				'*' === $country ? '' : $country,
				'*' === $state ? '' : $state,
				absint( $rate['tax_rate_priority'] ),
				WC_Tax::format_tax_rate_class( $rate['tax_rate_class'] ),
				self::normalize_locations( $postcodes, true ),
				self::normalize_locations( $cities, false ),
			)
		);
	}

	/**
	 * Normalize location sets without expanding wildcards or postcode ranges.
	 *
	 * @param array $locations Location restrictions.
	 * @param bool  $postcodes Whether these are postcode restrictions.
	 * @return array
	 */
	private static function normalize_locations( array $locations, bool $postcodes ): array {
		foreach ( $locations as $key => $location ) {
			if ( $postcodes ) {
				$location = str_replace( chr( 226 ) . chr( 128 ) . chr( 166 ), '...', $location );
			}
			$locations[ $key ] = strtoupper( trim( $location ) );
		}
		$locations = array_values( array_unique( array_diff( array_filter( $locations ), array( '*' ) ) ) );
		sort( $locations, SORT_STRING );

		return $locations;
	}

	/**
	 * Reject CSV values that could unintentionally zero rates or change their class.
	 * Rates must fit the eight-character database column after rounding.
	 *
	 * @param array $rate Tax rate data.
	 * @param array $tax_classes Allowed tax class slugs.
	 * @return bool
	 */
	private static function is_valid_tax_rate( array $rate, array $tax_classes ): bool {
		return is_numeric( $rate['tax_rate'] )
			&& is_finite( (float) $rate['tax_rate'] )
			&& (float) $rate['tax_rate'] >= 0
			&& strlen( number_format( (float) $rate['tax_rate'], 4, '.', '' ) ) <= 8
			&& ctype_digit( $rate['tax_rate_priority'] )
			&& in_array( sanitize_title( $rate['tax_rate_class'] ), $tax_classes, true );
	}

	/**
	 * Performs post-import cleanup of files and the cache.
	 */
	public function import_end() {
		echo '<p>' . esc_html__( 'All done!', 'woocommerce' ) . ' <a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=tax' ) ) . '">' . esc_html__( 'View tax rates', 'woocommerce' ) . '</a></p>';

		do_action( 'import_end' );
	}

	/**
	 * Set the import error message.
	 *
	 * @param string $message Error message.
	 */
	protected function set_import_error_message( $message ) {
		$this->import_error_message = $message;
	}

	/**
	 * Handles the CSV upload and initial parsing of the file to prepare for.
	 * displaying author import options.
	 *
	 * @return bool False if error uploading or invalid file, true otherwise
	 */
	public function handle_upload() {
		$file_url = isset( $_POST['file_url'] ) ? wc_clean( wp_unslash( $_POST['file_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in WC_Tax_Rate_Importer::dispatch()

		if ( empty( $file_url ) ) {
			$file = wp_import_handle_upload();

			if ( isset( $file['error'] ) ) {
				$this->set_import_error_message( $file['error'] );

				return false;
			}

			if ( ! wc_is_file_valid_csv( $file['file'], false ) ) {
				// Remove file if not valid.
				wp_delete_attachment( $file['id'], true );

				$this->set_import_error_message( __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );

				return false;
			}

			$this->id = absint( $file['id'] );
		} elseif (
			( 0 === stripos( realpath( ABSPATH . $file_url ), ABSPATH ) ) &&
			file_exists( ABSPATH . $file_url )
		) {
			if ( ! wc_is_file_valid_csv( ABSPATH . $file_url ) ) {
				$this->set_import_error_message( __( 'Invalid file type. The importer supports CSV and TXT file formats.', 'woocommerce' ) );

				return false;
			}

			$this->file_url = esc_attr( $file_url );
		} else {
			return false;
		}

		return true;
	}

	/**
	 * Output header html.
	 */
	public function header() {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Import tax rates', 'woocommerce' ) . '</h1>';
	}

	/**
	 * Output footer html.
	 */
	public function footer() {
		echo '</div>';
	}

	/**
	 * Output information about the uploading process.
	 */
	public function greet() {

		echo '<div class="narrow">';
		echo '<p>' . esc_html__( 'Hi there! Upload a CSV file containing tax rates to import the contents into your shop. Choose a .csv file to upload, then click "Upload file and import".', 'woocommerce' ) . '</p>';

		/* translators: 1: Link to tax rates sample file 2: Closing link. */
		echo '<p>' . sprintf( esc_html__( 'Your CSV needs to include columns in a specific order. %1$sClick here to download a sample%2$s.', 'woocommerce' ), '<a href="' . esc_url( WC()->plugin_url() ) . '/sample-data/sample_tax_rates.csv">', '</a>' ) . '</p>';

		$action = 'admin.php?import=woocommerce_tax_rate_csv&step=1';

		$bytes      = apply_filters( 'import_upload_size_limit', wp_max_upload_size() );
		$size       = size_format( $bytes );
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) :
			?>
			<div class="error">
				<p><?php esc_html_e( 'Before you can upload your import file, you will need to fix the following error:', 'woocommerce' ); ?></p>
				<p><strong><?php echo esc_html( $upload_dir['error'] ); ?></strong></p>
			</div>
		<?php else : ?>
			<form enctype="multipart/form-data" id="import-upload-form" method="post" action="<?php echo esc_attr( wp_nonce_url( $action, 'import-upload' ) ); ?>">
				<table class="form-table">
					<tbody>
						<tr>
							<th>
								<label for="upload"><?php esc_html_e( 'Choose a file from your computer:', 'woocommerce' ); ?></label>
							</th>
							<td>
								<input type="file" id="upload" name="import" size="25" />
								<input type="hidden" name="action" value="save" />
								<input type="hidden" name="max_file_size" value="<?php echo absint( $bytes ); ?>" />
								<small>
									<?php
									printf(
										/* translators: %s: maximum upload size */
										esc_html__( 'Maximum size: %s', 'woocommerce' ),
										esc_attr( $size )
									);
									?>
								</small>
							</td>
						</tr>
						<tr>
							<th>
								<label for="file_url"><?php esc_html_e( 'OR enter path to file:', 'woocommerce' ); ?></label>
							</th>
							<td>
								<?php echo ' ' . esc_html( ABSPATH ) . ' '; ?><input type="text" id="file_url" name="file_url" size="25" />
							</td>
						</tr>
						<tr>
							<th><label><?php esc_html_e( 'Delimiter', 'woocommerce' ); ?></label><br/></th>
							<td><input type="text" name="delimiter" placeholder="," size="2" /></td>
						</tr>
						<tr>
							<th><label for="update_existing"><?php esc_html_e( 'Update existing tax rates', 'woocommerce' ); ?></label></th>
							<td>
								<input type="checkbox" name="update_existing" id="update_existing" value="1" aria-describedby="update-existing-description" />
								<p class="description" id="update-existing-description"><?php esc_html_e( 'Update rates with the same country, state, ZIP/postcodes, cities, priority, and tax class. Rates that do not match will be added. Rows matching multiple rates will be skipped.', 'woocommerce' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<button type="submit" class="button" value="<?php esc_attr_e( 'Upload file and import', 'woocommerce' ); ?>"><?php esc_html_e( 'Upload file and import', 'woocommerce' ); ?></button>
				</p>
			</form>
			<?php
		endif;

		echo '</div>';
	}

	/**
	 * Show import error and quit.
	 *
	 * @param  string $message Error message.
	 * @return never
	 */
	private function import_error( $message = '' ) {
		echo '<p><strong>' . esc_html__( 'Sorry, there has been an error.', 'woocommerce' ) . '</strong><br />';
		if ( $message ) {
			echo esc_html( $message );
		}
		echo '</p>';
		$this->footer();
		die();
	}

	/**
	 * Added to http_request_timeout filter to force timeout at 60 seconds during import.
	 *
	 * @param  int $val Value.
	 * @return int 60
	 */
	public function bump_request_timeout( $val ) {
		return 60;
	}
}
