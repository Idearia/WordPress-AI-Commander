# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

### Build and Development
- **Run static analysis**: `composer phpstan`
- **Generate WordPress readme**: `composer readme`
- **Build distribution package**: `composer build`

### Internationalization (i18n)
- **Generate POT template**: `wp i18n make-pot . languages/ai-commander.pot --domain=ai-commander --exclude=vendor,node_modules,mobile`
- **Compile PO to MO files**: `wp i18n make-mo languages/`
- **Create JSON files for JS**: `wp i18n make-json languages/ --no-purge`
- **Mobile translations**: Handled via `MobileTranslations.php` service and REST API endpoint

### Mobile App Development
- **Install dependencies**: `cd mobile && npm install`
- **Development with WordPress backend**:
  ```bash
  cp mobile/.env.example mobile/.env.local
  # Edit .env.local and set VITE_WP_BASE_URL
  cd mobile && npm run dev
  ```
- **Build for production**: `cd mobile && npm run build` (outputs to `mobile/app/`)
- **Lint code**: `cd mobile && npm run lint`
- **Format code**: `cd mobile && npm run format`
- **Primary access**: Through WordPress at configured path (e.g. `/ai-commander/assistant`)
- **Legacy access**: `mobile/index.html` (redirects to `mobile/app/index.html`)

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
5. **PwaPage** (includes/PwaPage.php): Serves the mobile PWA with embedded WordPress configuration

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
- **Mobile app**: React PWA in `mobile/` directory
  - Built with React, TypeScript, and Vite
  - Entry point: `mobile/src/main.tsx` (React), `mobile/src/main.ts` (legacy)
  - Production build: `mobile/app/` (committed to repo)
  - User access: `mobile/index.html` (redirects to app)
  - Translation system: Runtime loading from WordPress backend

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
  - `/translations`: Get mobile app translations
  - `/manifest`: Get dynamic PWA manifest (multilingual)

### PWA Serving
- **PwaPage.php**: Serves mobile PWA at configurable path (default: `/ai-commander/assistant`)
- **Embedded config**: Injects `window.AI_COMMANDER_CONFIG` with translations, manifest, and base URL
- **Admin settings**: PWA path configurable in Settings → Mobile Web App Settings
- **Filter hooks**: `ai_commander_filter_pwa_*` for customization

### Mobile App Architecture
The mobile app (`mobile/` directory) is a React-based TypeScript PWA:

**React Components**:
- `App`: Main React component handling service initialization and navigation
- `ConfigScreen`: WordPress site configuration and authentication
- `MainApp`: Primary voice assistant interface with settings
- `MicButton`: Microphone button with press-and-hold detection
- `ChatContainer`: Message history and conversation display

**State Management**:
- `AppContext`: React Context with useReducer for centralized state
- `useTranslation`: Custom hook for accessing translations
- `TranslationProvider`: Context provider for translation services

**Services (Legacy Integration)**:
- `ApiService`: WordPress REST API client
- `AudioService`: Mobile audio unlocking and custom TTS playback
- `WebRTCService`: OpenAI Realtime API WebRTC handling
- `SessionManager`: Orchestrates WebRTC sessions and tool execution (with React bridge)
- `TranslationService`: Handles translation loading from WordPress backend

**Key Features**:
- React-based modern UI with TypeScript
- Comprehensive internationalization with WordPress .po/.mo integration
- Runtime translation loading from WordPress REST API
- WebRTC connection to OpenAI Realtime API
- Custom TTS when audio modality is disabled
- Mobile audio unlocking for iOS/Android
- Tool execution through WordPress backend
- Lazy service initialization for better performance
- SessionManager bridge for React-service integration

**Authentication Flows**:
1. **WordPress-served** (primary): Users access mobile app at `/ai-commander/assistant`, only enter credentials
2. **Development mode**: Set `VITE_WP_BASE_URL` in `.env.local`, only enter credentials
3. **Legacy mode**: Manual entry of WordPress URL, username, and app password

### Security
- WordPress nonce verification for AJAX requests
- Capability checks for tool execution (default: `edit_posts`)
- Application password authentication for REST API
- User-specific conversation isolation
- CORS headers restricted to `/ai-commander/v1/` endpoints only
