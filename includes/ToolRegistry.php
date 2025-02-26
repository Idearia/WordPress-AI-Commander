<?php
/**
 * Tool Registry Class
 *
 * @package WP_Natural_Language_Commands
 */

namespace WPNaturalLanguageCommands\Includes;

use WPNaturalLanguageCommands\Tools\BaseTool;

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Tool Registry class.
 *
 * This class manages all the tools in the plugin. It provides methods for
 * registering, retrieving, and executing tools.
 *
 * All tools will be registered in strict mode.
 *
 * Docs: https://platform.openai.com/docs/guides/function-calling
 */
class ToolRegistry {

    /**
     * The single instance of the class.
     *
     * @var ToolRegistry
     */
    private static $instance = null;

    /**
     * The registered tools.
     *
     * @var array
     */
    private $tools = array();

    /**
     * Constructor.
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

    /**
     * Get the single instance of the class.
     *
     * @return ToolRegistry The instance.
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Register a tool.
     *
     * @param BaseTool $tool The tool to register.
     * @return bool True if the tool was registered, false otherwise.
     */
    public function register_tool( $tool ) {
        if ( ! $tool instanceof BaseTool ) {
            return false;
        }

        $name = $tool->get_name();
        
        if ( isset( $this->tools[ $name ] ) ) {
            // Tool already registered
            return false;
        }

        $this->tools[ $name ] = $tool;
        return true;
    }

    /**
     * Get a registered tool by name.
     *
     * @param string $name The name of the tool to get.
     * @return BaseTool|null The tool, or null if not found.
     */
    public function get_tool( $name ) {
        return isset( $this->tools[ $name ] ) ? $this->tools[ $name ] : null;
    }
    
    /**
     * Check if a tool is registered.
     *
     * @param string $name The name of the tool to check.
     * @return bool True if the tool is registered, false otherwise.
     */
    public function has_tool( $name ) {
        return isset( $this->tools[ $name ] );
    }

    /**
     * Get all registered tools.
     *
     * @return array The registered tools.
     */
    public function get_tools() {
        return $this->tools;
    }

    /**
     * Get all tool definitions for OpenAI function calling.
     *
     * This array will be converted to JSON and included in all calls
     * to the OpenAI API.
     *
     * @return array The tool definitions.
     */
    public function get_tool_definitions() {
        $definitions = array();
        
        foreach ( $this->tools as $name => $tool ) {

            $properties = (object)array_map( function( $param ) {
                // Even in strict mode, it is still possible to define optional parameters,
                // by including 'null' in the 'type' property.
                $param['type'] = is_array( $param['type'] ) ? $param['type'] : array( $param['type'] );
                if ( $param['required'] === false && ! in_array( 'null', $param['type'] ) ) {
                    $param['type'][] = 'null';
                }
                // We need to get rid of the 'required' and 'default' properties, as they are not
                // part of the OpenAI function calling schema.
                unset( $param['required'], $param['default'] );
                return $param;
            }, $tool->get_parameters() );

            $definitions[] = array(
                'strict' => true,
                'type' => 'function',
                'function' => array(
                    'name' => $name,
                    'description' => $tool->get_description(),
                    'parameters' => array(
                        'type' => 'object',
                        // In strict mode, all parameters must be listed in the 'required' property.
                        'required' => array_keys( $tool->get_parameters() ),
                        'properties' => $properties,
                    ),
                ),
            );
        }
        
        return $definitions;
    }

    /**
     * Execute a tool by name with the given parameters.
     *
     * @param string $name The name of the tool to execute.
     * @param array $params The parameters to use when executing the tool.
     * @return array|\WP_Error The result of executing the tool, or \WP_Error on failure.
     */
    public function execute_tool( $name, $params ) {
        $tool = $this->get_tool( $name );
        
        if ( ! $tool ) {
            return new \WP_Error(
                'tool_not_found',
                sprintf( 'Tool not found: %s', $name )
            );
        }
        
        return $tool->execute( $params );
    }

    /**
     * Auto-discover and register tools from the tools directory.
     */
    public function discover_tools() {
        $tools_dir = WP_NATURAL_LANGUAGE_COMMANDS_PLUGIN_DIR . 'tools/';
        $tool_files = glob( $tools_dir . 'class-*-tool.php' );
        
        foreach ( $tool_files as $file ) {
            // Skip the base tool class
            if ( basename( $file ) === 'class-base-tool.php' ) {
                continue;
            }
            
            // The file is already included in the main plugin file
            // So we don't need to include it here
            
            // Extract the class name from the file name
            $class_name = str_replace( 
                array( 'class-', '-tool.php', '-' ), 
                array( '', '', '_' ), 
                basename( $file ) 
            );
            $class_name = 'WP_NLC_' . ucwords( $class_name, '_' ) . '_Tool';
            
            // Instantiate the tool
            if ( class_exists( $class_name ) ) {
                new $class_name();
            }
        }
    }
}
