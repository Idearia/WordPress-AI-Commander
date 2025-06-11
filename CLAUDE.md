# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Build and Development
- **Run static analysis**: `composer phpstan`
- **Generate WordPress readme**: `composer readme`
- **Build distribution package**: `composer build`

### Testing
- **Static analysis**: `composer phpstan` (PHPStan level 3 with WordPress rules)

## Architecture Overview

This is a WordPress plugin that provides AI-powered content management through chat and voice interfaces. Key architectural patterns:

### Plugin Structure
- **Namespace**: All PHP classes use `AICommander` namespace
- **Entry point**: `ai-commander.php` handles plugin lifecycle
- **Admin pages**: Located in `admin/` directory, extend `AdminPage` base class
- **Core logic**: `includes/` contains business logic and services
- **Tools**: `tools/` directory contains WordPress action tools extending `BaseTool`

### Key Components
1. **ToolRegistry** (includes/ToolRegistry.php): Singleton managing tool registration and execution
2. **CommandProcessor** (includes/CommandProcessor.php): Processes natural language commands via OpenAI
3. **ConversationManager** (includes/ConversationManager.php): Handles conversation history and database persistence
4. **OpenaiClient** (includes/OpenaiClient.php): Wrapper for OpenAI API interactions

### Tool System
Tools extend `BaseTool` and are auto-registered. Each tool must implement:
- `get_name()`: Unique identifier
- `get_description()`: OpenAI function description
- `execute($args)`: Main execution logic
- `get_parameters()`: Parameter schema for OpenAI

### Database Schema
Two custom tables created on activation:
- `wp_ai_commander_conversations`: Stores conversation metadata
- `wp_ai_commander_messages`: Stores individual messages and tool results

### Frontend Components
- **Chat interface**: React component in `assets/js/react-chat-interface.js`
- **Realtime interface**: React component in `assets/js/react-realtime-interface.js`
- **Mobile app**: Standalone PWA in `mobile/index.html`

### API Endpoints
- **AJAX handlers**: `wp-admin/admin-ajax.php` actions prefixed with `ai_commander_`
- **REST API**: Custom endpoints under `/wp-json/ai-commander/v1/`

### Security
- WordPress nonce verification for AJAX requests
- Capability checks for tool execution (default: `edit_posts`)
- Application password authentication for REST API
- User-specific conversation isolation