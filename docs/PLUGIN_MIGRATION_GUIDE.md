# Plugin.json Migration Guide

This guide helps plugin developers migrate their existing plugin.json files to the new standardized format introduced in Shopologic v1.1.

**🎯 ENHANCED PLUGIN ECOSYSTEM - PRODUCTION READY**

This migration guide now covers the enhanced plugin ecosystem with 47 advanced models, cross-plugin integration, real-time events, and performance monitoring capabilities.

## Overview

The standardized plugin.json format ensures consistency across all plugins and improves compatibility with the Plugin Manager system. All plugins should be migrated to use the new format.

## Key Changes

### 1. Author Field Structure

**Old format (various):**
```json
"author": "John Doe"
// or
"author": "John Doe <john@example.com>"
// or
"author": {
    "name": "John Doe",
    "email": "john@example.com"
}
```

**New standardized format:**
```json
"author": {
    "name": "John Doe"
}
```

Additional fields (email, url) are optional:
```json
"author": {
    "name": "John Doe",
    "email": "john@example.com",
    "url": "https://example.com"
}
```

### 2. Bootstrap Field (Main Class Definition)

**Old formats:**
```json
// Format 1: Direct class field
"class": "MyPlugin\\MyPluginClass"

// Format 2: Main field
"main": "src/MyPlugin.php"

// Format 3: Main class field
"main_class": "MyPlugin\\MyPluginClass"

// Format 4: Nested config
"config": {
    "main_class": "MyPlugin\\MyPluginClass"
}
```

**New standardized format:**
```json
"bootstrap": {
    "class": "MyPlugin\\MyPluginClass",
    "file": "src/MyPlugin.php"
}
```

### 3. Requirements Structure

**Old format:**
```json
"dependencies": ["core", "inventory-management"]
// or
"requires": {
    "core": ">=1.0.0"
}
```

**New standardized format:**
```json
"requirements": {
    "php": ">=8.3",
    "core": ">=1.0.0",
    "dependencies": {
        "shopologic/core": "^1.0",
        "inventory-management": ">=1.0.0"
    }
}
```

### 4. Hooks Format

**Old formats:**
```json
// Format 1: Simple arrays
"hooks": {
    "actions": ["order.created", "order.updated"],
    "filters": ["product.price", "cart.total"]
}

// Format 2: Detailed objects
"hooks": {
    "actions": [
        {
            "hook": "order.created",
            "handler": "onOrderCreated",
            "priority": 10
        }
    ]
}
```

**New standardized format:**
- Use simple arrays for basic hooks
- Use detailed objects only when specifying handler or priority

```json
"hooks": {
    "actions": [
        "order.created",
        "order.updated"
    ],
    "filters": [
        "product.price",
        "cart.total"
    ]
}
```

For advanced hooks with specific handlers:
```json
"hooks": {
    "actions": [
        {
            "hook": "order.created",
            "handler": "onOrderCreated",
            "priority": 10
        }
    ]
}
```

### 5. API Endpoints

**Old format:**
```json
"api_endpoints": [
    {
        "path": "/api/v1/my-plugin",
        "method": "GET",
        "handler": "handleGet"
    }
]
```

**New standardized format:**
```json
"api": {
    "endpoints": [
        {
            "method": "GET",
            "path": "/api/v1/my-plugin",
            "handler": "handleGet"
        }
    ]
}
```

## Migration Process

### Automated Migration

Use the provided migration script to automatically convert your plugin.json files:

```bash
php cli/migrate-plugin-manifests.php
```

This script will:
1. Create backups of all plugin.json files
2. Convert them to the standardized format
3. Validate the results
4. Report any issues

### Manual Migration

If you prefer to migrate manually:

1. **Update Author Field:**
   ```json
   // Change from:
   "author": "Your Name"
   
   // To:
   "author": {
       "name": "Your Name"
   }
   ```

