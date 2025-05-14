<?php

/**
 * Markdown to WordPress readme.txt Converter
 * 
 * This script converts a Markdown README.md file to the WordPress readme.txt format
 * required for plugin submissions to the WordPress.org plugin directory.
 *
 * Docs: https://wordpress.org/plugins/developers/#readme
 */

// Configuration
$input_file = 'README.md';
$output_file = 'readme.txt';

// Read the Markdown file
$markdown_content = file_get_contents($input_file);
if ($markdown_content === false) {
    die("Error: Could not read file $input_file\n");
}

// Extract the plugin name from the first line
preg_match('/^# (.*?)$/m', $markdown_content, $title_matches);
$plugin_name = trim($title_matches[1]);

// Extract the short description (text after the title and before the first ##)
preg_match('/^# .*?$(.*?)^##/ms', $markdown_content, $desc_matches);
$short_description = trim($desc_matches[1]);

// Extract WordPress metadata
preg_match('/```wordpress-metadata(.*?)```/s', $markdown_content, $metadata_matches);
if (!empty($metadata_matches[1])) {
    $metadata_text = trim($metadata_matches[1]);
    // Remove the WordPress metadata section from the content
    $markdown_content = str_replace($metadata_matches[0], '', $markdown_content);
    // Remove the `## WordPress Metadata` heading
    $markdown_content = str_replace('## WordPress Metadata', '', $markdown_content);
} else {
    die("Error: WordPress metadata section not found in $input_file\n");
}

// Start building the readme.txt content
$readme_content = "=== $plugin_name ===\n";

// Add metadata
$readme_content .= $metadata_text . "\n\n";

// Add short description
$readme_content .= $short_description . "\n\n";

// Get the main content (everything except the title and short description)
// Without stripping the initial ## !
$content = '##' . preg_replace('/^# .*?$(.*?)^##/ms', '', $markdown_content, 1);

// Remove To do and Installation sections
$content = remove_section($content, 'To do');
$content = remove_section($content, 'Installation');

// Convert Markdown headers to WordPress readme.txt section headers
$content = preg_replace('/^## (.*?)$/m', '== $1 ==', $content);
$content = preg_replace('/^### (.*?)$/m', '= $1 =', $content);
$content = preg_replace('/^#### (.*?)$/m', '= $1 =', $content);

// Add the processed content
$readme_content .= $content;

// Write the readme.txt file
if (file_put_contents($output_file, $readme_content) === false) {
    die("Error: Could not write to file $output_file\n");
}

echo "Conversion complete! WordPress readme.txt file created successfully.\n";


/**
 * Remove a section from a markdown string.  This works
 * also if the section is the last one, as it uses a regex
 * that goes all the way to the end of the file if it is
 * not followed by another section header.
 */
function remove_section($markdown_content, $section_name, $prefix = '##')
{
    // Escape special characters in the section name for regex
    $escaped_section_name = preg_quote($section_name, '/');

    // Pattern to match the section and its content up to the next section or end of string
    $pattern = "/$prefix\\s*$escaped_section_name\\s*$(.*?)(?=$prefix|\\Z)/ms";

    // Remove the matched section and its content
    return preg_replace($pattern, '', $markdown_content);
}
