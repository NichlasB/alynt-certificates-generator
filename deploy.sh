#!/bin/bash
set -e

REMOTE_HOST="dev-root"
REMOTE_PATH="/var/www/bp-play.demostatus.com/htdocs/wp-content/plugins/alynt-certificates-generator"

echo "🚀 Deploying alynt-certificate-generator to staging..."

rsync -avz --delete \
  --exclude=".git" \
  --exclude=".gitignore" \
  --exclude="node_modules" \
  --exclude=".DS_Store" \
  --exclude=".env" \
  --exclude="README.md" \
  --exclude="scripts" \
  --exclude="*.map" \
  --exclude="composer.phar" \
  --exclude="SETUP_NOTES_WINDOWS.txt" \
  ./ \
  "${REMOTE_HOST}:${REMOTE_PATH}/"

echo "✅ Deployment complete!"
echo "📍 Remote path: ${REMOTE_PATH}"
