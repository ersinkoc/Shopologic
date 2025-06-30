#!/bin/bash
# Shopologic Plugin Test Runner

echo "🧪 Running Shopologic Plugin Tests"
echo "=================================="

# Check if PHPUnit is available
if ! command -v phpunit &> /dev/null; then
    echo "❌ PHPUnit not found. Please install PHPUnit first."
    echo "   composer global require phpunit/phpunit"
    exit 1
fi

# Run tests for each plugin
for plugin_dir in */; do
    if [ -d "${plugin_dir}tests" ]; then
        echo ""
        echo "🔬 Testing plugin: ${plugin_dir%/}"
        echo "=========================="
        
        cd "$plugin_dir"
        
        if [ -f "phpunit.xml" ]; then
            phpunit --configuration phpunit.xml
        else
            phpunit tests/
        fi
        
        cd ..
    fi
done

echo ""
echo "✅ All plugin tests completed!"
echo "📊 Check TEST_REPORT.json for detailed results"