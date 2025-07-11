<?php
/**
 * Post Editing Tool Class
 *
 * @package AICommander
 */

namespace AICommander\Tools;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Post Editing Tool class.
 *
 * This class handles the editing of posts via natural language commands.
 */
class PostEditingTool extends BaseTool {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->name = 'edit_post';
        $this->description = __( 'Edits an existing WordPress post', 'ai-commander' );
        $this->required_capability = 'edit_others_posts';
        
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
                'description' => __( 'The ID of the post to edit', 'ai-commander' ),
                'required' => false,
            ),
            'post_title' => array(
                'type' => 'string',
                'description' => __( 'The title of the post to find (if post_id is not provided)', 'ai-commander' ),
                'required' => false,
            ),
            'title' => array(
                'type' => 'string',
                'description' => __( 'The new title for the post', 'ai-commander' ),
                'required' => false,
            ),
            'content' => array(
                'type' => 'string',
                'description' => __( 'The new content for the post', 'ai-commander' ),
                'required' => false,
            ),
            'excerpt' => array(
                'type' => 'string',
                'description' => __( 'The new excerpt for the post', 'ai-commander' ),
                'required' => false,
            ),
            'status' => array(
                'type' => 'string',
                'description' => __( 'The new status for the post (draft, publish, pending, future)', 'ai-commander' ),
                'enum' => array( 'draft', 'publish', 'pending', 'future' ),
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
            'date' => array(
                'type' => 'string',
                'description' => __( 'The new date to publish the post (format: YYYY-MM-DD HH:MM:SS)', 'ai-commander' ),
                'required' => false,
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
                __( 'Either post_id or post_title is required to identify the post to edit.', 'ai-commander' )
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

        // Prepare post data for update
        $post_data = array(
            'ID' => $post_id,
        );

        // Add fields to update if provided
        if ( isset( $params['title'] ) ) {
            $post_data['post_title'] = sanitize_text_field( $params['title'] );
        }

        if ( isset( $params['content'] ) ) {
            $post_data['post_content'] = wp_kses_post( $params['content'] );
        }

        if ( isset( $params['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
        }

        if ( isset( $params['status'] ) ) {
            $post_data['post_status'] = sanitize_text_field( $params['status'] );
        }

        if ( isset( $params['date'] ) ) {
            $post_data['post_date'] = sanitize_text_field( $params['date'] );
            $post_data['post_date_gmt'] = get_gmt_from_date( $params['date'] );
        }

        // Update the post
        $result = wp_update_post( $post_data, true );

        if ( $result instanceof \WP_Error ) {
            return $result;
        }

        // Handle categories
        if ( isset( $params['categories'] ) && is_array( $params['categories'] ) ) {
            $category_ids = array();

            foreach ( $params['categories'] as $category_name ) {
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

            if ( ! empty( $category_ids ) ) {
                wp_set_post_categories( $post_id, $category_ids );
            }
        }

        // Handle tags
        if ( isset( $params['tags'] ) && is_array( $params['tags'] ) ) {
            wp_set_post_tags( $post_id, $params['tags'] );
        }

        // Get post info
        $post_title = get_the_title( $post_id );
        $post_type = get_post_type( $post_id );
        $post_url = get_permalink( $post_id );
        $edit_url = get_edit_post_link( $post_id, 'raw' );

        // Return the result
        return array(
            'success' => true,
            'post_id' => $post_id,
            'post_title' => $post_title,
            'post_type' => $post_type,
            'post_url' => $post_url,
            'edit_url' => $edit_url,
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

        $post_id = isset( $result['post_id'] ) ? $result['post_id'] : 'unknown';
        $post_title = isset( $result['post_title'] ) ? $result['post_title'] : 'unknown';
        $post_type = isset( $result['post_type'] ) ? $result['post_type'] : 'unknown';

        $summary = '';

        if ( $post_type === 'post' ) {
            $summary = sprintf( __( 'Post "%s" updated successfully with ID %d.', 'ai-commander' ), $post_title, $post_id );
        }
        else if ( $post_type === 'page' ) {
            $summary = sprintf( __( 'Page "%s" updated successfully with ID %d.', 'ai-commander' ), $post_title, $post_id );
        }
        else {
            $summary = sprintf( __( 'Post of type "%s" updated successfully with ID %d.', 'ai-commander' ), $post_type, $post_id );
        }

        return $summary;
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
}
