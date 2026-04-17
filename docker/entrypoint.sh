#!/bin/bash
set -e

# Start cron daemon in background
if command -v cron &> /dev/null; then
    cron
fi

# Start Apache in foreground
exec apache2-foreground
