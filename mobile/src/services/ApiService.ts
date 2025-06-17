import { API_ENDPOINTS, ERROR_MESSAGES } from '@/utils/constants';
import { SessionResponse, ToolExecutionRequest, ToolExecutionResponse } from '@/types';

export class ApiService {
  constructor(
    private siteUrl: string,
    private bearerToken: string
  ) {}

  async testConnection(): Promise<void> {
    const response = await fetch(`${this.siteUrl}${API_ENDPOINTS.WP_USER_ME}`, {
      method: 'GET',
      mode: 'cors',
      credentials: 'omit',
      headers: {
        Authorization: `Basic ${this.bearerToken}`,
      },
    });

    if (!response.ok) {
      if (response.status === 401) {
        throw new Error(ERROR_MESSAGES.INVALID_CREDENTIALS);
      } else if (response.status === 403) {
        // In multisite, status 403 might be normal
        try {
          const userData = await response.json();
          if (userData && userData.id && userData.name) {
            console.log('Authentication successful despite 403 status');
          } else {
            throw new Error(ERROR_MESSAGES.ACCESS_DENIED);
          }
        } catch (jsonError) {
          throw new Error(ERROR_MESSAGES.ACCESS_DENIED);
        }
      } else {
        throw new Error(ERROR_MESSAGES.CONNECTION_FAILED);
      }
    }
  }

  async createSession(): Promise<SessionResponse> {
    const response = await fetch(`${this.siteUrl}${API_ENDPOINTS.REALTIME_SESSION}`, {
      method: 'POST',
      credentials: 'omit',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Basic ${this.bearerToken}`,
      },
      body: JSON.stringify({}),
    });

    if (!response.ok) {
      const error = await response.text();
      throw new Error(`${ERROR_MESSAGES.SESSION_FAILED}: ${error}`);
    }

    const sessionData = await response.json();
    if (!sessionData.client_secret?.value) {
      throw new Error('Invalid session response');
    }

    return sessionData;
  }

  async executeTool(request: ToolExecutionRequest): Promise<ToolExecutionResponse> {
    const response = await fetch(`${this.siteUrl}${API_ENDPOINTS.REALTIME_TOOL}`, {
      method: 'POST',
      credentials: 'omit',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Basic ${this.bearerToken}`,
      },
      body: JSON.stringify(request),
    });

    const result = await response.json();
    return result;
  }

  async getTextToSpeech(text: string): Promise<Blob> {
    const response = await fetch(`${this.siteUrl}${API_ENDPOINTS.READ_TEXT}`, {
      method: 'POST',
      credentials: 'omit',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Basic ${this.bearerToken}`,
      },
      body: JSON.stringify({ text }),
    });

    if (!response.ok) {
      throw new Error(`TTS request failed with status ${response.status}`);
    }

    return await response.blob();
  }

  static generateBearerToken(username: string, appPassword: string): string {
    const credentials = `${username}:${appPassword}`;
    return btoa(credentials);
  }
}
