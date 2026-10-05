/**
 * External dependencies
 */
import { WC_API_PATH } from '@woocommerce/e2e-utils-playwright';

/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures/fixtures';
import { ADMIN_STATE_PATH } from '../../playwright.config';

test.describe( 'WooCommerce Tax Settings > enable', () => {
	test.use( { storageState: ADMIN_STATE_PATH } );

	// Tax calculation is enabled globally in site setup, so disable it first to
	// make the UI toggle an actual change — otherwise the checkbox is already
	// checked, the form is unchanged, and the React "Save changes" button stays
	// disabled. Restore the baseline (enabled) afterwards so the shared site
	// doesn't drift off-baseline for concurrent workers.
	test.beforeEach( async ( { restApi } ) => {
		await restApi.put(
			`${ WC_API_PATH }/settings/general/woocommerce_calc_taxes`,
			{ value: 'no' }
		);
	} );

	test.afterEach( async ( { restApi } ) => {
		await restApi.put(
			`${ WC_API_PATH }/settings/general/woocommerce_calc_taxes`,
			{ value: 'yes' }
		);
	} );

	test( 'can enable tax calculation', async ( { page } ) => {
		await page.goto( 'wp-admin/admin.php?page=wc-settings&tab=general' );

		// Make sure the general tab is active
		await expect( page.locator( 'a.nav-tab-active' ) ).toContainText(
			'General'
		);

		// Enable tax calculation
		await page.locator( '#woocommerce_calc_taxes' ).check();
		await page.locator( 'text=Save changes' ).click();

		// Verify that settings have been saved
		await expect( page.locator( 'div.updated.inline' ) ).toContainText(
			'Your settings have been saved.'
		);
		await expect( page.locator( '#woocommerce_calc_taxes' ) ).toBeChecked();

		// Verify that tax settings are now present
		await expect(
			page.locator( 'a.nav-tab:has-text("Tax")' )
		).toBeVisible();
	} );
} );

