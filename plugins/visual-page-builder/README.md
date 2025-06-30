# 🎨 Visual Page Builder Plugin

![Quality Badge](https://img.shields.io/badge/Quality-71%25%20(C)-yellow)


Drag-and-drop page builder with pre-designed templates, responsive layouts, custom components, and real-time editing for creating stunning e-commerce pages without coding.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This plugin is part of the enhanced Shopologic ecosystem featuring cross-plugin integration, real-time events, performance monitoring, and automated testing.

## 🚀 Quick Start

```bash
# Initialize plugin ecosystem
php bootstrap_plugins.php

# Activate Visual Page Builder
php cli/plugin.php activate visual-page-builder
```

## ✨ Key Features

### 🎨 Design Tools
- **Drag & Drop Editor** - Intuitive interface
- **Pre-Built Templates** - Professional designs
- **Custom Components** - Reusable blocks
- **Responsive Design** - Mobile-first layouts
- **Live Preview** - Real-time editing

### 🧩 Component Library
- **Content Blocks** - Text, images, videos
- **E-commerce Widgets** - Products, cart, checkout
- **Forms & CTAs** - Lead generation
- **Social Elements** - Feed integration
- **Interactive Features** - Sliders, tabs, accordions

### 🎯 Advanced Features
- **A/B Testing** - Page variations
- **SEO Optimization** - Built-in SEO tools
- **Custom CSS/JS** - Advanced customization
- **Version Control** - Page history
- **Template Marketplace** - Community templates

## 🏗️ Plugin Architecture

### Main Plugin Class
- **`VisualPageBuilderPlugin.php`** - Core builder engine

### Services
- **Builder Engine** - Page construction
- **Component Registry** - Block management
- **Template Manager** - Template storage
- **Preview Service** - Live rendering
- **Export Service** - Code generation

### Models
- **Page** - Page definitions
- **Component** - Building blocks
- **Template** - Page templates
- **Revision** - Version history
- **Asset** - Media assets

### Controllers
- **Builder API** - Editor endpoints
- **Builder UI** - Visual interface
- **Template Store** - Template browser

## 🔧 Installation

### Requirements
- PHP 8.3+
- Modern browsers
- Image processing
- CDN support
- Template storage

### Setup

```bash
# Activate plugin
php cli/plugin.php activate visual-page-builder

# Run migrations
php cli/migrate.php up

# Install components
php cli/builder.php install-components

# Import templates
php cli/builder.php import-templates
```

## 📚 API Endpoints

### REST API
- `GET /api/v1/builder/pages` - List pages
- `POST /api/v1/builder/pages` - Create page
- `PUT /api/v1/builder/pages/{id}` - Update page
- `GET /api/v1/builder/components` - Get components
- `POST /api/v1/builder/preview` - Preview page

## 🚀 Production Ready

This plugin is production-ready with:
- ✅ Intuitive page building
- ✅ Professional templates
- ✅ Responsive design
- ✅ SEO optimized
- ✅ Performance focused
- ✅ Enterprise features

---

**Visual Page Builder** - Beautiful pages made easy for Shopologic