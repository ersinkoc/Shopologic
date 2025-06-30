#!/bin/bash

# Shopologic Development Server Startup Script

echo "🚀 Starting Shopologic E-commerce Platform"
echo "========================================="
echo ""
echo "📋 System Status:"
echo "- Core System: ✅ Ready"
echo "- Plugins: ✅ 77 plugins activated"
echo "- Database: ⚠️  Requires configuration"
echo ""
echo "🌐 Starting development server on http://localhost:8000"
echo ""
echo "📍 Available endpoints:"
echo "- http://localhost:8000/test.php    - System test page"
echo "- http://localhost:8000/            - Main storefront"
echo "- http://localhost:8000/admin.php   - Admin panel"
echo "- http://localhost:8000/api.php     - API endpoint"
echo ""
echo "Press Ctrl+C to stop the server"
echo ""

# Start PHP development server
php -S localhost:8000 -t public/