2. **Convert Main Class Definition:**
   ```json
   // Change from:
   "main": "src/MyPlugin.php"
   
   // To:
   "bootstrap": {
       "class": "MyNamespace\\MyPlugin",
       "file": "src/MyPlugin.php"
   }
   ```

3. **Standardize Requirements:**
   ```json
   // Change from:
   "dependencies": ["core"]
   
   // To:
   "requirements": {
       "php": ">=8.3",
       "core": ">=1.0.0",
       "dependencies": {
           "shopologic/core": "^1.0"
       }
   }
   ```

4. **Update Hooks Format:**
   ```json
   // For simple hooks, keep as arrays:
   "hooks": {
       "actions": ["init", "shutdown"],
       "filters": ["content", "title"]
   }
   ```

5. **Move API Endpoints:**
   ```json
   // Change from:
   "api_endpoints": [...]
   
   // To:
   "api": {
       "endpoints": [...]
   }
   ```

## Validation

After migration, validate your plugin.json:

```bash
php cli/validate-plugins.php plugins/your-plugin
```

The validator will check:
- JSON syntax
- Required fields
- Field formats
- Schema compliance

## Backwards Compatibility

The Plugin Manager maintains backwards compatibility with old formats:
- Old formats are automatically detected and converted at runtime
- Plugins continue to work without immediate migration
- However, migration is recommended for:
  - Better performance (no runtime conversion)
  - Access to new features
  - Compliance with coding standards

## Common Issues and Solutions

### Issue: Class not found after migration

**Solution:** Ensure the `bootstrap.class` field matches the actual PHP namespace and class name in your file.

### Issue: Dependencies not loading

**Solution:** Convert simple dependency arrays to the full requirements structure with version constraints.

### Issue: Hooks not firing

**Solution:** Check that hook names haven't changed during migration. Use simple arrays unless you need specific handlers or priorities.

### Issue: API endpoints returning 404

**Solution:** Ensure the `api.endpoints` structure is correct and handlers are properly defined.

## Example: Complete Migration

**Before:**
```json
{
    "name": "my-plugin",
    "version": "1.0.0",
    "description": "My awesome plugin",
    "author": "John Doe <john@example.com>",
    "main": "MyPlugin.php",
    "dependencies": ["core", "api"],
    "hooks": {
        "actions": ["init", "shutdown"]
    },
    "api_endpoints": [
        {
            "path": "/api/v1/my-plugin",
            "method": "GET",
            "handler": "handleRequest"
        }
    ]
}
```

**After:**
```json
{
    "name": "my-plugin",
    "version": "1.0.0",
    "description": "My awesome plugin",
    "author": {
        "name": "John Doe",
        "email": "john@example.com"
    },
    "requirements": {
        "php": ">=8.3",
        "core": ">=1.0.0",
        "dependencies": {
            "shopologic/core": "^1.0",
            "shopologic/api": "^1.0"
        }
    },
    "autoload": {
        "psr-4": {
            "MyPlugin\\": "src/"
        }
    },
    "bootstrap": {
        "class": "MyPlugin\\MyPlugin",
        "file": "MyPlugin.php"
    },
    "hooks": {
        "actions": [
            "init",
            "shutdown"
        ]
    },
    "api": {
        "endpoints": [
            {
                "method": "GET",
                "path": "/api/v1/my-plugin",
                "handler": "handleRequest"
            }
        ]
    }
}
```

## Getting Help

If you encounter issues during migration:

1. Run the validator for detailed error messages
2. Check the plugin logs in `storage/logs/`
3. Refer to the [Plugin Development Guide](PLUGIN_DEVELOPMENT_GUIDE.md)
4. Contact support with your specific error messages

## Next Steps

After successful migration:

1. Test your plugin thoroughly
2. Update your plugin documentation
3. Consider adding new standardized fields:
   - `provides` - Interfaces your plugin implements
   - `widgets` - Dashboard widgets
   - `scheduled_tasks` - Cron jobs
   - `config.schema` - Configuration options

The standardized format enables better tooling, validation, and plugin ecosystem growth.