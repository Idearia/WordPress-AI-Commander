# AI Commander Mobile App

This is the React-based mobile voice assistant for AI Commander WordPress plugin. The app provides real-time voice interaction with your WordPress site through OpenAI's Realtime API and includes comprehensive internationalization support.

## Development Setup

1. Install dependencies:
```bash
npm install
```

2. (Optional) Configure WordPress connection for development:
```bash
cp .env.example .env.local
# Edit .env.local and set VITE_WP_BASE_URL to your WordPress site
```

3. Start development server:
```bash
npm run dev
```

4. Build for production:
```bash
npm run build
```

### Development Modes

- **With .env.local**: App only asks for username/password, uses configured WordPress URL
- **Without .env.local**: App asks for WordPress URL, username, and password (legacy mode)
- **WordPress-served**: When accessing through WordPress, all config is embedded automatically

## Project Structure

```
mobile/
├── src/
│   ├── components/     # UI components
│   ├── services/       # Business logic services
│   ├── types/          # TypeScript type definitions
│   ├── utils/          # Utility functions and constants
│   ├── styles/         # CSS styles
│   ├── main.tsx        # React application entry point
│   ├── main.ts         # Legacy entry point (deprecated)
│   └── index.html      # HTML template
├── public/             # Static assets (manifest, icons)
├── app/                # Production build (committed to repo)
├── index.html          # Redirect to app/index.html
└── package.json        # Dependencies and scripts
```

## Available Scripts

- `npm run dev` - Start Vite development server
- `npm run build` - Build for production
- `npm run preview` - Preview production build
- `npm run type-check` - Run TypeScript type checking
- `npm run lint` - Run ESLint
- `npm run lint:fix` - Fix ESLint issues
- `npm run format` - Format code with Prettier
- `npm run format:check` - Check code formatting

## Key Features

- **React & TypeScript** for modern component-based UI with type safety
- **Internationalization (i18n)** with WordPress .po/.mo file integration
- **Real-time Translation Loading** from WordPress REST API
- **Vite** for fast development and optimized builds
- **ESLint & Prettier** for code quality
- **Modular architecture** for maintainability
- **PWA support** for offline capabilities

## Architecture

The app uses modern React architecture with the following structure:

### React Components
- **App**: Main React component handling service initialization and screen navigation
- **ConfigScreen**: WordPress site configuration and authentication
- **MainApp**: Primary voice assistant interface with settings menu
- **MicButton**: Microphone button with press-and-hold detection
- **ChatContainer**: Message history and conversation display

### State Management
- **AppContext**: React Context with useReducer for centralized state
- **useTranslation**: Custom hook for accessing translations
- **TranslationProvider**: Context provider for translation services

### Services (Legacy Integration)
- **ApiService**: WordPress REST API client with authentication
- **AudioService**: Mobile audio unlocking and custom TTS playback
- **WebRTCService**: OpenAI Realtime API WebRTC connection handling
- **SessionManager**: Orchestrates WebRTC sessions and tool execution (with React bridge)
- **TranslationService**: Handles translation loading from WordPress backend

### Key Technologies
- **WebRTC**: Real-time audio streaming with OpenAI
- **Web Audio API**: Mobile audio unlocking for iOS/Android
- **MediaStream API**: Microphone access and control
- **Service Worker**: PWA offline support (via manifest)

## Voice Interaction Flow

1. **Authentication**: User enters WordPress credentials
2. **Session Creation**: App requests session token from WordPress
3. **WebRTC Connection**: Establishes peer connection with OpenAI
4. **Audio Streaming**: Real-time bidirectional audio
5. **Tool Execution**: Processes commands through WordPress backend
6. **Response Handling**: Either OpenAI audio or custom TTS

## API Integration

### WordPress REST API Endpoints

- `POST /wp-json/ai-commander/v1/realtime/session` - Create OpenAI session
- `POST /wp-json/ai-commander/v1/realtime/tool` - Execute tool calls
- `POST /wp-json/ai-commander/v1/read-text` - Generate TTS audio
- `GET /wp-json/ai-commander/v1/translations` - Get mobile app translations
- `GET /wp-json/ai-commander/v1/manifest` - Get dynamic PWA manifest
- `GET /wp-json/wp/v2/users/me` - Validate authentication

### Authentication Flow

The mobile app supports multiple authentication flows depending on how it's accessed:

#### 1. WordPress-Served PWA (Primary Flow)
When accessed through WordPress at the configured PWA path (e.g., `/ai-commander/assistant`):

1. **WordPress serves the PWA** via `PwaPage.php` which:
   - Generates HTML with embedded configuration (`window.AI_COMMANDER_CONFIG`)
   - Includes translations, manifest data, and base URL
   - Eliminates the need to ask users for the site URL

2. **User only enters credentials**:
   - Username
   - Application password

3. **App initializes** with embedded config:
   - Uses embedded translations (no API call needed)
   - Knows the WordPress base URL
   - Only needs to validate credentials

#### 2. Development Mode (Vite)
When running locally with `npm run dev`:

1. **Set environment variable** in `.env.local`:
   ```bash
   VITE_WP_BASE_URL=http://your-wordpress-site.local
   ```

2. **User only enters credentials** (same as WordPress flow)

3. **App uses Vite environment** for base URL

#### 3. Legacy/Standalone Mode
When accessed without embedded config or Vite:

1. **User enters all information**:
   - WordPress site URL
   - Username
   - Application password

2. **App validates and stores** all three values

### WordPress Integration: PwaPage.php

The `PwaPage.php` class is responsible for serving the PWA with WordPress context:

