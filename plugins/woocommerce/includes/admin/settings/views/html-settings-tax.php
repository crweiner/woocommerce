<?php
/**
 * Admin view: Settings tax
 *
 * @package WooCommerce\Admin\Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wc-tax-rates-search" id="rates-search">
	<input type="search" class="wc-tax-rates-search-field" placeholder="<?php esc_attr_e( 'Search&hellip;', 'woocommerce' ); ?>" value="<?php echo isset( $_GET['s'] ) ? esc_attr( $_GET['s'] ) : ''; ?>" />
</div>

<div id="rates-pagination"></div>

<h3>
	<?php
	/* translators: %s: tax rate */
	printf(
		__( '"%s" tax rates', 'woocommerce' ),
		$current_class ? esc_html( $current_class ) : __( 'Standard', 'woocommerce' )
	);
	?>
</h3>

<div class="tablenav wc-tax-bulk-actions">
	<label for="wc-tax-bulk-action" class="screen-reader-text"><?php esc_html_e( 'Bulk actions', 'woocommerce' ); ?></label>
	<select id="wc-tax-bulk-action">
		<option value=""><?php esc_html_e( 'Bulk actions', 'woocommerce' ); ?></option>
		<option value="edit"><?php esc_html_e( 'Edit', 'woocommerce' ); ?></option>
		<option value="remove"><?php esc_html_e( 'Remove', 'woocommerce' ); ?></option>
	</select>
	<button type="button" class="button" id="wc-tax-apply-bulk-action"><?php esc_html_e( 'Apply', 'woocommerce' ); ?></button>
	<button type="button" class="button" id="wc-tax-select-all"><?php esc_html_e( 'Select all matching rates', 'woocommerce' ); ?></button>
	<button type="button" class="button" id="wc-tax-clear-selection"><?php esc_html_e( 'Clear selection', 'woocommerce' ); ?></button>
	<span id="wc-tax-selection-status" role="status" aria-live="polite"></span>
</div>

<fieldset id="wc-tax-bulk-editor" hidden>
	<legend><?php esc_html_e( 'Bulk edit selected rates', 'woocommerce' ); ?></legend>
	<p id="wc-tax-bulk-help"><?php esc_html_e( 'Leave fields blank to keep their current values. Enter * to apply to all countries, states, postcodes, or cities. Changes are applied when you save.', 'woocommerce' ); ?></p>
	<div class="wc-tax-bulk-fields">
		<?php
		$bulk_fields = array(
			'tax_rate_country'  => __( 'Country code', 'woocommerce' ),
			'tax_rate_state'    => __( 'State code', 'woocommerce' ),
			'postcode'          => __( 'Postcode / ZIP', 'woocommerce' ),
			'city'              => __( 'City', 'woocommerce' ),
			'tax_rate'          => __( 'Rate %', 'woocommerce' ),
			'tax_rate_name'     => __( 'Tax name', 'woocommerce' ),
			'tax_rate_priority' => __( 'Priority', 'woocommerce' ),
		);
		foreach ( $bulk_fields as $attribute => $label ) :
			$input_type = in_array( $attribute, array( 'tax_rate', 'tax_rate_priority' ), true ) ? 'number' : 'text';
			$input_step = 'tax_rate' === $attribute ? '0.0001' : '1';
			$input_min  = 'tax_rate_priority' === $attribute ? '1' : '0';
			$input_max  = 'tax_rate' === $attribute ? '999.9999' : '';
			?>
			<label>
				<?php echo esc_html( $label ); ?>
				<input type="<?php echo esc_attr( $input_type ); ?>" data-bulk-attribute="<?php echo esc_attr( $attribute ); ?>" aria-describedby="wc-tax-bulk-help" autocomplete="off" max="<?php echo esc_attr( $input_max ); ?>" min="<?php echo esc_attr( $input_min ); ?>" step="<?php echo esc_attr( $input_step ); ?>" />
			</label>
		<?php endforeach; ?>
		<?php
		$bulk_flags = array(
			'tax_rate_compound' => __( 'Compound', 'woocommerce' ),
			'tax_rate_shipping' => __( 'Shipping', 'woocommerce' ),
		);
		foreach ( $bulk_flags as $attribute => $label ) :
			?>
			<label>
				<?php echo esc_html( $label ); ?>
				<select data-bulk-attribute="<?php echo esc_attr( $attribute ); ?>">
					<option value=""><?php esc_html_e( 'No change', 'woocommerce' ); ?></option>
					<option value="1"><?php esc_html_e( 'Yes', 'woocommerce' ); ?></option>
					<option value="0"><?php esc_html_e( 'No', 'woocommerce' ); ?></option>
				</select>
			</label>
		<?php endforeach; ?>
	</div>
	<p>
		<button type="button" class="button button-primary" id="wc-tax-update-selected"><?php esc_html_e( 'Update selected rates', 'woocommerce' ); ?></button>
		<button type="button" class="button" id="wc-tax-cancel-bulk-edit"><?php esc_html_e( 'Cancel', 'woocommerce' ); ?></button>
	</p>
