# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Build and Development
- **Run static analysis**: `composer phpstan`
- **Generate WordPress readme**: `composer readme`
- **Build distribution package**: `composer build`

### Mobile App Development
- **Install dependencies**: `cd mobile && npm install`
- **Development server**: `cd mobile && npm run dev`
- **Build for production**: `cd mobile && npm run build` (outputs to `mobile/app/`)
- **Lint code**: `cd mobile && npm run lint`
- **Format code**: `cd mobile && npm run format`
- **Access URL**: `mobile/index.html` (redirects to `mobile/app/index.html`)

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
- **Mobile app**: TypeScript PWA in `mobile/` directory
  - Built with Vite and TypeScript
  - Entry point: `mobile/src/main.ts`
  - Production build: `mobile/app/` (committed to repo)
  - User access: `mobile/index.html` (redirects to app)

### API Endpoints
- **AJAX handlers**: `wp-admin/admin-ajax.php` actions prefixed with `ai_commander_`
- **REST API**: Custom endpoints under `/wp-json/ai-commander/v1/`
  - `/command`: Process text commands
  - `/transcribe`: Transcribe audio to text
  - `/voice-command`: Process voice commands
  - `/conversations`: Get user conversations
  - `/read-text`: Text-to-speech (returns MP3 audio)
  - `/realtime/session`: Create OpenAI Realtime session
  - `/realtime/tool`: Execute tools for Realtime API

### Mobile App Architecture
The mobile app (`mobile/` directory) is a TypeScript-based PWA:

**Services**:
- `ApiService`: WordPress REST API client
- `AudioService`: Mobile audio unlocking and custom TTS playback
- `WebRTCService`: OpenAI Realtime API WebRTC handling
- `SessionManager`: Orchestrates WebRTC sessions and tool execution
- `StateManager`: Centralized state management with observers

**Components**:
- `App`: Main application controller
- `UIController`: DOM manipulation and UI updates
- `MicButtonController`: Microphone button state machine

**Key Features**:
- WebRTC connection to OpenAI Realtime API
- Custom TTS when audio modality is disabled
- Mobile audio unlocking for iOS/Android
- Tool execution through WordPress backend
- Lazy service initialization for better performance

### Security
- WordPress nonce verification for AJAX requests
- Capability checks for tool execution (default: `edit_posts`)
- Application password authentication for REST API
- User-specific conversation isolation
- CORS headers restricted to `/ai-commander/v1/` endpoints only