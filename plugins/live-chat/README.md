# 💬 Live Chat Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Real-time customer support chat system with agent management, AI-powered responses, visitor tracking, and omnichannel integration for superior customer service experiences.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Live Chat
php cli/plugin.php activate live-chat
```

## ✨ Key Features

### 💬 Chat Management
- **Real-Time Messaging** - Instant communication
- **Multi-Agent Support** - Team collaboration
- **Queue Management** - Fair chat distribution
- **Canned Responses** - Quick reply templates
- **File Sharing** - Document and image support

### 🤖 AI-Powered Features
- **Chatbot Integration** - Automated responses
- **Smart Routing** - Skill-based assignment
- **Sentiment Analysis** - Customer mood detection
- **Translation Support** - Multi-language chat
- **Intent Recognition** - Query understanding

### 📊 Analytics & Monitoring
- **Chat Analytics** - Performance metrics
- **Agent Monitoring** - Productivity tracking
- **Customer Satisfaction** - CSAT ratings
- **Response Time Tracking** - SLA monitoring
- **Conversion Tracking** - Sales attribution

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`LiveChatPlugin.php`** - Core chat engine

### Services
- **Chat Server** - WebSocket communication
- **Agent Manager** - Agent availability
- **Queue Service** - Chat distribution
- **Bot Engine** - AI responses
- **Analytics Tracker** - Chat metrics

### Models
- **ChatSession** - Active chat records
- **Message** - Chat messages
- **Agent** - Support agent data
- **ChatBot** - Bot configurations
- **ChatMetric** - Performance data

### Controllers
- **Chat API** - Chat endpoints
- **Agent Console** - Agent interface
- **Widget Controller** - Customer widget

## 🔧 Installation

### Requirements
- PHP 8.3+
- WebSocket server
- Redis for real-time
- AI/ML services
- SSL certificate

### Setup

```bash
# Activate plugin
php cli/plugin.php activate live-chat

# Run migrations
php cli/migrate.php up

# Start chat server
php cli/chat.php start-server

# Configure chatbot
php cli/chat.php setup-bot
```

## 📚 API Endpoints

### REST API
- `POST /api/v1/chat/start` - Start chat session
- `POST /api/v1/chat/message` - Send message
- `GET /api/v1/chat/history` - Chat history
- `POST /api/v1/chat/rate` - Rate conversation
- `GET /api/v1/chat/agents` - Available agents

### WebSocket Events
- `chat.message` - New message
- `chat.typing` - Typing indicator
- `chat.agent.join` - Agent joined
- `chat.visitor.queue` - Visitor queued

## 🚀 Production Ready

This plugin is production-ready with:
- ✅ Real-time communication
- ✅ Multi-agent support
- ✅ AI-powered features
- ✅ Comprehensive analytics
- ✅ Omnichannel integration
- ✅ Enterprise scalability

---

**Live Chat** - Real-time customer support for Shopologic