</fieldset>

<table class="wc_tax_rates widefat">
	<thead>
		<tr>
			<td class="check-column"><input type="checkbox" id="wc-tax-select-page" aria-label="<?php esc_attr_e( 'Select all rates on this page', 'woocommerce' ); ?>" /></td>
			<th width="8%"><a href="https://en.wikipedia.org/wiki/ISO_3166-1#Current_codes" target="_blank"><?php _e( 'Country&nbsp;code', 'woocommerce' ); ?></a>&nbsp;<?php echo wc_help_tip( __( 'A 2 digit country code, e.g. US. Leave blank to apply to all.', 'woocommerce' ) ); ?></th>
			<th width="8%"><?php _e( 'State code', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'A 2 digit state code, e.g. AL. Leave blank to apply to all.', 'woocommerce' ) ); ?></th>
			<th><?php _e( 'Postcode / ZIP', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'Postcode for this rule. Semi-colon (;) separate multiple values. Leave blank to apply to all areas. Wildcards (*) and ranges for numeric postcodes (e.g. 12345...12350) can also be used.', 'woocommerce' ) ); ?></th>
			<th><?php _e( 'City', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'Cities for this rule. Semi-colon (;) separate multiple values. Leave blank to apply to all cities.', 'woocommerce' ) ); ?></th>
			<th width="8%"><?php _e( 'Rate&nbsp;%', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'Enter a tax rate (percentage) to 4 decimal places.', 'woocommerce' ) ); ?></th>
			<th width="8%"><?php _e( 'Tax name', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'Enter a name for this tax rate.', 'woocommerce' ) ); ?></th>
			<th width="8%"><?php _e( 'Priority', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'Choose a priority for this tax rate. Only 1 matching rate per priority will be used. To define multiple tax rates for a single area you need to specify a different priority per rate.', 'woocommerce' ) ); ?></th>
			<th width="8%"><?php _e( 'Compound', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'Choose whether or not this is a compound rate. Compound tax rates are applied on top of other tax rates.', 'woocommerce' ) ); ?></th>
			<th width="8%"><?php _e( 'Shipping', 'woocommerce' ); ?>&nbsp;<?php echo wc_help_tip( __( 'Choose whether or not this tax rate also gets applied to shipping.', 'woocommerce' ) ); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<th colspan="10">
				<a href="#" class="button plus insert"><?php _e( 'Insert row', 'woocommerce' ); ?></a>
				<a href="#" class="button minus remove_tax_rates"><?php _e( 'Remove selected row(s)', 'woocommerce' ); ?></a>
				<a href="#" download="tax_rates.csv" class="button export"><?php _e( 'Export CSV', 'woocommerce' ); ?></a>
				<a href="<?php echo admin_url( 'admin.php?import=woocommerce_tax_rate_csv' ); ?>" class="button import"><?php _e( 'Import CSV', 'woocommerce' ); ?></a>
			</th>
		</tr>
	</tfoot>
	<tbody id="rates">
		<tr>
			<th colspan="10" style="text-align: center;"><?php esc_html_e( 'Loading&hellip;', 'woocommerce' ); ?></th>
		</tr>
	</tbody>
</table>

<div id="rates-bottom-pagination"></div>

