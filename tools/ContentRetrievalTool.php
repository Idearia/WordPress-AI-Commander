<?php
/**
 * Content Retrieval Tool Class
 *
 * @package AICommander
 */

namespace AICommander\Tools;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Content Retrieval Tool class.
 *
 * This class handles the retrieval of content via natural language commands.
 */
class ContentRetrievalTool extends BaseTool {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->name = 'retrieve_content';
        $this->description = __( 'Retrieves WordPress content based on various criteria', 'ai-commander' );
        $this->required_capability = 'read_private_posts';
        
        parent::__construct();
    }

    /**
     * Get the tool parameters for OpenAI function calling.
     *
     * @return array The tool parameters.
     */
    public function get_parameters() {
        return array(
            'post_type' => array(
                'type' => 'string',
                'description' => __( 'The type of post to retrieve (post, page, or any custom post type)', 'ai-commander' ),
                'required' => false,
                'default' => 'post',
            ),
            'search' => array(
                'type' => 'string',
                'description' => __( 'Search term to find in posts', 'ai-commander' ),
                'required' => false,
            ),
            'author' => array(
                'type' => 'string',
                'description' => __( 'Author name or ID to filter by', 'ai-commander' ),
                'required' => false,
            ),
            'category' => array(
                'type' => 'string',
                'description' => __( 'Category name or ID to filter by', 'ai-commander' ),
                'required' => false,
            ),
            'tag' => array(
                'type' => 'string',
                'description' => __( 'Tag name or ID to filter by', 'ai-commander' ),
                'required' => false,
            ),
            'status' => array(
                'type' => 'string',
                'description' => __( 'Post status to filter by (publish, draft, pending, future, etc.)', 'ai-commander' ),
                'required' => false,
                'default' => 'publish',
            ),
            'order_by' => array(
                'type' => 'string',
                'description' => __( 'Field to order results by (date, title, modified, etc.)', 'ai-commander' ),
                'required' => false,
                'default' => 'date',
            ),
            'order' => array(
                'type' => 'string',
                'description' => __( 'Order direction (ASC or DESC)', 'ai-commander' ),
                'enum' => array( 'ASC', 'DESC' ),
                'required' => false,
                'default' => 'DESC',
            ),
            'limit' => array(
                'type' => 'integer',
                'description' => __( 'Maximum number of posts to retrieve', 'ai-commander' ),
                'required' => false,
                'default' => 10,
            ),
            'include_content' => array(
                'type' => 'boolean',
                'description' => __( 'Whether to include the full content of posts', 'ai-commander' ),
                'required' => false,
                'default' => false,
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
        // Build query arguments
        $query_args = array(
            'post_type'      => $params['post_type'],
            'post_status'    => $params['status'],
            'posts_per_page' => $params['limit'],
            'orderby'        => $params['order_by'],
            'order'          => $params['order'],
        );
        
        // Add search parameter if provided
        if ( ! empty( $params['search'] ) ) {
            $query_args['s'] = $params['search'];
        }
        
        // Add author parameter if provided
        if ( ! empty( $params['author'] ) ) {
            // Check if author is numeric (ID) or string (name)
            if ( is_numeric( $params['author'] ) ) {
                $query_args['author'] = intval( $params['author'] );
            } else {
                $author = get_user_by( 'login', $params['author'] );
                if ( ! $author ) {
                    $author = get_user_by( 'slug', $params['author'] );
                }
                if ( ! $author ) {
                    $author = get_user_by( 'email', $params['author'] );
                }
                
                if ( $author ) {
                    $query_args['author'] = $author->ID;
                } else {
                    return new \WP_Error(
                        'author_not_found',
                        sprintf( __( 'Author "%s" not found.', 'ai-commander' ), $params['author'] )
                    );
                }
            }
        }
        
        // Add category parameter if provided
        if ( ! empty( $params['category'] ) ) {
            // Check if category is numeric (ID) or string (name)
            if ( is_numeric( $params['category'] ) ) {
                $query_args['cat'] = intval( $params['category'] );
            } else {
                $category = get_term_by( 'name', $params['category'], 'category' );
                if ( ! $category ) {
                    $category = get_term_by( 'slug', $params['category'], 'category' );
                }
                
                if ( $category ) {
                    $query_args['cat'] = $category->term_id;
                } else {
                    return new \WP_Error(
                        'category_not_found',
                        sprintf( __( 'Category "%s" not found.', 'ai-commander' ), $params['category'] )
                    );
                }
            }
        }
        
        // Add tag parameter if provided
        if ( ! empty( $params['tag'] ) ) {
            // Check if tag is numeric (ID) or string (name)
            if ( is_numeric( $params['tag'] ) ) {
                $query_args['tag_id'] = intval( $params['tag'] );
            } else {
                $tag = get_term_by( 'name', $params['tag'], 'post_tag' );
                if ( ! $tag ) {
                    $tag = get_term_by( 'slug', $params['tag'], 'post_tag' );
                }
                
                if ( $tag ) {
                    $query_args['tag_id'] = $tag->term_id;
                } else {
                    return new \WP_Error(
                        'tag_not_found',
                        sprintf( __( 'Tag "%s" not found.', 'ai-commander' ), $params['tag'] )
                    );
                }
            }
        }
        
        // Execute the query
        $query = new \WP_Query( $query_args );
        
        // Prepare the results
        $posts = array();
        
        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                
                $post_id = get_the_ID();
                $post = array(
                    'id'         => $post_id,
                    'title'      => get_the_title(),
                    'date'       => get_the_date( 'Y-m-d H:i:s' ),
                    'modified'   => get_the_modified_date( 'Y-m-d H:i:s' ),
                    'status'     => get_post_status(),
                    'author'     => get_the_author(),
                    'permalink'  => get_permalink(),
                    'post_url'  => get_permalink( $post_id ),
                    'edit_url'  => get_edit_post_link( $post_id, 'raw' ),
                );
                
                // Add excerpt
                $post['excerpt'] = get_the_excerpt();
                
                // Add content if requested
                if ( $params['include_content'] ) {
                    $post['content'] = get_the_content();
                }
                
                // Add categories
                $categories = get_the_category();
                $post['categories'] = array();
                
                if ( ! empty( $categories ) ) {
                    foreach ( $categories as $category ) {
                        $post['categories'][] = $category->name;
                    }
                }
                
                // Add tags
                $tags = get_the_tags();
                $post['tags'] = array();
                
                if ( ! empty( $tags ) ) {
                    foreach ( $tags as $tag ) {
                        $post['tags'][] = $tag->name;
                    }
                }
                
                // Add featured image if available
                $thumbnail_id = get_post_thumbnail_id( $post_id );
                if ( $thumbnail_id ) {
                    $post['featured_image'] = wp_get_attachment_url( $thumbnail_id );
                }
                
                $posts[] = $post;
            }
            
            // Reset post data
            wp_reset_postdata();
        }
        
        // Prepare the response
        $response = array(
            'success' => true,
            'count'   => count( $posts ),
            'posts'   => $posts,
        );
        
        // Add query information
        $response['query'] = array(
            'post_type' => $params['post_type'],
            'status'    => $params['status'],
            'limit'     => $params['limit'],
            'order_by'  => $params['order_by'],
            'order'     => $params['order'],
        );
        
        if ( ! empty( $params['search'] ) ) {
            $response['query']['search'] = $params['search'];
        }
        
        if ( ! empty( $params['author'] ) ) {
            $response['query']['author'] = $params['author'];
        }
        
        if ( ! empty( $params['category'] ) ) {
            $response['query']['category'] = $params['category'];
        }
        
        if ( ! empty( $params['tag'] ) ) {
            $response['query']['tag'] = $params['tag'];
        }
        
        return $response;
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
        
        $posts = $result['posts'];
        $count = count( $posts );
        
        if ( $count === 0 ) {
            return __( 'No posts found matching the criteria.', 'ai-commander' );
        }
        
        $summary = sprintf(
            __( 'Found %d %s matching the criteria:', 'ai-commander' ),
            $count,
            $count === 1 ? $params['post_type'] : $params['post_type'] . 's'
        );
        
        // For each post, show title, view link and edit link
        if ( $count > 0 ) {
            $summary .= "<ul>";
        }
        foreach ( $posts as $post ) {
            $summary .= sprintf(
                "<li>%s (%s | %s)</li>",
                $post['title'],
                "<a href='" . esc_url( $post['post_url'] ) . "' target='_blank'>" . __( 'View', 'ai-commander' ) . "</a>",
                "<a href='" . esc_url( $post['edit_url'] ) . "' target='_blank'>" . __( 'Edit', 'ai-commander' ) . "</a>"
            );
        }
        if ( $count > 0 ) {
            $summary .= "</ul>";
        }
        
        return $summary;
    }
}
