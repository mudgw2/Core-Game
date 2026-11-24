export interface MultiplayerRuntimeConfig {
  environment: string;
  host: string;
  port: number;
  roomCapacity: number;
  developmentGuestsEnabled: boolean;
  developmentToken: string;
  hmacSecret: string;
}

function boundedInteger(value: string | undefined, fallback: number, minimum: number, maximum: number): number {
  if (value === undefined || value.trim() === '') return fallback;
  const parsed = Number(value);
  if (!Number.isInteger(parsed) || parsed < minimum || parsed > maximum) {
    throw new Error(`Invalid multiplayer integer configuration: ${value}`);
  }
  return parsed;
}

export function parseMultiplayerRuntimeConfig(environment: Record<string, string | undefined>): MultiplayerRuntimeConfig {
  const nodeEnvironment = (environment.NODE_ENV ?? 'development').trim().toLowerCase();
  const host = (environment.CORE_MULTIPLAYER_HOST ?? '127.0.0.1').trim();
  if (!host) throw new Error('CORE_MULTIPLAYER_HOST cannot be empty.');

  const developmentToken = environment.CORE_MULTIPLAYER_DEV_TOKEN ?? '';
  const requestedDevelopmentGuests = (environment.CORE_MULTIPLAYER_ALLOW_DEV_GUESTS ?? '').trim().toLowerCase() === 'true';
  const isLoopbackHost = host === '127.0.0.1' || host === '::1' || host.toLowerCase() === 'localhost';
  const developmentGuestsEnabled = requestedDevelopmentGuests
    && nodeEnvironment !== 'production'
    && isLoopbackHost
    && developmentToken.length >= 32;

  return {
    environment: nodeEnvironment,
    host,
    port: boundedInteger(environment.CORE_MULTIPLAYER_PORT, 2567, 1, 65535),
    roomCapacity: boundedInteger(environment.CORE_MULTIPLAYER_ROOM_CAPACITY, 16, 1, 64),
    developmentGuestsEnabled,
    developmentToken,
    hmacSecret: environment.CORE_MULTIPLAYER_HMAC_SECRET ?? '',
  };
}

export function isDevelopmentJoinAllowed(token: string, config: MultiplayerRuntimeConfig): boolean {
  if (!config.developmentGuestsEnabled || !config.developmentToken) return false;
  if (token.length !== config.developmentToken.length) return false;
  let difference = 0;
  for (let index = 0; index < token.length; index += 1) {
    difference |= token.charCodeAt(index) ^ config.developmentToken.charCodeAt(index);
  }
  return difference === 0;
}
