# WP Natural Language Commands

Issue commands in natural language to WordPress via a chatbot interface. Integrates with OpenAI's API to process natural language commands and execute the appropriate WordPress actions.

## Features

- **Natural Language Interface**: Interact with WordPress using conversational language
- **Chatbot Interface**: User-friendly chat interface in the WordPress admin area
- **Conversation History**: Maintains context across multiple commands in a conversation
- **Extensible Tool System**: Add new tools and capabilities to control plugins and themes

## Installation

1. Upload the `wp-natural-language-commands` directory to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'NL Commands > Settings' and insert your OpenAI API key
4. Go to the 'NL Commands > Chatbot' and try a test command, e.g. create a "Hello world" post with tag "Testing".

## Demo video

https://github.com/user-attachments/assets/fe01dd48-9fea-44b2-9023-85291cc70ec2

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
- **Description**: Creates a new WordPress post
- **Functionality**: Creates posts with title, content, excerpt, categories, tags, and more
- **Example prompt**: "Create a new 'Hello world' post as a draft with tag 'Testing'"

### Content Retrieval Tool
- **Name**: `retrieve_content`
- **Description**: Retrieves WordPress content based on various criteria
- **Functionality**: Searches and filters posts, pages, and custom post types by author, category, tag, status, etc.
- **Example prompt**: "Show all drafts with tag 'Testing'"

### Post Editing Tool
- **Name**: `edit_post`
- **Description**: Edits an existing WordPress post
- **Functionality**: Updates post title, content, excerpt, status, categories, tags, etc.
- **Example prompt**: "Edit the post with title 'Hello world' and set the status to 'Published'"

### Content Organization Tool
- **Name**: `organize_content`
- **Description**: Organizes WordPress content
- **Functionality**: Manages categories, tags, and other taxonomies
- **Example prompt**: "Create a new category 'Testing category' and assign it to all posts with title 'Hello world'"

### Site Information Tool
- **Name**: `get_site_info`
- **Description**: Retrieves basic WordPress site information
- **Functionality**: Gets site title, URL, tagline, and multisite information
- **Example prompt**: "Show site information"

## How to Add New Tools from Another Theme or Plugin

You can extend the plugin's functionality by adding new tools from your own theme or plugin. Here's how:

### 1. Create a New Tool Class

Create a new PHP file in your theme or plugin, for example `SimplePageCreationTool.php`. Your tool class should extend the `BaseTool` class from the WP Natural Language Commands plugin:

```php
<?php
use WPNaturalLanguageCommands\Tools\BaseTool;

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
        // Validate parameters
        $validation = $this->validate_parameters($params);
        if ($validation instanceof \WP_Error) {
            return $validation;
        }

        // Apply default values
        $params = $this->apply_parameter_defaults( $params );

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
        
        if (isset($result['message'])) {
            return $result['message'];
        }
        
        return sprintf('Empty page "%s" created successfully with ID %d.', $params['title'], $result['page_id']);
    }
}
```

### 2. Register Your Tool

In your theme's `functions.php` file or your plugin file, add code to register your tool when WordPress initializes:

```php
/**
 * Register custom tools with WP Natural Language Commands
 */
function register_custom_nlc_tools() {
    // Make sure WP Natural Language Commands plugin is active
    if (!class_exists('WPNaturalLanguageCommands\\Includes\\ToolRegistry')) {
        return;
    }
    
    // Include your custom tool class
    require_once 'path/to/your/SimplePageCreationTool.php';
    
    // Instantiate your tool (this will automatically register it)
    new SimplePageCreationTool();
}
add_action('init', 'register_custom_nlc_tools', 20); // Priority 20 to ensure it runs after the plugin's tools are registered
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
GET /wp-json/wp-nlc/v1/conversations
```

Response:

```json
[
  {
    "conversation_uuid": "12345678-1234-1234-1234-123456789012",
    "created_at": "2025-02-27 21:00:00",
    "updated_at": "2025-02-27 21:05:00",
    "preview": "Create a new post titled 'Hello World'"
  },
  {
    "conversation_uuid": "87654321-4321-4321-4321-210987654321",
    "created_at": "2025-02-26 15:30:00",
    "updated_at": "2025-02-26 15:35:00",
    "preview": "List all posts in the 'News' category"
  }
]
```

#### Create a new conversation

```
POST /wp-json/wp-nlc/v1/conversations
```

Request body:

```json
{
  "command": "Create a new post titled 'Hello World'"
}
```

Response:

```json
{
  "success": true,
  "message": "I've created a new post titled 'Hello World'.",
  "actions": [
    {
      "tool": "create_post",
      "arguments": {
        "title": "Hello World"
      },
      "result": {
        "post_id": 123,
        "post_url": "https://example.com/hello-world"
      },
      "title": "Executed create_post successfully.",
      "summary": "Created post 'Hello World' with ID 123."
    }
  ],
  "conversation_uuid": "12345678-1234-1234-1234-123456789012"
}
```

#### Get an existing conversation

```
GET /wp-json/wp-nlc/v1/conversations/{uuid}
```

Response:

```json
{
  "conversation_uuid": "12345678-1234-1234-1234-123456789012",
  "messages": [
    {
      "role": "assistant",
      "content": "Hello! I'm your WordPress assistant. How can I help you today?"
    },
    {
      "role": "user",
      "content": "Create a new post titled 'Hello World'"
    },
    {
      "role": "assistant",
      "content": "I've created a new post titled 'Hello World'."
    }
  ]
}
```

#### Add a command to an existing conversation

```
POST /wp-json/wp-nlc/v1/conversations/{uuid}/commands
```

Request body:

```json
{
  "command": "Create a new post titled 'Hello World'"
}
```

Response:

```json
{
  "success": true,
  "message": "I've created a new post titled 'Hello World'.",
  "actions": [
    {
      "tool": "create_post",
      "arguments": {
        "title": "Hello World"
      },
      "result": {
        "post_id": 123,
        "post_url": "https://example.com/hello-world"
      },
      "title": "Executed create_post successfully.",
      "summary": "Created post 'Hello World' with ID 123."
    }
  ],
  "conversation_uuid": "12345678-1234-1234-1234-123456789012"
}
```

### Postman Collection

A Postman collection is included in the plugin to help you test and interact with the REST API. You can find it in the `postman_collection.json` file in the plugin directory.

To use the collection:

1. Import the `postman_collection.json` file into Postman
2. Set up the collection variables:
   - `baseUrl`: Your WordPress site URL (e.g., `http://localhost/wordpress`)
   - `username`: Your WordPress username
   - `password`: Your WordPress application password
3. You can now use the collection to test the REST API endpoints

## To do

Important features to add:
- add voice control
- add WP CLI command
- tool to manage users

Nice to have features:
- add system prompt to option pages
- allow to filter system prompt
- allow links and newlines in chatbot responses

Bug fixes:
- test user roles permissions
- is `result['message']` needed?
