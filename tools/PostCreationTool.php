<?php
/**
 * Post Creation Tool Class
 *
 * @package WPNL
 */

namespace WPNL\Tools;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Post Creation Tool class.
 *
 * This class handles the creation of posts via natural language commands.
 */
class PostCreationTool extends BaseTool {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->name = 'create_post';
        $this->description = 'Creates a new WordPress post';
        $this->required_capability = 'publish_posts';
        
        parent::__construct();
    }

    /**
     * Get the tool parameters for OpenAI function calling.
     *
     * @return array The tool parameters.
     */
    public function get_parameters() {
        return array(
            'title' => array(
                'type' => 'string',
                'description' => 'The title of the post',
                'required' => true,
            ),
            'content' => array(
                'type' => 'string',
                'description' => 'The content of the post',
                'required' => false,
            ),
            'excerpt' => array(
                'type' => 'string',
                'description' => 'The excerpt of the post',
                'required' => false,
            ),
            'status' => array(
                'type' => 'string',
                'description' => 'The status of the post (draft, publish, pending, future)',
                'enum' => array( 'draft', 'publish', 'pending', 'future' ),
                'required' => false,
                'default' => 'draft',
            ),
            'post_type' => array(
                'type' => 'string',
                'description' => 'The type of post to create',
                'required' => false,
                'default' => 'post',
            ),
            'categories' => array(
                'type' => 'array',
                'description' => 'The categories to assign to the post',
                'items' => array(
                    'type' => 'string',
                ),
                'required' => false,
            ),
            'tags' => array(
                'type' => 'array',
                'description' => 'The tags to assign to the post',
                'items' => array(
                    'type' => 'string',
                ),
                'required' => false,
            ),
            'date' => array(
                'type' => 'string',
                'description' => 'The date to publish the post (format: YYYY-MM-DD HH:MM:SS)',
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
        // Prepare post data
        $post_data = array(
            'post_title'    => sanitize_text_field( $params['title'] ),
            'post_content'  => wp_kses_post( $params['content'] ),
            'post_status'   => sanitize_text_field( $params['status'] ),
            'post_type'     => sanitize_text_field( $params['post_type'] ),
        );

        // Add optional parameters if provided
        if ( isset( $params['excerpt'] ) ) {
            $post_data['post_excerpt'] = sanitize_text_field( $params['excerpt'] );
        }

        if ( isset( $params['date'] ) ) {
            $post_data['post_date'] = sanitize_text_field( $params['date'] );
            $post_data['post_date_gmt'] = get_gmt_from_date( $params['date'] );
        }

        // Insert the post
        $post_id = wp_insert_post( $post_data, true );

        if ( $post_id instanceof \WP_Error ) {
            return $post_id;
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
        
        if ( $post_type === 'post' ) {
            return sprintf( 'Post "%s" created successfully with ID %d.', $post_title, $post_id );
        } elseif ( $post_type === 'page' ) {
            return sprintf( 'Page "%s" created successfully with ID %d.', $post_title, $post_id );
        } else {
            return sprintf( 'Post of type "%s" created successfully with ID %d.', $post_type, $post_id );
        }
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
                'label' => 'View post',
                'url' => $post_url,
                'target' => '_blank',
            );
        }
        
        if ( !empty( $edit_url ) ) {
            $buttons[] = array(
                'type' => 'link',
                'label' => 'Edit post',
                'url' => $edit_url,
                'target' => '_blank',
            );
        }
        
        return $buttons;
    }
}
