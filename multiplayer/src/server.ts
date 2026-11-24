import { createServer } from "node:http";
import express from "express";
import { Server } from "@colyseus/core";
import { WebSocketTransport } from "@colyseus/ws-transport";
import { parseMultiplayerRuntimeConfig } from "./config/runtime.js";
import { UniverseRoom } from "./rooms/UniverseRoom.js";

const config = parseMultiplayerRuntimeConfig(process.env);
const app = express();
app.disable("x-powered-by");
app.get("/health", (_request, response) => {
  response.status(200).json({
    status: "ok",
    service: "core-multiplayer",
    release: "0.1.115",
    roomType: "universe",
    authentication: "php-hmac-grant",
    developmentGuestsEnabled: config.developmentGuestsEnabled,
  });
});

const server = new Server({
  transport: new WebSocketTransport({
    server: createServer(app),
    pingInterval: 10000,
    pingMaxRetries: 2,
  }),
  greet: false,
});

server
  .define("universe", UniverseRoom)
  .filterBy(["universeId", "systemId", "locationId"]);

server.listen(config.port, config.host).then(() => {
  console.info(`[CORE Multiplayer] Listening on ${config.host}:${config.port}`);
  console.info(`[CORE Multiplayer] Universe room capacity: ${config.roomCapacity}`);
  console.info(`[CORE Multiplayer] Development guest access: ${config.developmentGuestsEnabled ? "enabled (loopback only)" : "disabled"}`);
}).catch((error: unknown) => {
  console.error("[CORE Multiplayer] Failed to start", error);
  process.exitCode = 1;
});
