# AI Commander Mobile App

This is the TypeScript-based mobile voice assistant for AI Commander WordPress plugin.

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
│   ├── main.ts         # Application entry point
│   └── index.html      # HTML template
├── public/             # Static assets (manifest, icons)
├── dist/               # Build output
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

- **TypeScript** for type safety
- **Vite** for fast development and optimized builds
- **ESLint & Prettier** for code quality
- **Modular architecture** for maintainability
- **PWA support** for offline capabilities

## Architecture

The app is split into several modules:

### Services
- **StateManager**: Centralized state management with observers
- **ApiService**: WordPress REST API client with authentication
- **AudioService**: Mobile audio unlocking and custom TTS playback
- **WebRTCService**: OpenAI Realtime API WebRTC connection handling
- **SessionManager**: Orchestrates WebRTC sessions and tool execution

### Components
- **App**: Main application controller and initialization
- **UIController**: DOM manipulation and UI state updates
- **MicButtonController**: Microphone button state machine

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

The app uses a centralized `StateManager` with observer pattern:

```typescript
interface AppState {
  siteUrl: string;
  username: string;
  bearerToken: string | null;
  sessionToken: string | null;
  status: AppStatus;
  messages: Message[];
  // ... more state
}
```

Components subscribe to state changes for reactive updates.

## Building for Production

The production build outputs to the `dist/` directory:

```bash
npm run build
```

Output structure:
```
dist/
├── index.html          # Main HTML file
├── manifest.json       # PWA manifest
└── assets/
    ├── main-*.css      # Minified styles
    └── main-*.js       # Minified JavaScript
```

## Deployment

1. Build the app: `npm run build`
2. Upload contents of `dist/` to your web server
3. Ensure CORS headers are configured for your WordPress site
4. Access the app from a mobile device

## Development Notes

### Mobile Audio
The app includes special handling for mobile browsers:
- Audio context unlocking on first user interaction
- Muted play/pause cycle for iOS Safari compatibility
- WebRTC audio track management

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