# CLAUDE.md - AI Assistant Reference

## Commands
- Static Analysis: `composer phpstan` (uses level 3)
- Manual Testing: Install plugin in a WordPress environment and use admin interface

## Code Style Guidelines
- **Namespaces**: Use `WPNL` namespace with subnamespaces for components (`WPNL\Tools`, `WPNL\Includes`)
- **Class Names**: PascalCase (e.g., `BaseTool`, `PostCreationTool`)
- **Method Names**: camelCase (e.g., `execute()`, `validateParameters()`)
- **Variables**: snake_case (e.g., `$post_id`, `$tool_name`)
- **Returns**: WordPress conventions with `\WP_Error` for failures
- **Type Annotations**: Use PHPDoc for parameters and return types
- **Inheritance**: Extend `BaseTool` for new tools implementing `execute()` method
- **Parameter Validation**: Use validation in constructor or dedicated methods
- **Error Handling**: Return WordPress error objects with descriptive messages
- **Documentation**: Include docblocks for classes and methods