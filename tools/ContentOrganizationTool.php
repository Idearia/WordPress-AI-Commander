<?php
/**
 * Content Organization Tool Class
 *
 * @package AICommander
 */

namespace AICommander\Tools;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Content Organization Tool class.
 *
 * This class handles the organization of content via natural language commands.
 */
class ContentOrganizationTool extends BaseTool {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->name = 'organize_content';
        $this->description = __( 'Organizes WordPress content by assigning categories, tags, and other taxonomies', 'ai-commander' );
        $this->required_capability = 'manage_categories';
        
        parent::__construct();
    }

    /**
     * Get the tool parameters for OpenAI function calling.
     *
     * @return array The tool parameters.
     */
    public function get_parameters() {
        return array(
            'post_id' => array(
                'type' => 'integer',
                'description' => __( 'The ID of the post to organize', 'ai-commander' ),
                'required' => false,
            ),
            'post_title' => array(
                'type' => 'string',
                'description' => __( 'The title of the post to find (if post_id is not provided)', 'ai-commander' ),
                'required' => false,
            ),
            'categories' => array(
                'type' => 'array',
                'description' => __( 'The categories to assign to the post', 'ai-commander' ),
                'items' => array(
                    'type' => 'string',
                ),
                'required' => false,
            ),
            'tags' => array(
                'type' => 'array',
                'description' => __( 'The tags to assign to the post', 'ai-commander' ),
                'items' => array(
                    'type' => 'string',
                ),
                'required' => false,
            ),
            'featured_image' => array(
                'type' => 'string',
                'description' => __( 'The URL or ID of the featured image to set for the post', 'ai-commander' ),
                'required' => false,
            ),
            'action' => array(
                'type' => 'string',
                'description' => __( 'The action to perform (add, remove, replace)', 'ai-commander' ),
                'enum' => array( 'add', 'remove', 'replace' ),
                'required' => false,
                'default' => 'add',
            ),
        );
    }

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params The parameters to use when executing the tool.
     * @return array|\WP_Error The result of executing the tool.
     */
    public function execute( $params ) {
        // We need either post_id or post_title to find the post
        if ( empty( $params['post_id'] ) && empty( $params['post_title'] ) ) {
            return new \WP_Error(
                'missing_post_identifier',
                __( 'Either post_id or post_title is required to identify the post to organize.', 'ai-commander' )
            );
        }

        // We need at least one organization parameter
        if ( empty( $params['categories'] ) && empty( $params['tags'] ) && empty( $params['featured_image'] ) ) {
            return new \WP_Error(
                'missing_organization_parameters',
                __( 'At least one of categories, tags, or featured_image is required.', 'ai-commander' )
            );
        }

        // Find the post
        $post_id = $this->find_post( $params );
        
        if ( $post_id instanceof \WP_Error ) {
            return $post_id;
        }

        // Get the current post data
        $post = get_post( $post_id );
        
        if ( ! $post ) {
            return new \WP_Error(
                'post_not_found',
                sprintf( __( 'Post with ID %d not found.', 'ai-commander' ), $post_id )
            );
        }

        // Track changes
        $changes = array();

        // Handle categories
        if ( isset( $params['categories'] ) && is_array( $params['categories'] ) ) {
            $result = $this->handle_categories( $post_id, $params['categories'], $params['action'] );
            if ( $result instanceof \WP_Error ) {
                return $result;
            }
            $changes[] = $result;
        }

        // Handle tags
        if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
            $result = $this->handle_tags( $post_id, $params['tags'], $params['action'] );
            if ( $result instanceof \WP_Error ) {
                return $result;
            }
            $changes[] = $result;
        }

        // Handle featured image
        if ( isset( $params['featured_image'] ) ) {
            $result = $this->handle_featured_image( $post_id, $params['featured_image'] );
            if ( $result instanceof \WP_Error ) {
                return $result;
            }
            $changes[] = $result;
        }

        // Get the post URL
        $post_url = get_permalink( $post_id );

        // Get the edit URL
        $edit_url = get_edit_post_link( $post_id, 'raw' );

        // Return the result
        return array(
            'success' => true,
            'post_id' => $post_id,
            'post_url' => $post_url,
            'edit_url' => $edit_url,
            'changes' => $changes,
        );
    }
    
    /**
     * Get a human-readable summary of the tool execution result.
     *
     * @param array|\WP_Error $result The result of executing the tool.
     * @param array $params The parameters used when executing the tool.
     * @return string A human-readable summary of the result.
     */
    public function get_result_summary( $result, $params ) {
        if ( is_wp_error( $result ) ) {
            return $result->get_error_message();
        }
        
        // Create a detailed summary based on the changes made
        $summary_parts = array();
        
        if ( isset( $result['post_id'] ) ) {
            $post_title = get_the_title( $result['post_id'] );
            $post_id = $result['post_id'];
            
            $summary_parts[] = sprintf( __( 'Post "%s" (ID: %d) organized', 'ai-commander' ), $post_title, $post_id );
            
            if ( isset( $result['changes'] ) && is_array( $result['changes'] ) ) {
                foreach ( $result['changes'] as $change ) {
                    if ( isset( $change['type'] ) ) {
                        switch ( $change['type'] ) {
                            case 'categories':
                                if ( isset( $change['action'], $change['after'] ) ) {
                                    $categories = implode( ', ', $change['after'] );
                                    if ( ! empty( $categories ) ) {
                                        $summary_parts[] = sprintf( __( 'Categories set to: %s', 'ai-commander' ), $categories );
                                    }
                                }
                                break;
                                
                            case 'tags':
                                if ( isset( $change['action'], $change['after'] ) ) {
                                    $tags = implode( ', ', $change['after'] );
                                    if ( ! empty( $tags ) ) {
                                        $summary_parts[] = sprintf( __( 'Tags set to: %s', 'ai-commander' ), $tags );
                                    }
                                }
                                break;
                                
                            case 'featured_image':
                                if ( isset( $change['after'] ) && ! empty( $change['after'] ) ) {
                                    $summary_parts[] = __( 'Featured image updated', 'ai-commander' );
                                }
                                break;
                        }
                    }
                }
            }
        }
        
        return implode( '. ', $summary_parts ) . '.';
    }

    /**
     * Get action buttons for the tool execution result.
     *
     * @param array|\WP_Error $result The result of executing the tool.
     * @param array $params The parameters used when executing the tool.
     * @return array Array of action button definitions.
     */
    public function get_action_buttons( $result, $params ) {
        if ( is_wp_error( $result ) ) {
            return array();
        }

        $post_url = isset( $result['post_url'] ) ? $result['post_url'] : '';
        $edit_url = isset( $result['edit_url'] ) ? $result['edit_url'] : '';

        $buttons = array();

        if ( !empty( $post_url ) ) {
            $buttons[] = array(
                'type' => 'link',
                'label' => __( 'View post', 'ai-commander' ),
                'url' => $post_url,
                'target' => '_blank',
            );
        }
        
        if ( !empty( $edit_url ) ) {
            $buttons[] = array(
                'type' => 'link',
                'label' => __( 'Edit post', 'ai-commander' ),
                'url' => $edit_url,
                'target' => '_blank',
            );
        }
        
        return $buttons;
    }

    /**
     * Find a post by ID or title.
     *
     * @param array $params The parameters to use when finding the post.
     * @return int|\WP_Error The post ID, or \WP_Error on failure.
     */
    private function find_post( $params ) {
        // If post_id is provided, use it
        if ( ! empty( $params['post_id'] ) ) {
            return intval( $params['post_id'] );
        }

        // Otherwise, search by title
        $query_args = array(
            'post_type'      => 'any',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'title'          => $params['post_title'],
            'no_found_rows'  => true,
            'fields'         => 'ids',
        );

        $query = new \WP_Query( $query_args );

        if ( ! $query->have_posts() ) {
            return new \WP_Error(
                'post_not_found',
                sprintf( __( 'No post found with title "%s".', 'ai-commander' ), $params['post_title'] )
            );
        }

        return $query->posts[0];
    }

    /**
     * Handle categories.
     *
     * @param int    $post_id    The post ID.
     * @param array  $categories The categories to handle.
     * @param string $action     The action to perform.
     * @return array|\WP_Error The result of handling categories.
     */
    private function handle_categories( $post_id, $categories, $action ) {
        // Get current categories
        $current_categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
        
        // Prepare category IDs
        $category_ids = array();
        
        // Process each category
        foreach ( $categories as $category_name ) {
            $category = get_term_by( 'name', $category_name, 'category' );
            
            if ( $category ) {
                $category_ids[] = $category->term_id;
            } else {
                // Create the category if it doesn't exist
                $new_category = wp_insert_term( $category_name, 'category' );
                if ( ! $new_category instanceof \WP_Error ) {
                    $category_ids[] = $new_category['term_id'];
                }
            }
        }
        
        // Handle different actions
        switch ( $action ) {
            case 'add':
                // Get current category IDs
                $current_category_ids = wp_get_post_categories( $post_id );
                
                // Merge with new category IDs
                $category_ids = array_unique( array_merge( $current_category_ids, $category_ids ) );
                break;
                
            case 'remove':
                // Get current category IDs
                $current_category_ids = wp_get_post_categories( $post_id );
                
                // Remove specified categories
                $category_ids_to_remove = $category_ids;
                $category_ids = array_diff( $current_category_ids, $category_ids_to_remove );
                break;
                
            case 'replace':
                // Use only the new category IDs
                break;
        }
        
        // Update post categories
        $result = wp_set_post_categories( $post_id, $category_ids );
        
        if ( false === $result ) {
            return new \WP_Error(
                'category_update_failed',
                __( 'Failed to update post categories.', 'ai-commander' )
            );
        }
        
        // Get updated categories
        $updated_categories = wp_get_post_categories( $post_id, array( 'fields' => 'names' ) );
        
        return array(
            'type' => 'categories',
            'action' => $action,
            'before' => $current_categories,
            'after' => $updated_categories,
        );
    }

    /**
     * Handle tags.
     *
     * @param int    $post_id The post ID.
     * @param array  $tags    The tags to handle.
     * @param string $action  The action to perform.
     * @return array|\WP_Error The result of handling tags.
     */
    private function handle_tags( $post_id, $tags, $action ) {
        // Get current tags
        $current_tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
        
        // Handle different actions
        switch ( $action ) {
            case 'add':
                // Append new tags
                $result = wp_set_post_tags( $post_id, $tags, true );
                break;
                
            case 'remove':
                // Remove specified tags
                $tags_to_keep = array_diff( $current_tags, $tags );
                $result = wp_set_post_tags( $post_id, $tags_to_keep, false );
                break;
                
            case 'replace':
                // Replace all tags
                $result = wp_set_post_tags( $post_id, $tags, false );
                break;
        }
        
        if ( false === $result ) {
            return new \WP_Error(
                'tag_update_failed',
                __( 'Failed to update post tags.', 'ai-commander' )
            );
        }
        
        // Get updated tags
        $updated_tags = wp_get_post_tags( $post_id, array( 'fields' => 'names' ) );
        
        return array(
            'type' => 'tags',
            'action' => $action,
            'before' => $current_tags,
            'after' => $updated_tags,
        );
    }

    /**
     * Handle featured image.
     *
     * @param int    $post_id        The post ID.
     * @param string $featured_image The featured image URL or ID.
     * @return array|\WP_Error The result of handling the featured image.
     */
    private function handle_featured_image( $post_id, $featured_image ) {
        // Get current featured image
        $current_thumbnail_id = get_post_thumbnail_id( $post_id );
        $current_thumbnail_url = $current_thumbnail_id ? wp_get_attachment_url( $current_thumbnail_id ) : '';
        
        // Check if featured_image is a URL or ID
        if ( is_numeric( $featured_image ) ) {
            // It's an ID
            $attachment_id = intval( $featured_image );
        } else {
            // It's a URL or path, try to find or upload the image
            $attachment_id = $this->get_attachment_id_from_url( $featured_image );
            
            if ( ! $attachment_id ) {
                // Image doesn't exist in the media library, try to upload it
                $attachment_id = $this->upload_image_from_url( $featured_image );
                
                if ( $attachment_id instanceof \WP_Error ) {
                    return $attachment_id;
                }
            }
        }
        
        // Set the featured image
        $result = set_post_thumbnail( $post_id, $attachment_id );
        
        if ( false === $result ) {
            return new \WP_Error(
                'featured_image_update_failed',
                __( 'Failed to update featured image.', 'ai-commander' )
            );
        }
        
        // Get updated featured image
        $updated_thumbnail_id = get_post_thumbnail_id( $post_id );
        $updated_thumbnail_url = $updated_thumbnail_id ? wp_get_attachment_url( $updated_thumbnail_id ) : '';
        
        return array(
            'type' => 'featured_image',
            'before' => $current_thumbnail_url,
            'after' => $updated_thumbnail_url,
        );
    }

    /**
     * Get attachment ID from URL.
     *
     * @param string $url The attachment URL.
     * @return int|false The attachment ID, or false if not found.
     */
    private function get_attachment_id_from_url( $url ) {
        global $wpdb;
        
        // First, try to get the attachment ID using WordPress functions
        $attachment_id = attachment_url_to_postid( $url );
        
        if ( $attachment_id ) {
            return $attachment_id;
        }
        
        // If that fails, try to query the database directly
        $url = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $url );
        $url = str_replace( wp_get_upload_dir()['baseurl'] . '/', '', $url );
        
        $attachment_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s", $url ) );
        
        return $attachment_id ? intval( $attachment_id ) : false;
    }

    /**
     * Upload image from URL.
     *
     * @param string $url The image URL.
     * @return int|\WP_Error The attachment ID, or \WP_Error on failure.
     */
    private function upload_image_from_url( $url ) {
        // Require necessary files
        require_once( ABSPATH . 'wp-admin/includes/file.php' );
        require_once( ABSPATH . 'wp-admin/includes/media.php' );
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        
        // Download the image
        $temp_file = download_url( $url );
        
        if ( $temp_file instanceof \WP_Error ) {
            return $temp_file;
        }
        
        // Prepare file array
        $file_array = array(
            'name'     => basename( $url ),
            'tmp_name' => $temp_file,
        );
        
        // Upload the image
        $attachment_id = media_handle_sideload( $file_array, 0 );
        
        // Clean up the temporary file
        if ( file_exists( $temp_file ) ) {
            wp_delete_file( $temp_file );
        }
        
        return $attachment_id;
    }
}
