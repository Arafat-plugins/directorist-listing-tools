<?php
/**
 * Tests for Pending Manager Functionality
 *
 * @package DirectoristListingTools
 */

/**
 * Pending Manager Tests Class
 */
class Test_Pending_Manager extends WP_UnitTestCase {

	/**
	 * Test publishing pending listings.
	 */
	public function test_publish_pending_listings() {
		// Create pending listings.
		$post_type = dlt_get_post_type();
		$listing1   = $this->factory->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'pending',
				'post_title'  => 'Pending Listing 1',
			)
		);
		$listing2   = $this->factory->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'pending',
				'post_title'  => 'Pending Listing 2',
			)
		);

		// Get pending manager instance.
		$pending_manager = Directorist_Listing_Tools_Pending_Manager::get_instance();

		// Use reflection to access private method.
		$reflection = new ReflectionClass( $pending_manager );
		$method     = $reflection->getMethod( 'publish_listings' );
		$method->setAccessible( true );

		// Publish listings.
		$results = $method->invoke( $pending_manager, array( $listing1, $listing2 ) );

		// Verify results.
		$this->assertCount( 2, $results['success'] );
		$this->assertEmpty( $results['failed'] );

		// Verify posts are published.
		$post1 = get_post( $listing1 );
		$post2 = get_post( $listing2 );
		$this->assertEquals( 'publish', $post1->post_status );
		$this->assertEquals( 'publish', $post2->post_status );
	}

	/**
	 * Test deleting pending listings.
	 */
	public function test_delete_pending_listings() {
		// Create pending listings.
		$post_type = dlt_get_post_type();
		$listing1   = $this->factory->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'pending',
				'post_title'  => 'Pending Listing 3',
			)
		);

		$pending_manager = Directorist_Listing_Tools_Pending_Manager::get_instance();

		$reflection = new ReflectionClass( $pending_manager );
		$method     = $reflection->getMethod( 'delete_listings' );
		$method->setAccessible( true );

		// Delete listing.
		$results = $method->invoke( $pending_manager, array( $listing1 ) );

		// Verify results.
		$this->assertCount( 1, $results['success'] );
		$this->assertEmpty( $results['failed'] );

		// Verify post is deleted.
		$this->assertNull( get_post( $listing1 ) );
	}

	/**
	 * Test getting pending listings.
	 */
	public function test_get_pending_listings() {
		// Create pending and published listings.
		$post_type = dlt_get_post_type();
		$pending   = $this->factory->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'pending',
			)
		);
		$published = $this->factory->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'publish',
			)
		);

		$pending_manager = Directorist_Listing_Tools_Pending_Manager::get_instance();

		$reflection = new ReflectionClass( $pending_manager );
		$method     = $reflection->getMethod( 'get_pending_listings' );
		$method->setAccessible( true );

		$pending_listings = $method->invoke( $pending_manager );

		// Verify only pending listing is returned.
		$ids = wp_list_pluck( $pending_listings, 'ID' );
		$this->assertContains( $pending, $ids );
		$this->assertNotContains( $published, $ids );
	}
}

