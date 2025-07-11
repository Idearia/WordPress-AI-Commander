<?php
/**
 * Site Information Tool Class
 *
 * @package AICommander
 */

namespace AICommander\Tools;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Site Information Tool class.
 *
 * This class handles the retrieval of site information via natural language commands.
 */
class SiteInformationTool extends BaseTool {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->name = 'get_site_info';
        $this->description = __( 'Retrieves basic WordPress site information', 'ai-commander' );
        $this->required_capability = 'read';
        
        parent::__construct();
    }

    /**
     * Get the tool parameters for OpenAI function calling.
     *
     * @return array The tool parameters.
     */
    public function get_parameters() {
        return array(
            // No parameters needed for this tool as it retrieves general site information
        );
    }

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params The parameters to use when executing the tool.
     * @return array|\WP_Error The result of executing the tool.
     */
    public function execute( $params ) {
        // Get site title
        $site_title = get_bloginfo( 'name' );
        
        // Get site URL
        $site_url = get_bloginfo( 'url' );
        
        // Get site tagline
        $site_tagline = get_bloginfo( 'description' );
        
        // Prepare the response
        $response = array(
            'success' => true,
            'site_info' => array(
                'title' => $site_title,
                'url' => $site_url,
                'tagline' => $site_tagline,
            ),
        );
        
        // Check if this is a multisite installation
        if ( is_multisite() ) {
            // Get the current site ID
            $site_id = get_current_blog_id();
            $response['site_info']['site_id'] = $site_id;
            
            // Get network information
            $response['site_info']['is_multisite'] = true;
            $response['site_info']['network_name'] = get_network()->site_name;
            $response['site_info']['network_url'] = network_home_url();
        } else {
            $response['site_info']['is_multisite'] = false;
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
        $summary_parts = array();
        
        if ( isset( $result['site_info'] ) ) {
            $site_info = $result['site_info'];
            
            if ( isset( $site_info['title'] ) ) {
                $summary_parts[] = sprintf( __( 'Site Title: %s', 'ai-commander' ), $site_info['title'] );
            }
            
            if ( isset( $site_info['url'] ) ) {
                $summary_parts[] = sprintf( __( 'Site URL: %s', 'ai-commander' ), $site_info['url'] );
            }
            
            if ( isset( $site_info['tagline'] ) && ! empty( $site_info['tagline'] ) ) {
                $summary_parts[] = sprintf( __( 'Site Tagline: %s', 'ai-commander' ), $site_info['tagline'] );
            }
            
            if ( isset( $site_info['is_multisite'] ) && $site_info['is_multisite'] ) {
                if ( isset( $site_info['site_id'] ) ) {
                    $summary_parts[] = sprintf( __( 'Site ID: %d', 'ai-commander' ), $site_info['site_id'] );
                }
                
                if ( isset( $site_info['network_name'] ) ) {
                    $summary_parts[] = sprintf( __( 'Network Name: %s', 'ai-commander' ), $site_info['network_name'] );
                }
            }
        }
        
        return implode( '. ', $summary_parts ) . '.';
    }
}
