# 📝 Event Sourcing Plugin

![Quality Badge](https://img.shields.io/badge/Quality-57%25%20(F)-red)


Complete event sourcing implementation providing immutable event logs, event replay capabilities, CQRS pattern support, and temporal queries for building audit-compliant, scalable applications.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Event Sourcing
php cli/plugin.php activate event-sourcing
```

## ✨ Key Features

### 📚 Event Store Management
- **Immutable Event Log** - Append-only event storage
- **Event Versioning** - Schema evolution support
- **Event Replay** - Rebuild state from events
- **Snapshot Support** - Performance optimization
- **Event Archival** - Long-term event storage

### 🔄 CQRS Implementation
- **Command Bus** - Command routing and handling
- **Query Models** - Read-optimized projections
- **Event Handlers** - Asynchronous processing
- **Saga Management** - Long-running processes
- **Eventual Consistency** - Distributed system support

### 🕰️ Temporal Features
- **Time Travel Queries** - Point-in-time state
- **Audit Trail** - Complete history tracking
- **Event Correlation** - Related event linking
- **Compensating Events** - Error correction
- **Event Sourcing Patterns** - Best practices

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`EventSourcingPlugin.php`** - Core event sourcing engine

### Services
- **Event Store** - Event persistence layer
- **Command Bus** - Command dispatching
- **Event Bus** - Event publishing
- **Projection Engine** - Read model builder
- **Snapshot Manager** - State snapshots

### Models
- **Event** - Domain event definitions
- **Aggregate** - Event-sourced entities
- **Projection** - Read model views
- **Snapshot** - Aggregate snapshots
- **EventMetadata** - Event context data

### Controllers
- **Event API** - Event query endpoints
- **Command API** - Command submission
- **Admin Console** - Event store management

## 🔧 Installation

### Requirements
- PHP 8.3+
- Event store database
- Message queue system
- High-performance storage
- Distributed system support

### Setup

```bash
# Activate plugin
php cli/plugin.php activate event-sourcing

# Run migrations
php cli/migrate.php up

# Configure event store
php cli/event-sourcing.php setup-store

# Build projections
php cli/event-sourcing.php build-projections
```

## 📚 API Endpoints

### REST API
- `POST /api/v1/commands` - Submit command
- `GET /api/v1/events` - Query event stream
- `GET /api/v1/aggregates/{id}` - Get aggregate state
- `POST /api/v1/replay` - Replay events
- `GET /api/v1/projections` - Query projections

## 🚀 Production Ready

This plugin is production-ready with:
- ✅ Complete event sourcing
- ✅ CQRS pattern support
- ✅ Temporal queries
- ✅ Event replay capability
- ✅ Audit compliance
- ✅ Scalable architecture

---

**Event Sourcing** - Immutable event-driven architecture for Shopologic