test.describe.serial( 'WooCommerce Tax Settings', () => {
	test.use( { storageState: ADMIN_STATE_PATH } );

	test.beforeEach( async ( { restApi } ) => {
		await restApi.put(
			`${ WC_API_PATH }/settings/general/woocommerce_calc_taxes`,
			{
				value: 'yes',
			}
		);
	} );

	test.afterEach( async ( { restApi } ) => {
		// Restore the global baseline (tax calculation enabled in site setup)
		// rather than leaving it off, which would disable tax for concurrent
		// workers and drift the shared site off-baseline.
		await restApi.put(
			`${ WC_API_PATH }/settings/general/woocommerce_calc_taxes`,
			{
				value: 'yes',
			}
		);
	} );

	test( 'can set tax options', async ( { page } ) => {
		await page.goto( 'wp-admin/admin.php?page=wc-settings&tab=tax', {} );

		// Make sure we're on the tax tab
		await expect( page.locator( 'a.nav-tab-active' ) ).toContainText(
			'Tax'
		);

		// Prices exclusive of tax
		await page
			.locator( 'text=No, I will enter prices exclusive of tax' )
			.check();
		// Tax based on customer shipping address
		await page
			.locator( '#woocommerce_tax_based_on' )
			.selectOption( 'shipping' );
		// Standard tax class for shipping
		await page.locator( '#woocommerce_shipping_tax_class' ).selectOption( {
			label: 'Standard',
		} );
		// Leave rounding unchecked
		// Display prices excluding tax
		await page
			.locator( '#woocommerce_tax_display_shop' )
			.selectOption( 'excl' );
		// Display prices including tax in cart and at checkout
		await page
			.locator( '#woocommerce_tax_display_cart' )
			.selectOption( 'incl' );
		// Display a single tax total
		await page
			.locator( '#woocommerce_tax_total_display' )
			.selectOption( 'single' );
		await page.locator( 'text=Save changes' ).click();

		// Verify that settings have been saved
		await expect( page.locator( 'div.updated.inline' ) ).toContainText(
			'Your settings have been saved.'
		);
		await expect(
			page.locator( 'text=No, I will enter prices exclusive of tax' )
		).toBeChecked();
		await expect( page.locator( '#woocommerce_tax_based_on' ) ).toHaveValue(
			'shipping'
		);
		await expect(
			page.locator( '#woocommerce_shipping_tax_class' )
		).toContainText( 'Standard' );
		await expect(
			page.locator( '#woocommerce_tax_display_shop' )
		).toHaveValue( 'excl' );
		await expect(
			page.locator( '#woocommerce_tax_display_cart' )
		).toHaveValue( 'incl' );
		await expect(
			page.locator( '#woocommerce_tax_total_display' )
		).toHaveValue( 'single' );
	} );

	test( 'can add tax classes', async ( { page } ) => {
		await page.goto( 'wp-admin/admin.php?page=wc-settings&tab=tax', {} );

		await expect( page.locator( 'a.nav-tab-active' ) ).toContainText(
			'Tax'
		);

		// Add a "fancy" tax class
		await page.locator( '#woocommerce_tax_classes' ).fill( 'Fancy' );
		await page.locator( 'text=Save changes' ).click();

		// Verify that the settings have been saved
		await expect( page.locator( 'div.updated.inline' ) ).toContainText(
			'Your settings have been saved.'
		);
		await expect(
			page.locator( 'ul.subsubsub > li > a >> nth=2' )
		).toContainText( 'Fancy rates' );
	} );

	test( 'can set rate settings', async ( { page } ) => {
		await page.goto(
			'wp-admin/admin.php?page=wc-settings&tab=tax&section=fancy'
		);

		// Make sure the tax tab is active, with the "fancy" subsection
		await expect( page.locator( 'a.nav-tab-active' ) ).toContainText(
			'Tax'
		);
		await expect(
			page.locator( 'ul.subsubsub > li > a.current' )
		).toContainText( 'Fancy rates' );

		// Create a state tax
		await page.locator( '.wc_tax_rates a.insert' ).click();
		await page
			.locator( 'input[name^="tax_rate_country[new-0"]' )
			.fill( 'US' );
		await page
			.locator( 'input[name^="tax_rate_state[new-0"]' )
			.fill( 'CA' );
		await page.locator( 'input[name^="tax_rate[new-0"]' ).fill( '7.5' );
		await page
			.locator( 'input[name^="tax_rate_name[new-0"]' )
			.fill( 'CA State Tax' );

		// Create a federal tax
		await page.locator( '.wc_tax_rates a.insert' ).click();
		await page
			.locator( 'input[name^="tax_rate_country[new-1"]' )
			.fill( 'US' );
		await page.locator( 'input[name^="tax_rate[new-1"]' ).fill( '1.5' );
		await page
			.locator( 'input[name^="tax_rate_priority[new-1"]' )
			.fill( '2' );
		await page
			.locator( 'input[name^="tax_rate_name[new-1"]' )
			.fill( 'Federal Tax' );
		await page.locator( 'input[name^="tax_rate_shipping[new-1"]' ).click();

		// Save changes
		await page.locator( 'text=Save changes' ).click();
		await expect( page.locator( '.blockOverlay' ) ).toBeHidden();

		// Verity that there are 2 rates
		await expect( page.locator( '#rates tr' ) ).toHaveCount( 2 );

		// Delete federal rate
		await page
			.locator( '#rates tr' )
			.filter( { has: page.locator( 'input[value="Federal Tax"]' ) } )
			.locator( '.wc-tax-select-rate' )
			.check();
		await page
			.getByRole( 'link', { name: 'Remove selected row(s)' } )
			.click();

		// Save changes
		await page.locator( 'text=Save changes' ).click();
		await expect( page.locator( '.blockOverlay' ) ).toBeHidden();

		// Verify that there is 1 tax rate left
		await expect( page.locator( '#rates tr' ) ).toHaveCount( 1 );
		await expect(
			page.locator(
				'#rates tr:first-of-type input[name^="tax_rate_state"][value="CA"]'
			)
		).toBeVisible();

		// Delete State tax
		await page
			.locator( '#rates tr' )
			.filter( { has: page.locator( 'input[value="CA State Tax"]' ) } )
			.locator( '.wc-tax-select-rate' )
			.check();
		await page.locator( '.wc_tax_rates a.remove_tax_rates' ).click();
		await page.locator( 'text=Save changes' ).click();
		await expect( page.locator( '.blockOverlay' ) ).toBeHidden();
	} );

	test( 'can remove tax classes', async ( { page } ) => {
		await page.goto( 'wp-admin/admin.php?page=wc-settings&tab=tax' );
		await expect( page.locator( 'a.nav-tab-active' ) ).toContainText(
			'Tax'
		);

		// Remove "Fancy" tax class
		await page.locator( '#woocommerce_tax_classes' ).fill( '' );
		await page.locator( 'text=Save changes' ).click();
		await expect( page.locator( '.blockOverlay' ) ).toBeHidden();

		// Verify that settings have been saved
		await expect( page.locator( 'div.updated.inline' ) ).toContainText(
			'Your settings have been saved.'
		);
		await expect( page.locator( '#woocommerce_tax_classes' ) ).toHaveValue(
			''
		);
		await expect(
			page.locator( 'ul.subsubsub > li > a:has-text("Fancy rates")' )
		).toHaveCount( 0 );
	} );
} );

