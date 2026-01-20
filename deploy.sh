#!/bin/bash
# Deploy script for alynt-certificate-generator
# Configure these variables:
PLUGIN_SLUG="alynt-certificate-generator"
REMOTE_USER="your-ssh-user"
REMOTE_HOST="dev-root"
REMOTE_PATH="/var/www/bp-play.demostatus.com/htdocs/wp-content/plugins/${PLUGIN_SLUG}"

rsync -avz --delete \
  --exclude 'node_modules' \
  --exclude '.git' \
  --exclude '.husky' \
  --exclude 'scripts' \
  --exclude '*.map' \
  ./ "${REMOTE_USER}@${REMOTE_HOST}:${REMOTE_PATH}/"

echo "Deployed ${PLUGIN_SLUG} to staging"
