import type { ApiError } from '../../types';

const BASE_URL = (import.meta.env.VITE_API_BASE_URL || '/api').replace(/\/+$/, '');

export interface RequestOptions extends RequestInit {
  timeoutMs?: number;
}

export async function apiClient<T>(endpoint: string, options: RequestOptions = {}): Promise<T> {
  const { timeoutMs = 15000, headers = {}, ...customConfig } = options;

  const url = endpoint.startsWith('http') ? endpoint : `${BASE_URL}${endpoint.startsWith('/') ? endpoint : `/${endpoint}`}`;

  const controller = new AbortController();
  const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

  try {
    const response = await fetch(url, {
      ...customConfig,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...headers,
      },
      signal: controller.signal,
    });

    clearTimeout(timeoutId);

    // Handle 204 No Content
    if (response.status === 204) {
      return {} as T;
    }

    const contentType = response.headers.get('content-type') || '';
    const isJson = contentType.includes('application/json');
    const data = isJson ? await response.json() : await response.text();

    if (!response.ok) {
      const error: ApiError = {
        code: data?.error?.code || data?.code || `HTTP_${response.status}`,
        message: data?.error?.message || data?.message || response.statusText || 'Request failed.',
        status: response.status,
        fields: data?.error?.fields || data?.errors || undefined,
      };
      throw error;
    }

    return data as T;
  } catch (err: unknown) {
    clearTimeout(timeoutId);

    if (err && typeof err === 'object' && 'status' in err && 'code' in err) {
      throw err as ApiError;
    }

    if ((err as Error)?.name === 'AbortError') {
      const timeoutError: ApiError = {
        code: 'TIMEOUT_ERROR',
        message: 'The request timed out while awaiting a response from the server.',
        status: 504,
      };
      throw timeoutError;
    }

    const networkError: ApiError = {
      code: 'NETWORK_ERROR',
      message: (err as Error)?.message || 'Failed to connect to the server. Please check your network.',
      status: 0,
    };
    throw networkError;
  }
}
