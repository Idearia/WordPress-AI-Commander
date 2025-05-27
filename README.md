# AI Commander

Control WordPress with natural language or voice, with API support.  Uses OpenAI to process commands and execute the appropriate WordPress actions.

## Features

- **Conversational assistant**: Command WordPress using your voice, powered by OpenAI Realtime API
- **Chatbot assistant**: In alternative, issue commands via text, using a user-friendly chat interface
- **Conversation history**: Maintains context across multiple commands in a conversation
- **Extensible tool system**: Add new tools and capabilities to control plugins and themes
- **REST API**: Allows remote interaction with the chatbot via API

## Installation

1. Upload the `ai-commander` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'AI Commander > Settings' and insert your OpenAI API key
4. Go to the 'AI Commander > Chatbot' and try a test command, e.g. create a "Hello world" post with tag "Testing".

## Demo video

- Voice assistant demo video: _TO BE ADDED_
- Chatbot assistant demo video: https://github.com/user-attachments/assets/824745f7-0bfe-4bb1-b7f8-c8b0c48eaeae

## Example conversation

Run the following commands in the chatbot, one after the other:

```
> Create a draft on how GPUs are used to train AI models; keep it short
> Add the "AI generated" tag to the post
> Add a paragraph to the post discussing the role of NVIDIA in GPU manifacturing
> Publish the post
```

## Available Tools

The plugin comes with several built-in tools:

### Post Creation Tool
- **Name**: `create_post`
- **Functionality**: Creates posts with title, content, excerpt, categories, tags, and more
- **Example prompt**: "Create a new 'Hello world' post as a draft with tag 'Testing'"

### Content Retrieval Tool
- **Name**: `retrieve_content`
- **Functionality**: Searches and filters posts, pages, and custom post types by author, category, tag, status, etc.
- **Example prompt**: "Show all drafts with tag 'Testing'"

### Post Editing Tool
- **Name**: `edit_post`
- **Functionality**: Updates post title, content, excerpt, status, categories, tags, etc.
- **Example prompt**: "Edit the post with title 'Hello world' and set the status to 'Published'"

### Content Organization Tool
- **Name**: `organize_content`
- **Functionality**: Manages categories, tags, and other taxonomies
- **Example prompt**: "Create a new category 'Testing category' and assign it to all posts with title 'Hello world'"

### Site Information Tool
- **Name**: `get_site_info`
- **Functionality**: Gets site title, URL, tagline, and multisite information
- **Example prompt**: "Show site information"

### Date Tool
- **Name**: `get_today_date`
- **Functionality**: Returns the current date in ISO 8601 format, together with the day of the week
- **Example prompt**: "Show me all posts published last week"

## How to Add New Tools from Another Theme or Plugin

You can extend the plugin's functionality by adding new tools from your own theme or plugin. Here's how:

### 1. Create a New Tool Class

Create a new PHP file in your theme or plugin, for example `SimplePageCreationTool.php`. Your tool class should extend the `BaseTool` class from the AI Commander plugin:

```php
<?php
use AICommander\Tools\BaseTool;

/**
 * Tool to create an empty WordPress page with just a title.
 */
class SimplePageCreationTool extends BaseTool {

    public function __construct() {
        $this->name = 'create_empty_page';
        $this->description = 'Creates an empty WordPress page with the specified title';
        $this->required_capability = 'publish_pages'; // Only users who can publish pages can use this tool

        parent::__construct();
    }

    /**
     * Get the tool parameters for OpenAI function calling.
     */
    public function get_parameters() {
        return array(
            'title' => array(
                'type' => 'string',
                'description' => 'The title of the page to create',
                'required' => false,
                'default' => 'New Page',
            ),
        );
    }

    /**
     * Execute the tool with the given parameters.
     */
    public function execute($params) {
        // Create the page
        $page_data = array(
            'post_title'    => sanitize_text_field($params['title']),
            'post_content'  => '',
            'post_status'   => 'draft',
            'post_type'     => 'page',
        );

        // Insert the page
        $page_id = wp_insert_post($page_data, true);

        if ($page_id instanceof \WP_Error) {
            return $page_id;
        }

        // Get the page URL and edit URL
        $page_url = get_permalink($page_id);
        $edit_url = get_edit_post_link($page_id, 'raw');

        // Return the result
        return array(
            'success' => true,
            'page_id' => $page_id,
            'page_url' => $page_url,
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
    public function get_result_summary($result, $params) {
        if (is_wp_error($result)) {
            return $result->get_error_message();
        }

        return sprintf('Empty page "%s" created successfully with ID %d.', $params['title'], $result['page_id']);
    }
}
```

### 2. Register Your Tool

In your theme's `functions.php` file or your plugin file, add code to register your tool when WordPress initializes:

```php
/**
 * Register custom tools for AI Commander
 */
function register_custom_ai_commander_tools() {
    // Make sure AI Commander plugin is active
    if (!class_exists('AICommander\\Includes\\ToolRegistry')) {
        return;
    }

    // Include your custom tool class
    require_once 'path/to/your/SimplePageCreationTool.php';

    // Instantiate your tool (this will automatically register it)
    new SimplePageCreationTool();
}
add_action('init', 'register_custom_ai_commander_tools', 10);
```

