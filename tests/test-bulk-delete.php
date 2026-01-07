<?php
/**
 * Tests for Bulk Delete Functionality
 *
 * @package DirectoristListingTools
 */

/**
 * Bulk Delete Tests Class
 */
class Test_Bulk_Delete extends WP_UnitTestCase {

	/**
	 * Test sanitize listing IDs function.
	 */
	public function test_sanitize_listing_ids() {
		// Test comma-separated string.
		$ids = dlt_sanitize_listing_ids( '123, 456, 789' );
		$this->assertEquals( array( 123, 456, 789 ), $ids );

		// Test with invalid values.
		$ids = dlt_sanitize_listing_ids( '123, abc, 456, -789' );
		$this->assertEquals( array( 123, 456 ), $ids );

		// Test empty string.
		$ids = dlt_sanitize_listing_ids( '' );
		$this->assertEmpty( $ids );

		// Test duplicates.
		$ids = dlt_sanitize_listing_ids( '123, 456, 123' );
		$this->assertEquals( array( 123, 456 ), $ids );
	}

	/**
	 * Test bulk delete with valid listings.
	 */
	public function test_bulk_delete_valid_listings() {
		// Create test listings.
		$post_type = dlt_get_post_type();
		$listing1  = $this->factory->post->create(
			array(
				'post_type' => $post_type,
				'post_title' => 'Test Listing 1',
			)
		);
		$listing2  = $this->factory->post->create(
			array(
				'post_type' => $post_type,
				'post_title' => 'Test Listing 2',
			)
		);

		// Get bulk delete instance.
		$bulk_delete = Directorist_Listing_Tools_Bulk_Delete::get_instance();

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $bulk_delete );
		$method     = $reflection->getMethod( 'delete_listings' );
		$method->setAccessible( true );

		// Delete listings.
		$results = $method->invoke( $bulk_delete, array( $listing1, $listing2 ) );

		// Verify results.
		$this->assertCount( 2, $results['success'] );
		$this->assertEmpty( $results['failed'] );

		// Verify posts are deleted.
		$this->assertNull( get_post( $listing1 ) );
		$this->assertNull( get_post( $listing2 ) );
	}

	/**
	 * Test bulk delete with invalid listing IDs.
	 */
	public function test_bulk_delete_invalid_listings() {
		$bulk_delete = Directorist_Listing_Tools_Bulk_Delete::get_instance();

		$reflection = new ReflectionClass( $bulk_delete );
		$method     = $reflection->getMethod( 'delete_listings' );
		$method->setAccessible( true );

		// Try to delete non-existent listings.
		$results = $method->invoke( $bulk_delete, array( 99999, 99998 ) );

		// Verify all failed.
		$this->assertEmpty( $results['success'] );
		$this->assertCount( 2, $results['failed'] );
	}
}

