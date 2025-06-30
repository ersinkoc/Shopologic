# ⭐ Reviews & Ratings Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Comprehensive customer review and rating system with moderation tools, rich media support, verified purchases, and social proof features for building trust and driving conversions.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Reviews & Ratings
php cli/plugin.php activate reviews-ratings
```

## ✨ Key Features

### 📝 Review Management
- **Star Ratings** - 1-5 star system
- **Written Reviews** - Detailed feedback
- **Photo/Video Reviews** - Rich media support
- **Verified Purchase Badge** - Authenticity
- **Review Moderation** - Quality control

### 🏆 Social Features
- **Helpful Votes** - Community validation
- **Review Responses** - Merchant replies
- **Q&A Section** - Customer questions
- **Review Sharing** - Social media integration
- **Reviewer Profiles** - User reputation

### 📊 Analytics & Display
- **Review Analytics** - Performance metrics
- **Rich Snippets** - SEO optimization
- **Widget Customization** - Display options
- **Email Requests** - Review solicitation
- **Incentive Programs** - Review rewards

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`ReviewsRatingsPlugin.php`** - Core review engine

### Services
- **Review Manager** - Review lifecycle
- **Rating Calculator** - Score computation
- **Moderation Service** - Content filtering
- **Notification Service** - Email campaigns
- **Analytics Engine** - Review insights

### Models
- **Review** - Review content
- **Rating** - Numeric ratings
- **ReviewMedia** - Photos/videos
- **ReviewVote** - Helpfulness votes
- **ReviewRequest** - Email campaigns

### Controllers
- **Review API** - Review endpoints
- **Widget Controller** - Display widgets
- **Admin Panel** - Management interface

## 🔧 Installation

### Requirements
- PHP 8.3+
- Media storage
- Email service
- Moderation tools
- SEO support

### Setup

```bash
# Activate plugin
php cli/plugin.php activate reviews-ratings

# Run migrations
php cli/migrate.php up

# Configure moderation
php cli/reviews.php setup-moderation

# Import existing reviews
php cli/reviews.php import
```

## 📚 API Endpoints

### REST API
- `GET /api/v1/reviews` - List reviews
- `POST /api/v1/reviews` - Submit review
- `PUT /api/v1/reviews/{id}` - Update review
- `POST /api/v1/reviews/{id}/vote` - Vote helpful
- `GET /api/v1/products/{id}/rating` - Get rating

## 🚀 Production Ready

This plugin is production-ready with:
- ✅ Complete review system
- ✅ Rich media support
- ✅ Moderation tools
- ✅ SEO optimization
- ✅ Email automation
- ✅ Analytics integration

---

**Reviews & Ratings** - Social proof system for Shopologic