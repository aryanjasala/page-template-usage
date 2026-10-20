const { test, expect, wp, wpEval } = require( './utils' );

// Twenty Twenty-Five (set in global setup) offers one page template: "Page No Title" (page-no-title).
const NO_TITLE = 'page-no-title';
const GONE = 'templates/retired-landing.php';

test.describe( 'Template Usage Column', () => {
	const ids = {};

	test.beforeAll( () => {
		const create = ( title, template, status = 'publish' ) => {
			const id = wp( 'post', 'create', '--post_type=page', `--post_status=${ status }`, `--post_title=${ title }`, '--porcelain' );
			if ( template ) {
				wp( 'post', 'meta', 'update', id, '_wp_page_template', template );
			}
			return Number( id );
		};

		ids.plain = create( 'TUC Plain page' );
		ids.noTitle = create( 'TUC No title page', NO_TITLE );
		ids.noTitleDraft = create( 'TUC No title draft', NO_TITLE, 'draft' );
		ids.gone = create( 'TUC Retired landing', GONE );
	} );

	test.afterAll( () => {
		wp( 'post', 'delete', ...Object.values( ids ).map( String ), '--force' );
	} );

	test( 'shows the template of every page, with missing templates flagged', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );

		const row = ( id ) => page.locator( `#post-${ id } .column-template_usage` );
		await expect( page.getByRole( 'columnheader', { name: /Template/ } ).first() ).toBeVisible();
		await expect( row( ids.plain ) ).toHaveText( 'Default' );
		await expect( row( ids.noTitle ) ).toHaveText( 'Page No Title' );
		await expect( row( ids.gone ) ).toContainText( 'Missing template' );
		await expect( row( ids.gone ).locator( '.tuc-missing' ) ).toHaveCSS( 'color', 'rgb(179, 45, 46)' );
		await expect( row( ids.gone ) ).toContainText( GONE );
		await expect( page.locator( '.tuc-missing-notice' ) ).toContainText( '1 page uses a template the theme no longer has' );
		await expect( page.locator( '.tuc-missing-notice' ).getByRole( 'link', { name: 'Fix in Template Usage' } ) ).toHaveAttribute( 'href', /page=template-usage/ );
	} );

	test( 'filters the list by template, with counts in the dropdown', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );

		const options = await page.locator( '#filter-by-template option' ).allTextContents();
		expect( options ).toContain( 'Page No Title (2)' );
		expect( options ).toContain( 'Missing template (1)' );

		await page.locator( '#filter-by-template' ).selectOption( NO_TITLE );
		await page.locator( '#post-query-submit' ).click();
		await expect( page.locator( '#the-list .row-title' ) ).toHaveText( [ 'TUC No title draft', 'TUC No title page' ] );

		await page.goto( '/wp-admin/edit.php?post_type=page&template_usage=__missing' );
		await expect( page.locator( '#the-list .row-title' ) ).toHaveText( [ 'TUC Retired landing' ] );
	} );

	test( 'clicking the column heading sorts by template and keeps every page', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );
		const total = await page.locator( '#the-list tr' ).count();

		await page.locator( 'th#template_usage a' ).click();
		await expect( page ).toHaveURL( /orderby=template_usage/ );
		await expect( page.locator( '#the-list tr' ) ).toHaveCount( total );

		const cells = await page.locator( '#the-list .column-template_usage' ).allInnerTexts();
		const firstNoTitle = cells.findIndex( ( text ) => text.includes( 'Page No Title' ) );
		const lastDefault = cells.map( ( text ) => text.trim() ).lastIndexOf( 'Default' );
		expect( firstNoTitle ).toBeGreaterThan( lastDefault );
	} );

	test( 'quick edit keeps the column after saving', async ( { page } ) => {
		await page.goto( '/wp-admin/edit.php?post_type=page' );

		const row = page.locator( `#post-${ ids.plain }` );
		await row.hover();
		await row.locator( 'button.editinline' ).click();
		await page.locator( `#edit-${ ids.plain } select[name="page_template"]` ).selectOption( NO_TITLE );
		await page.locator( `#edit-${ ids.plain } .save` ).click();

		await expect( page.locator( `#post-${ ids.plain } .column-template_usage` ) ).toHaveText( 'Page No Title' );
		wp( 'post', 'meta', 'update', String( ids.plain ), '_wp_page_template', 'default' );
	} );

	test( 'report lists used, unused and missing templates and moves pages off a missing one', async ( { page } ) => {
		await page.goto( '/wp-admin/themes.php?page=template-usage' );

		const section = page.locator( '.tuc-report__section', { has: page.getByRole( 'heading', { name: 'Pages' } ) } );
		await expect( section.locator( `tr[data-template="${ NO_TITLE }"] .tuc-badge` ) ).toHaveText( 'In use' );
		await expect( section.locator( `tr[data-template="${ GONE }"] .tuc-badge` ) ).toHaveText( 'Missing' );
		await expect( section.locator( '.tuc-stats li.is-error' ) ).toContainText( '1' );

		const missingRow = section.locator( `tr[data-template="${ GONE }"]` );
		await missingRow.locator( 'select[name="to"]' ).selectOption( NO_TITLE );
		await missingRow.getByRole( 'button', { name: 'Move' } ).click();

		await expect( page.locator( '.notice-success' ) ).toContainText( 'Moved 1 item to "Page No Title"' );
		await expect( page.locator( `tr[data-template="${ GONE }"]` ) ).toHaveCount( 0 );
		expect( wp( 'post', 'meta', 'get', String( ids.gone ), '_wp_page_template' ) ).toBe( NO_TITLE );
	} );

	test( 'one click moves at most 500 pages, and says how many are left', async ( { page } ) => {
		test.setTimeout( 180_000 );
		const ids = JSON.parse(
			wpEval( `
				global $wpdb;
				$now = current_time( 'mysql', true );
				$ids = array();
				for ( $batch = 0; $batch < 4; $batch++ ) {
					$rows = array();
					for ( $i = 0; $i < 155; $i++ ) {
						$rows[] = $wpdb->prepare( "( %s, %s, %s, 'publish', 'page', '', '', '', '' )", 'Bulk landing ' . ( $batch * 155 + $i ), $now, $now );
					}
					$wpdb->query( "INSERT INTO {$wpdb->posts} ( post_title, post_date, post_date_gmt, post_status, post_type, post_content, post_excerpt, to_ping, pinged ) VALUES " . implode( ',', $rows ) );
					$first = (int) $wpdb->insert_id;
					$meta  = array();
					for ( $i = 0; $i < 155; $i++ ) {
						$ids[]  = $first + $i;
						$meta[] = $wpdb->prepare( '( %d, %s, %s )', $first + $i, '_wp_page_template', 'templates/bulk-gone.php' );
					}
					$wpdb->query( "INSERT INTO {$wpdb->postmeta} ( post_id, meta_key, meta_value ) VALUES " . implode( ',', $meta ) );
				}
				wp_cache_flush();
				echo wp_json_encode( $ids );
			` )
		);

		await page.goto( '/wp-admin/themes.php?page=template-usage' );
		const section = page.locator( '.tuc-report__section', { has: page.getByRole( 'heading', { name: 'Pages' } ) } );
		const row = section.locator( 'tr[data-template="templates/bulk-gone.php"]' );
		await expect( row ).toContainText( '620' );
		await row.locator( 'select[name="to"]' ).selectOption( NO_TITLE );
		await row.getByRole( 'button', { name: 'Move' } ).click();
		await expect( page.locator( '.notice-success' ) ).toContainText( 'Moved 500 items to "Page No Title".' );
		await expect( page.locator( '.notice-success' ) ).toContainText( '120 more are left.' );

		// The report updates at once: the counts cache is cleared when templates change.
		await expect( row ).toContainText( '120' );
		await row.locator( 'select[name="to"]' ).selectOption( NO_TITLE );
		await row.getByRole( 'button', { name: 'Move' } ).click();
		await expect( page.locator( '.notice-success' ) ).toContainText( 'Moved 120 items' );
		await expect( page.locator( '.notice-success' ) ).not.toContainText( 'left' );
		await expect( section.locator( 'tr[data-template="templates/bulk-gone.php"]' ) ).toHaveCount( 0 );

		wpEval( `foreach ( ${ JSON.stringify( ids ) } as $id ) { wp_delete_post( $id, true ); }` );
	} );

	test( 'abilities: an agent can read the report and move items off a missing template', async ( { rest } ) => {
		wp( 'post', 'meta', 'update', String( ids.gone ), '_wp_page_template', GONE );
		const path = ( name ) => `/wp-abilities/v1/abilities/template-usage-column/${ name }/run`;

		const listed = await rest( 'GET', '/wp-abilities/v1/abilities?category=template-usage-column' );
		expect( listed.body.map( ( ability ) => ability.name ).sort() ).toEqual( [ 'template-usage-column/get-report', 'template-usage-column/reassign' ] );
		// MCP clients only list abilities marked public.
		expect( JSON.parse( wpEval( `echo wp_json_encode( array_values( array_filter( array_map( function ( $ability ) { $meta = $ability->get_meta(); return ( ! empty( $meta['public'] ) && ! empty( $meta['mcp']['public'] ) ) ? '' : $ability->get_name(); }, wp_get_abilities() ), function ( $name ) { return 0 === strpos( $name, 'template-usage-column/' ); } ) ) );` ) ) ).toEqual( [] );

		const report = await rest( 'GET', path( 'get-report' ) );
		expect( report.status ).toBe( 200 );
		expect( report.body ).toMatchObject( { post_type: 'page', missing: 1 } );
		expect( report.body.templates.find( ( row ) => row.slug === GONE ) ).toMatchObject( { status: 'missing', count: 1 } );

		const moved = await rest( 'POST', path( 'reassign' ), { input: { from: 'missing', to: NO_TITLE } } );
		expect( moved.status ).toBe( 200 );
		expect( moved.body ).toEqual( { moved: 1, skipped: 0, remaining: 0 } );
		expect( wp( 'post', 'meta', 'get', String( ids.gone ), '_wp_page_template' ) ).toBe( NO_TITLE );
		expect( ( await rest( 'GET', path( 'get-report' ) ) ).body.missing ).toBe( 0 );

		const unknown = await rest( 'GET', `${ path( 'get-report' ) }?input[post_type]=nope` );
		expect( unknown.body.code ).toBe( 'template_usage_no_templates' );
	} );

	test( 'WP-CLI lists usage and reassigns', () => {
		wp( 'post', 'meta', 'update', String( ids.gone ), '_wp_page_template', GONE );

		const missing = JSON.parse( wp( 'template-usage', 'list', '--post_type=page', '--status=missing', '--format=json' ) );
		expect( missing ).toEqual( [ expect.objectContaining( { slug: GONE, count: 1, status: 'missing' } ) ] );

		expect( wp( 'template-usage', 'reassign', 'missing', 'default' ) ).toContain( 'Moved 1 item(s)' );
		expect( wp( 'template-usage', 'list', '--status=missing', '--format=count' ) ).toBe( '0' );
	} );
} );
