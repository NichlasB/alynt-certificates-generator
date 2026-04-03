#!/bin/bash
# Copy this file to deploy.sh and customize deploy.sh locally.
# Keep deploy.sh gitignored; commit only deploy.example.sh.
set -e

REMOTE_HOST="your-ssh-alias"
REMOTE_PATH="/var/www/your-site/htdocs/wp-content/plugins/alynt-certificates-generator"

echo "🚀 Deploying alynt-certificate-generator to staging..."

rsync -avz --delete \
  --exclude=".git" \
  --exclude=".github" \
  --exclude="docs" \
  --exclude=".gitignore" \
  --exclude=".gitattributes" \
  --exclude=".editorconfig" \
  --exclude="node_modules" \
  --exclude="tests" \
  --exclude="coverage" \
  --exclude="scripts/" \
  --exclude="build/" \
  --exclude="assets/src/" \
  --exclude=".DS_Store" \
  --exclude=".env" \
  --exclude=".env.local" \
  --exclude="README.md" \
  --exclude="CHANGELOG.md" \
  --exclude=".phpcs.xml" \
  --exclude=".phpcs.xml.dist" \
  --exclude="phpunit.xml" \
  --exclude="phpunit.xml.dist" \
  --exclude="package.json" \
  --exclude="package-lock.json" \
  --exclude="composer.json" \
  --exclude="composer.lock" \
  --exclude="deploy.sh" \
  --exclude="deploy.example.sh" \
  --exclude="session-context.tmp.md" \
  --exclude="*.map" \
  --exclude="composer.phar" \
  --exclude="SETUP_NOTES_WINDOWS.txt" \
  ./ \
  "${REMOTE_HOST}:${REMOTE_PATH}/"

echo "✅ Deployment complete!"
echo "📍 Remote path: ${REMOTE_PATH}"