### 3. Best Practices

- **OpenAI guidelines**: When defining your tools, follow the [OpenAI guidelines](https://platform.openai.com/docs/guides/function-calling) for tool creation.
- **Descriptive Names**: Use clear, descriptive names for your tools and parameters
- **Thorough Validation**: Always validate input parameters before executing your tool
- **Helpful Error Messages**: Return informative error messages when something goes wrong
- **Detailed Descriptions**: Provide detailed descriptions for your tool and its parameters
- **Meaningful Results**: Return structured results that include a success status and message
- **Human-Readable Summaries**: Implement the `get_result_summary` method to provide user-friendly summaries of your tool's actions
- **Appropriate Capabilities**: Set the `required_capability` property to ensure users can only execute tools they have permission to use; [here's a nice table of WordPress roles and capabilities](https://wordpress.org/documentation/article/roles-and-capabilities/#capability-vs-role-table)

## Action Buttons for Tool Messages

The plugin supports adding interactive action buttons to tool messages in the chatbot interface. These buttons allow users to perform additional actions related to the tool's result, such as viewing or editing a post, opening a modal with more information, or sending AJAX requests.

### Types of Action Buttons

The system supports three types of action buttons:

1. **Link buttons**: Open URLs in a new tab
2. **Modal buttons**: Open a modal with HTML content
3. **AJAX buttons**: Send AJAX requests with visual feedback

### Adding Action Buttons to Your Tool

To add action buttons to your tool, override the `get_action_buttons` method in your tool class:

```php
/**
 * Get action buttons for the tool execution result.
 *
 * @param array|\WP_Error $result The result of executing the tool.
 * @param array $params The parameters used when executing the tool.
 * @return array Array of action button definitions.
 */
public function get_action_buttons($result, $params) {
    if (is_wp_error($result)) {
        return array();
    }

    $buttons = array();

    // Example: Add a link button
    if (!empty($result['some_url'])) {
        $buttons[] = array(
            'type' => 'link',
            'label' => 'View Item',
            'url' => $result['some_url'],
            'target' => '_blank',
        );
    }

    // Example: Add a modal button
    $buttons[] = array(
        'type' => 'modal',
        'label' => 'View Details',
        'title' => 'Item Details',
        'content' => '<h2>Details</h2><p>Here are the details of the item.</p>',
    );

    // Example: Add an AJAX button
    $buttons[] = array(
        'type' => 'ajax',
        'label' => 'Delete Item',
        'url' => admin_url('admin-ajax.php'),
        'method' => 'POST',
        'data' => array(
            'action' => 'my_delete_action',
            'item_id' => $result['item_id'],
            'nonce' => wp_create_nonce('my_delete_action'),
        ),
        'confirmMessage' => 'Are you sure you want to delete this item?',
        'loadingText' => 'Deleting...',
        'responseAction' => 'message',
        'successMessage' => 'Item deleted successfully!',
    );

    return $buttons;
}
```

### Button Configuration Options

#### Link Button Options

```php
array(
    'type' => 'link',
    'label' => 'Button Label',
    'url' => 'https://example.com',
    'target' => '_blank', // Optional, defaults to '_blank'
)
```

#### Modal Button Options

```php
array(
    'type' => 'modal',
    'label' => 'Button Label',
    'title' => 'Modal Title', // Optional, defaults to 'Details'
    'content' => '<p>HTML content for the modal</p>',
)
```

#### AJAX Button Options

```php
array(
    'type' => 'ajax',
    'label' => 'Button Label',
    'url' => admin_url('admin-ajax.php'),
    'method' => 'POST', // Optional, defaults to 'POST'
    'data' => array(
        'action' => 'my_ajax_action',
        'param1' => 'value1',
        'nonce' => wp_create_nonce('my_ajax_action'),
    ),
    'confirmMessage' => 'Are you sure?', // Optional confirmation message
    'loadingText' => 'Processing...', // Optional text to show during AJAX request
    'responseAction' => 'message', // How to handle the response
    'successMessage' => 'Operation completed successfully!', // For 'message' responseAction
    'redirectUrl' => 'https://example.com', // For 'redirect' responseAction
    'modalTitle' => 'Response', // For 'modal' responseAction
)
```

### AJAX Response Handling

For AJAX buttons, you can specify how to handle the response using the `responseAction` property:

- **refresh**: Reload the current page
- **redirect**: Navigate to a URL from the response or the `redirectUrl` property
- **message**: Display a success message from the response or the `successMessage` property
- **update**: Update specific elements on the page with content from the response
- **modal**: Show the response in a modal
- **custom**: Execute a custom callback function

### Example: Response Format for 'update' Action

If you're using the 'update' responseAction, your AJAX handler should return a response in this format:

```php
wp_send_json_success(array(
    'updates' => array(
        '#element-id-1' => '<p>New content for element 1</p>',
        '.element-class' => '<div>New content for elements with this class</div>',
    )
));
```

## REST API

The plugin provides a REST API that allows you to interact with the chatbot remotely. This is useful for integrating the chatbot with external applications or creating custom interfaces.

### Authentication

The REST API uses WordPress application passwords for authentication. To use the API, you need to create an application password for your WordPress user:

1. Go to your WordPress profile page
2. Scroll down to "Application Passwords"
3. Enter a name for the application (e.g., "Chatbot API Client")
4. Click "Add New Application Password"
5. Copy the generated password (you won't be able to see it again)

Then use Basic Authentication with your requests:

```
Authorization: Basic base64encode(username:application_password)
```

### Endpoints

#### Get all conversations

```
GET /wp-json/ai-commander/v1/conversations
```

#### Process a command

```
POST /wp-json/ai-commander/v1/commands
```

Request body:

```json
{
  "command": "Create a new draft post titled 'Hello World'",
  "conversation_uuid": "optional-existing-conversation-id"
}
```

This endpoint handles both creating a new conversation (when `conversation_uuid` is omitted) and adding a command to an existing conversation (when `conversation_uuid` is provided).

#### Get an existing conversation

```
GET /wp-json/ai-commander/v1/conversations/{uuid}
```

#### Transcribe audio

```
POST /wp-json/ai-commander/v1/transcribe
```

Request body (multipart/form-data):

```
audio: [audio file]
language: [optional language code, e.g., 'en']
```

Response:

```json
{
  "transcription": "The transcribed text from the audio file"
}
```

#### Process voice command

```
POST /wp-json/ai-commander/v1/voice-command
```

Request body (multipart/form-data):

```
audio: [audio file]
conversation_uuid: [optional conversation UUID]
language: [optional language code, e.g., 'en']
```

This endpoint combines audio transcription and command processing in a single request.

#### Create realtime session

```
POST /wp-json/ai-commander/v1/realtime/session
```

Creates a new OpenAI Realtime session with the configured model, voice, and tools. Returns session data including an ephemeral token for WebRTC connection.

Request body:

```json
{}
```

Response:

```json
{
  "id": "sess_abc123",
  "object": "realtime.session",
  "model": "gpt-4o-realtime-preview-2024-12-17",
  "expires_at": 1640995200,
  "client_secret": {
    "value": "ephemeral_token_here",
    "expires_at": 1640995200
  }
}
```

#### Execute realtime tool

```
POST /wp-json/ai-commander/v1/realtime/tool
```

Executes a tool with the provided arguments. This endpoint is typically used by the OpenAI Realtime API to execute function calls.

Request body:

```json
{
  "tool_name": "create_post",
  "arguments": "{\"title\": \"Test Post\", \"content\": \"This is a test post.\", \"status\": \"draft\"}"
}
```

Response:

```json
{
  "success": true,
  "post_id": 123,
  "post_title": "Test Post",
  "post_url": "https://example.com/?p=123",
  "edit_url": "https://example.com/wp-admin/post.php?post=123&action=edit"
}
```

### Postman Collection

A Postman collection with example responses is included in [`postman_collection.json`](./postman_collection.json).

To use the collection:

1. Import the `postman_collection.json` file into Postman
2. Set up the collection variables:
   - `baseUrl`: Your WordPress site URL
   - `username`: Your WordPress username
   - `password`: Your WordPress application password
3. You can now use the collection to test the REST API endpoints

## Frequently Asked Questions

### Does this plugin require an OpenAI API key?

Yes, you need an OpenAI API key to use this plugin. You can get one from the [OpenAI website](https://platform.openai.com/).

### Which OpenAI models are supported?

The plugin works with GPT-4o by default, but you can configure it to use other models that support function calling.

### Is my data secure?

The plugin is entirely hosted on your own WordPress site. Your commands are sent to OpenAI's API for processing. Please review [OpenAI's privacy policy](https://openai.com/policies/privacy-policy) to understand how they handle your data.

## Changelog

### 1.0.0
* Initial release

## Upgrade Notice

### 1.0.0
Initial release of the AI Commander plugin.

## License

This plugin is licensed under the GNU General Public License version 2 (GPLv2) or, at your option, any later version. You should have received a copy of the GNU General Public License along with this plugin. If not, see <https://www.gnu.org/licenses/>.

## To do

Important features to add:
- allow to give back control to the user from within the tool call execution, e.g. "You asked to delete post XYZ, do you want to proceed?"  or  "You asked to modify post with title 'Hello world', but there are two of them.  Which one do you want to modify?"
- processing spinner should show what is being done, not as an intertitial, but in-chat like Claude and ChatGPT do
- add WP CLI command

Nice to have features:
- web search or Perplexity integration, e.g. create a new post on latest news on AI

Bug fixes:
- After a failed tool call it is impossible to send another command due to OpenAI error > Error: OpenAI API error (400): An assistant message with 'tool_calls' must be followed by tool messages responding to each 'tool_call_id'. The following tool_call_ids did not have response messages: call_c8lhBMxiUzRmAGywvfpl5ayH

## WordPress Metadata

```wordpress-metadata
Contributors: ideariasrl, guidopettinari, coccoinomane
Tags: ai, chatbot, natural language, openai, ai-agent
Requires at least: 6.0
Tested up to: 6.7.2
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
```