test.describe( 'WooCommerce tax rate bulk editing', () => {
	test.use( { storageState: ADMIN_STATE_PATH } );

	test( 'can select across pages and bulk edit ZIP codes without changing other rates', async ( {
		page,
		restApi,
	} ) => {
		const { data: taxClass } = await restApi.post< { slug: string } >(
			`${ WC_API_PATH }/taxes/classes`,
			{ name: `Bulk editing ${ Date.now() }` }
		);
		const ids: number[] = [];
		let previousPageSize = '100';
		const url = `wp-admin/admin.php?page=wc-settings&tab=tax&section=${ taxClass.slug }`;
		const save = async () => {
			const response = page.waitForResponse( ( result ) =>
				result
					.url()
					.includes( 'action=woocommerce_tax_rates_save_changes' )
			);
			await page
				.getByRole( 'button', { name: 'Save changes', exact: true } )
				.click();
			expect( ( await ( await response ).json() ).success ).toBe( true );
			await expect( page.locator( '.blockOverlay' ) ).toBeHidden();
		};
		try {
			for ( const postcode of [ '00501', '00502', '00503' ] ) {
				const { data: rate } = await restApi.post< { id: number } >(
					`${ WC_API_PATH }/taxes`,
					{
						country: 'US',
						state: 'NY',
						postcodes: postcode,
						rate: '6',
						name: 'Bulk demo',
						class: taxClass.slug,
					}
				);
				ids.push( rate.id );
			}
			await page.goto( url );
			await page
				.getByRole( 'button', { name: 'Screen options', exact: true } )
				.click();
			const perPageInput = page.locator(
				'#woocommerce_tax_rates_per_page'
			);
			previousPageSize = await perPageInput.inputValue();
			await perPageInput.fill( '1' );
			await page.locator( '#screen-options-apply' ).click();
			await expect( page.locator( '#rates tr[data-id]' ) ).toHaveCount(
				1
			);
			await page.reload();
			await expect( page.locator( '#rates tr[data-id]' ) ).toHaveCount(
				1
			);
			const checkbox = page.locator( '.wc-tax-select-rate' );
			await checkbox.press( 'Space' );
			await expect( checkbox ).toBeChecked();
			await page.locator( '#wc-tax-bulk-action' ).selectOption( 'edit' );
			await page.locator( '#wc-tax-apply-bulk-action' ).click();
			await page
				.locator( '[data-bulk-attribute="postcode"]' )
				.fill( '00501; 00504' );
			await page.locator( '#wc-tax-update-selected' ).click();
			const saveUrl = '**/*action=woocommerce_tax_rates_save_changes*';
			await page.route(
				saveUrl,
				( route ) =>
					route.fulfill( {
						json: { success: false, data: 'invalid_changes' },
					} ),
				{ times: 1 }
			);
			const errorDialog = page.waitForEvent( 'dialog' );
			await page
				.getByRole( 'button', { name: 'Save changes', exact: true } )
				.click();
			const dialog = await errorDialog;
			expect( dialog.message() ).toContain(
				'Your changes are still available'
			);
			await dialog.accept();
			await expect( page.locator( '.blockOverlay' ) ).toBeHidden();
			await expect(
				page.locator( '#rates [data-attribute="postcode"]' )
			).toHaveValue( '00501; 00504' );
			await save();
			const { data: rates } = await restApi.get<
				{ id: number; postcodes: string[] }[]
			>(
				`${ WC_API_PATH }/taxes?class=${ taxClass.slug }&orderby=order`
			);
			expect( rates[ 0 ].postcodes ).toEqual( [ '00501', '00504' ] );
			expect( rates[ 1 ].postcodes ).toEqual( [ '00502' ] );
			expect( rates[ 2 ].postcodes ).toEqual( [ '00503' ] );
			expect( rates.map( ( rate ) => rate.id ) ).toEqual( ids );
			await page.locator( '#wc-tax-select-page' ).check();
			await expect(
				page.locator( '#wc-tax-selection-status' )
			).toHaveText( 'Selected tax rates: 1' );
			await page.locator( '#wc-tax-select-all' ).click();
			await expect(
				page.locator( '#wc-tax-selection-status' )
			).toHaveText( 'Selected tax rates: 3' );
			await page.locator( '#wc-tax-bulk-action' ).selectOption( 'edit' );
			await page.locator( '#wc-tax-apply-bulk-action' ).click();
			await page
				.locator( '[data-bulk-attribute="tax_rate_name"]' )
				.fill( 'Updated bulk demo' );
			await page
				.locator( '[data-bulk-attribute="tax_rate"]' )
				.fill( '6.5' );
			await page.locator( '#wc-tax-update-selected' ).click();
			await save();
			const { data: updated } = await restApi.get<
				{ id: number; name: string; rate: string }[]
			>(
				`${ WC_API_PATH }/taxes?class=${ taxClass.slug }&orderby=order`
			);
			expect( updated.map( ( rate ) => rate.id ) ).toEqual( ids );
			expect(
				updated.every(
					( rate ) =>
						rate.name === 'Updated bulk demo' &&
						Number( rate.rate ) === 6.5
				)
			).toBe( true );
			await page.locator( '#wc-tax-select-all' ).click();
			await page
				.locator( '#wc-tax-bulk-action' )
				.selectOption( 'remove' );
			await page.locator( '#wc-tax-apply-bulk-action' ).click();
			await save();
			await page.reload();
			await expect( page.locator( '#rates tr[data-id]' ) ).toHaveCount(
				0
			);
		} finally {
			await page.goto( url );
			await page
				.getByRole( 'button', { name: 'Screen options', exact: true } )
				.click();
			await page
				.locator( '#woocommerce_tax_rates_per_page' )
				.fill( previousPageSize );
			await page.locator( '#screen-options-apply' ).click();
			const { data: remaining } = await restApi.get< { id: number }[] >(
				`${ WC_API_PATH }/taxes?class=${ taxClass.slug }`
			);
			for ( const rate of remaining ) {
				await restApi.delete( `${ WC_API_PATH }/taxes/${ rate.id }`, {
					force: true,
				} );
			}
			await restApi.delete(
				`${ WC_API_PATH }/taxes/classes/${ taxClass.slug }`,
				{ force: true }
			);
		}
	} );
} );
