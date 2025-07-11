<?php
/**
 * Date Tool Class
 *
 * @package AICommander
 */

namespace AICommander\Tools;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Date Tool class.
 *
 * This class provides the current date in ISO 8601 format.
 */
class GetTodayDateTool extends BaseTool {

    /**
     * Constructor.
     */
    public function __construct() {
        $this->name = 'get_today_date';
        $this->description = __( 'Returns the current date in ISO 8601 format, together with the week day name.  Useful to interpret queries that contain "today", "yesterday", "tomorrow", "next monday", "last week", "next week", etc.', 'ai-commander' );
        $this->required_capability = 'read'; // Basic capability that most users have
        
        parent::__construct();
    }

    /**
     * Get the tool parameters for OpenAI function calling.
     *
     * @return array The tool parameters.
     */
    public function get_parameters() {
        // No parameters needed
        return array();
    }

    /**
     * Execute the tool with the given parameters.
     *
     * @param array $params The parameters to use when executing the tool.
     * @return array|\WP_Error The result of executing the tool.
     */
    public function execute( $params ) {
        // Get current date in ISO 8601 format
        $current_date = current_datetime()->format('c');
        $week_day_name = current_datetime()->format('l');
        
        // Prepare the response
        $response = array(
            'success' => true,
            'today_date' => $current_date,
            'week_day_name' => $week_day_name,
        );
        
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
        
        return sprintf( __( '<p>Today is %s, %s</p>', 'ai-commander' ), $result['week_day_name'], $result['today_date'] );
    }
}