<script type="text/html" id="tmpl-wc-tax-table-row">
	<tr class="tips" data-tip="<?php printf( esc_attr__( 'Tax rate ID: %s', 'woocommerce' ), '{{ data.tax_rate_id }}' ); ?>" data-id="{{ data.tax_rate_id }}">
		<th scope="row" class="check-column">
			<input type="checkbox" class="wc-tax-select-rate" aria-label="<?php /* translators: %s: tax rate ID. */ printf( esc_attr__( 'Select tax rate %s', 'woocommerce' ), '{{ data.tax_rate_id }}' ); ?>" />
		</th>
		<td class="country">
			<input autocomplete="off" type="text" value="{{ data.tax_rate_country }}" placeholder="*" name="tax_rate_country[{{ data.tax_rate_id }}]" class="wc_input_country_iso" data-attribute="tax_rate_country" style="text-transform:uppercase" />
		</td>

		<td class="state">
			<input autocomplete="off" type="text" value="{{ data.tax_rate_state }}" placeholder="*" name="tax_rate_state[{{ data.tax_rate_id }}]" data-attribute="tax_rate_state" />
		</td>

		<td class="postcode">
			<input type="text" value="<# if ( data.postcode ) print( _.escape( data.postcode.join( '; ' ) ) ); #>" placeholder="*" data-name="tax_rate_postcode[{{ data.tax_rate_id }}]" data-attribute="postcode" />
		</td>

		<td class="city">
			<input type="text" value="<# if ( data.city ) print( _.escape( data.city.join( '; ' ) ) ); #>" placeholder="*" data-name="tax_rate_city[{{ data.tax_rate_id }}]" data-attribute="city" />
		</td>

		<td class="rate">
			<input autocomplete="off" type="text" value="{{ data.tax_rate }}" placeholder="0" name="tax_rate[{{ data.tax_rate_id }}]" data-attribute="tax_rate" />
		</td>

		<td class="name">
			<input autocomplete="off" type="text" value="{{ data.tax_rate_name }}" name="tax_rate_name[{{ data.tax_rate_id }}]" data-attribute="tax_rate_name" />
		</td>

		<td class="priority">
			<input type="number" step="1" min="1" value="{{ data.tax_rate_priority }}" name="tax_rate_priority[{{ data.tax_rate_id }}]" data-attribute="tax_rate_priority" />
		</td>

		<td class="compound">
			<input type="checkbox" class="checkbox" name="tax_rate_compound[{{ data.tax_rate_id }}]" <# if ( parseInt( data.tax_rate_compound, 10 ) ) { #> checked="checked" <# } #> data-attribute="tax_rate_compound" />
		</td>

		<td class="apply_to_shipping">
			<input type="checkbox" class="checkbox" name="tax_rate_shipping[{{ data.tax_rate_id }}]" <# if ( parseInt( data.tax_rate_shipping, 10 ) ) { #> checked="checked" <# } #> data-attribute="tax_rate_shipping" />
		</td>
	</tr>
</script>

<script type="text/html" id="tmpl-wc-tax-table-row-empty">
	<tr>
		<th colspan="10" style="text-align:center"><?php esc_html_e( 'No matching tax rates found.', 'woocommerce' ); ?></th>
	</tr>
</script>

<script type="text/html" id="tmpl-wc-tax-table-pagination">
	<div class="tablenav">
		<div class="tablenav-pages">
			<span class="displaying-num">
				<?php
				/* translators: %s: number */
				printf(
					__( '%s items', 'woocommerce' ), // %s will be a number eventually, but must be a string for now.
					'{{ data.qty_rates }}'
				);
				?>
			</span>
			<span class="pagination-links">
				<# if ( data.current_page === 1 ) { #>
					<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
					<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
				<# } else { #>
					<a class="button" data-goto="1">
						<span class="screen-reader-text"><?php esc_html_e( 'First page', 'woocommerce' ); ?></span>
						<span aria-hidden="true">&laquo;</span>
					</a>
					<a class="button" data-goto="<# print( Math.max( 1, parseInt( data.current_page, 10 ) - 1 ) ) #>">
						<span class="screen-reader-text"><?php esc_html_e( 'Previous page', 'woocommerce' ); ?></span>
						<span aria-hidden="true">&lsaquo;</span>
					</a>
				<# } #>

				<span class="paging-input">
					<label for="current-page-selector" class="screen-reader-text"><?php esc_html_e( 'Current page', 'woocommerce' ); ?></label>
					<?php
						/* translators: 1: current page 2: total pages */
						printf(
							esc_html_x( '%1$s of %2$s', 'Pagination', 'woocommerce' ),
							'<input class="current-page" id="current-page-selector" type="text" name="paged" value="{{ data.current_page }}" size="<# print( data.qty_pages.toString().length ) #>" aria-describedby="table-paging">',
							'<span class="total-pages">{{ data.qty_pages }}</span>'
						);
					?>
				</span>

				<# if ( data.current_page === data.qty_pages ) { #>
					<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
					<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
				<# } else { #>
					<a class="button" data-goto="<# print( Math.min( data.qty_pages, parseInt( data.current_page, 10 ) + 1 ) ) #>">
						<span class="screen-reader-text"><?php esc_html_e( 'Next page', 'woocommerce' ); ?></span>
						<span aria-hidden="true">&rsaquo;</span>
					</a>
					<a class="button" data-goto="{{ data.qty_pages }}">
						<span class="screen-reader-text"><?php esc_html_e( 'Last page', 'woocommerce' ); ?></span>
						<span aria-hidden="true">&raquo;</span>
					</a>
				<# } #>

			</span>
		</div>
	</div>
</script>
