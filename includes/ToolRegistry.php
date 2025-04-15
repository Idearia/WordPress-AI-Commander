<?php
/**
 * Tool Registry Class
 *
 * @package AICommander
 */

namespace AICommander\Includes;

use AICommander\Tools\BaseTool;

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
     * Remove a tool from the registry.
     *
     * @param string $name The name of the tool to remove.
     * @return bool True if the tool was removed, false if it wasn't registered.
     */
    public function unregister_tool( $name ) {
        if ( ! isset( $this->tools[ $name ] ) ) {
            return false;
        }
        
        unset( $this->tools[ $name ] );
        return true;
    }

    /**
     * Remove all tools from the registry.
     *
     * @param array $exceptions Array of tool names to keep in the registry.
     * @return int Number of tools removed.
     */
    public function unregister_all_tools( $exceptions = array() ) {
        $removed_count = 0;
        
        foreach ( array_keys( $this->tools ) as $tool_name ) {
            if ( ! in_array( $tool_name, $exceptions ) ) {
                unset( $this->tools[ $tool_name ] );
                $removed_count++;
            }
        }
        
        return $removed_count;
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
     * Documentation:
     * - Chat completion API: https://platform.openai.com/docs/guides/function-calling
     * - Realtime API: https://platform.openai.com/docs/guides/realtime-conversations#configure-callable-functions
     *
     * @param string $format The format of the tool definitions, possible values:
     * - 'chat_completion': for chat completion API
     * - 'realtime': for Realtime API
     * @return array The tool definitions.
     */
    public function get_tool_definitions( $format = 'chat_completion' ) {
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

            $parameters = array(
                'type' => 'object',
                // In strict mode, all parameters must be listed in the 'required' property.
                'required' => array_keys( $tool->get_parameters() ),
                'properties' => $properties,
            );

            if ( $format === 'chat_completion' ) {
                $definitions[] = array(
                    'strict' => true,
                    'type' => 'function',
                    'function' => array(
                        'name' => $name,
                        'description' => $tool->get_description(),
                        'parameters' => $parameters,
                    ),
                );
            } else if ( $format === 'realtime' ) {
                $definitions[] = array(
                    'type' => 'function',
                    'name' => $name,
                    'description' => $tool->get_description(),
                    'parameters' => $parameters,
                );
            }
            else {
                throw new \Exception( 'Invalid format for generating tool definitions' );
            }
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
        
        return $tool->execute_with_permission_check( $params );
    }
}
