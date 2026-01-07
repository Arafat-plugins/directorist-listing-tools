<?php
/**
 * Tests for Type Manager Functionality
 *
 * @package DirectoristListingTools
 */

/**
 * Type Manager Tests Class
 */
class Test_Type_Manager extends WP_UnitTestCase {

	/**
	 * Test setting listing type.
	 */
	public function test_set_listing_type() {
		// Create listing type taxonomy term.
		$taxonomy = dlt_get_listing_types_taxonomy();
		$term     = wp_insert_term( 'Test Type', $taxonomy );

		if ( is_wp_error( $term ) ) {
			$this->markTestSkipped( 'Could not create listing type term. Taxonomy may not exist.' );
			return;
		}

		$term_id = $term['term_id'];

		// Create test listing.
		$post_type = dlt_get_post_type();
		$listing   = $this->factory->post->create(
			array(
				'post_type' => $post_type,
				'post_title' => 'Test Listing',
			)
		);

		// Get type manager instance.
		$type_manager = Directorist_Listing_Tools_Type_Manager::get_instance();

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $type_manager );
		$method     = $reflection->getMethod( 'set_listing_type' );
		$method->setAccessible( true );

		// Set listing type.
		$results = $method->invoke( $type_manager, array( $listing ), $term_id );

		// Verify results.
		$this->assertCount( 1, $results['success'] );
		$this->assertEmpty( $results['failed'] );

		// Verify term is assigned.
		$terms = wp_get_object_terms( $listing, $taxonomy );
		$this->assertNotEmpty( $terms );
		$this->assertEquals( $term_id, $terms[0]->term_id );
	}

	/**
	 * Test setting listing type with invalid listing.
	 */
	public function test_set_listing_type_invalid_listing() {
		$taxonomy = dlt_get_listing_types_taxonomy();
		$term     = wp_insert_term( 'Test Type 2', $taxonomy );

		if ( is_wp_error( $term ) ) {
			$this->markTestSkipped( 'Could not create listing type term. Taxonomy may not exist.' );
			return;
		}

		$term_id = $term['term_id'];

		$type_manager = Directorist_Listing_Tools_Type_Manager::get_instance();

		$reflection = new ReflectionClass( $type_manager );
		$method     = $reflection->getMethod( 'set_listing_type' );
		$method->setAccessible( true );

		// Try to set type for non-existent listing.
		$results = $method->invoke( $type_manager, array( 99999 ), $term_id );

		// Verify failure.
		$this->assertEmpty( $results['success'] );
		$this->assertCount( 1, $results['failed'] );
	}
}