#### Key Functions:
1. **URL Handling**: Registers the PWA at a configurable path (default: `/ai-commander/assistant`)
2. **Configuration Embedding**: Injects `window.AI_COMMANDER_CONFIG` with:
   - `baseUrl`: WordPress site URL
   - `translations`: All mobile app translations in user's language
   - `manifest`: PWA manifest data (name, colors, icons)
   - `locale`: Current WordPress locale
   - `pwaPath`: The configured PWA path
   - `version`: Plugin version

3. **Asset Management**: 
   - Serves the built React app from `mobile/app/`
   - Assets load directly from plugin directory (no proxying)
   - Automatically includes correct CSS/JS files

4. **No Build Required**: The PWA is pre-built and committed, so it works immediately after plugin activation

#### Benefits:
- **Simplified UX**: Users don't need to know/type the WordPress URL
- **No FOUC**: Translations are immediately available
- **Multisite Ready**: Each site can have different PWA settings
- **Filter Support**: Developers can customize via WordPress filters

### Authentication Details

Uses WordPress Application Passwords with Basic Authentication:
```typescript
Authorization: Basic base64(username:app_password)
```

The app generates bearer tokens for API requests and stores the app password securely in localStorage.

## Features

### Core Features
- 🎙️ **Real-time Voice Interaction** - OpenAI Realtime API integration
- 📱 **Mobile-Optimized** - Touch-friendly interface for smartphones
- 🔐 **Secure Authentication** - WordPress application passwords
- 🔊 **Custom TTS** - Fallback when OpenAI audio is disabled
- 💬 **Visual Chat** - Message history with typing indicators
- ⚡ **Real-time Status** - Visual feedback for all states

### Technical Features
- 🔄 **Lazy Initialization** - Services initialized on demand
- 🎵 **Audio Interruption** - Stop TTS playback with button tap
- 📶 **Connection Recovery** - Automatic reconnection handling
- 🔇 **Smart Audio** - Microphone muting during TTS playback
- 📱 **PWA Ready** - Installable as standalone app

## State Management

The app uses React Context with useReducer for centralized state management:

```typescript
interface AppState {
  siteUrl: string;
  username: string;
  bearerToken: string | null;
  sessionToken: string | null;
  status: AppStatus;
  messages: Message[];
  currentTranscript: string;
  toolCallQueue: ToolCall[];
  modalities: string[];
  isCustomTtsEnabled: boolean;
  isPlayingCustomTts: boolean;
  // ... more state
}
```

Components access state through the `useAppContext()` hook and updates trigger automatic re-renders.

## Translation System

The mobile app includes comprehensive internationalization support:

### WordPress Integration
- **Backend Service**: `MobileTranslations.php` provides all mobile-specific strings
- **REST API Endpoint**: `/wp-json/ai-commander/v1/translations` serves translations
- **PO/MO Files**: Translators work with standard WordPress .po files
- **Runtime Loading**: Translations loaded dynamically based on user's WordPress language

### React Translation Usage
```typescript
// In React components
const { t } = useTranslation();
const text = t('mobile.status.disconnected', 'Press to start');

// In service classes  
const text = UiMessages.STATUS_MESSAGES.disconnected;
```

### Translation Features
- **Fallback Support**: English fallbacks when translations unavailable
- **Clean Syntax**: Simple `t(key, fallback)` function calls
- **Icon Integration**: Icons included in translatable strings per user requirements
- **Config Screen**: English-only for initial setup (site URL unknown)
- **Error Handling**: Graceful degradation when translation API unavailable

## Building for Production

The production build outputs to the `app/` directory:

```bash
npm run build
```

Output structure:
```
app/
├── index.html          # Main HTML file
├── manifest.json       # PWA manifest
└── assets/
    ├── main-*.css      # Minified styles
    └── main-*.js       # Minified JavaScript
```

## Deployment

The mobile app is pre-built and committed to the repository, so no build step is required on production servers:

### Primary Access Method
The PWA is served through WordPress at a configurable path (default: `/ai-commander/assistant`):
1. Users access the PWA at `https://yoursite.com/ai-commander/assistant`
2. WordPress serves the app with embedded configuration via `PwaPage.php`
3. Users only need to enter WordPress credentials (not the site URL)
4. PWA path can be configured in WordPress Admin → AI Commander → Settings → Mobile Web App Settings

### Legacy Access Method
The app is also accessible at `mobile/index.html` (redirects to `mobile/app/index.html`) for backwards compatibility.

### Technical Requirements
1. No Node.js or npm required on production server
2. CORS headers are automatically configured by the WordPress plugin
3. PWA manifest is dynamically generated with proper translations

### Rebuilding (For Developers)
If you make changes to the React app:
1. Make your changes in the `src/` directory
2. Run `npm run build` to build the app
3. Commit the changes including the `app/` directory
4. The updated app will be immediately available through both access methods

## Development Notes

### Mobile Audio
The app includes special handling for mobile browsers:
- Audio context unlocking on first user interaction
- Muted play/pause cycle for iOS Safari compatibility
- WebRTC audio track management

### React-Service Integration
The app bridges React components with legacy TypeScript services:
- **SessionManager Bridge**: Adapts StateManager interface to React Context
- **Service Lifecycle**: Services initialized lazily on first interaction
- **Cleanup Handling**: Proper service cleanup on component unmount
- **State Synchronization**: React state updates trigger service responses

### CORS Configuration
The WordPress plugin includes CORS headers for `/ai-commander/v1/` endpoints.
Special handling for the `/read-text` endpoint which returns binary audio data.

### Error Handling
- Connection failures show user-friendly messages
- Session expiry redirects to login
- Tool execution errors are passed to OpenAI for response

## Contributing

1. Follow the existing TypeScript patterns
2. Run linting before committing: `npm run lint`
3. Ensure types are properly defined
4. Test on actual mobile devices