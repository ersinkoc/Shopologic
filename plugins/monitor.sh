#!/bin/bash
# Shopologic Plugin Health Monitor
# Run this script via cron for continuous monitoring

cd "$(dirname "$0")"
php plugin_monitor.php

# Send alerts if critical issues found
if grep -q '"critical"' HEALTH_REPORT.json; then
    echo "Critical plugin health issues detected!" | mail -s "Shopologic Plugin Alert" admin@yoursite.com
fi