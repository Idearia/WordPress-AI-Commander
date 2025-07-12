# AI Commander Mobile App

This is the React-based mobile voice assistant for AI Commander WordPress plugin. The app provides real-time voice interaction with your WordPress site through OpenAI's Realtime API and includes comprehensive internationalization support.

## Development Setup

1. Install dependencies:
```bash
npm install
```

2. Start development server:
```bash
npm run dev
```

3. Build for production:
```bash
npm run build
```

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
- `GET /wp-json/wp/v2/users/me` - Validate authentication

### Authentication

Uses WordPress Application Passwords with Basic Authentication:
```typescript
Authorization: Basic base64(username:app_password)
```

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

1. The app is accessible at `mobile/index.html` (redirects to `mobile/app/index.html`)
2. No Node.js or npm required on production server
3. CORS headers are automatically configured by the WordPress plugin

For developers who want to rebuild the app:
1. Make your changes in the `src/` directory
2. Run `npm run build` to build the app
3. Commit the changes including the `app/` directory

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