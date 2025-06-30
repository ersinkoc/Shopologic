# 🔌 WebSocket Realtime Engine Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


High-performance WebSocket infrastructure providing real-time bidirectional communication, event broadcasting, presence channels, and scalable pub/sub messaging for interactive applications.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate WebSocket Realtime Engine
php cli/plugin.php activate websocket-realtime-engine
```

## ✨ Key Features

### 🔄 Real-Time Communication
- **Bidirectional Messaging** - Two-way communication
- **Event Broadcasting** - Real-time updates
- **Presence Channels** - User presence tracking
- **Private Channels** - Secure messaging
- **Room Management** - Channel organization

### 📡 Scalable Architecture
- **Horizontal Scaling** - Multi-server support
- **Redis Pub/Sub** - Distributed messaging
- **Load Balancing** - Connection distribution
- **Failover Support** - High availability
- **Cluster Mode** - Server clustering

### 🛡️ Security & Performance
- **Authentication** - Secure connections
- **Encryption** - TLS/SSL support
- **Rate Limiting** - Connection throttling
- **Message Queuing** - Reliable delivery
- **Connection Pooling** - Resource optimization

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`WebSocketRealtimeEnginePlugin.php`** - Core WebSocket engine

### Services
- **WebSocket Server** - Connection handling
- **Channel Manager** - Channel operations
- **Message Broker** - Message routing
- **Presence Service** - User tracking
- **Cluster Manager** - Multi-server coordination

### Models
- **Connection** - Client connections
- **Channel** - Communication channels
- **Message** - Message records
- **Presence** - User presence data
- **ServerNode** - Cluster nodes

### Controllers
- **WebSocket API** - HTTP fallback
- **Admin Dashboard** - Server monitoring
- **Debug Console** - Development tools

## 🔧 Installation

### Requirements
- PHP 8.3+
- WebSocket support
- Redis server
- SSL certificates
- Load balancer (optional)

### Setup

```bash
# Activate plugin
php cli/plugin.php activate websocket-realtime-engine

# Run migrations
php cli/migrate.php up

# Start WebSocket server
php cli/websocket.php start

# Configure clustering
php cli/websocket.php setup-cluster
```

## 📚 API Endpoints

### WebSocket Protocol
```javascript
// Connect
ws://localhost:8080/socket

// Subscribe to channel
{"event": "subscribe", "channel": "orders"}

// Send message
{"event": "message", "channel": "orders", "data": {...}}

// Presence channel
{"event": "presence", "channel": "users:online"}
```

### REST API
- `POST /api/v1/broadcast` - Broadcast message
- `GET /api/v1/channels` - List channels
- `GET /api/v1/connections` - Active connections
- `POST /api/v1/authenticate` - Auth token

## 🚀 Production Ready

This plugin is production-ready with:
- ✅ High-performance WebSocket
- ✅ Horizontal scaling
- ✅ Redis integration
- ✅ Security features
- ✅ Real-time analytics
- ✅ Enterprise reliability

---

**WebSocket Realtime Engine** - Real-time communication for Shopologic