<?php
/**
 * Tool Registry Class
 *
 * @package WP_Natural_Language_Commands
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Tool Registry class.
 *
 * This class manages all the tools in the plugin. It provides methods for
 * registering, retrieving, and executing tools.
 */
class WP_NLC_Tool_Registry {

    /**
     * The single instance of the class.
     *
     * @var WP_NLC_Tool_Registry
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
     * @return WP_NLC_Tool_Registry The instance.
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
     * @param WP_NLC_Base_Tool $tool The tool to register.
     * @return bool True if the tool was registered, false otherwise.
     */
    public function register_tool( $tool ) {
        if ( ! $tool instanceof WP_NLC_Base_Tool ) {
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
     * @return WP_NLC_Base_Tool|null The tool, or null if not found.
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
     * @return array The tool definitions.
     */
    public function get_tool_definitions() {
        $definitions = array();
        
        foreach ( $this->tools as $name => $tool ) {
            $definitions[] = array(
                'type' => 'function',
                'function' => array(
                    'name' => $name,
                    'description' => $tool->get_description(),
                    'parameters' => array(
                        'type' => 'object',
                        'properties' => $tool->get_parameters(),
                        'required' => $this->get_required_parameters( $tool ),
                    ),
                ),
            );
        }
        
        return $definitions;
    }

    /**
     * Get the required parameters for a tool.
     *
     * @param WP_NLC_Base_Tool $tool The tool.
     * @return array The required parameters.
     */
    private function get_required_parameters( $tool ) {
        $required = array();
        $parameters = $tool->get_parameters();
        
        foreach ( $parameters as $name => $param ) {
            if ( isset( $param['required'] ) && $param['required'] === true ) {
                $required[] = $name;
            }
        }
        
        return $required;
    }

    /**
     * Execute a tool by name with the given parameters.
     *
     * @param string $name The name of the tool to execute.
     * @param array $params The parameters to use when executing the tool.
     * @return array|WP_Error The result of executing the tool, or WP_Error on failure.
     */
    public function execute_tool( $name, $params ) {
        $tool = $this->get_tool( $name );
        
        if ( ! $tool ) {
            return new WP_Error(
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
