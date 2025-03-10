#!/bin/bash

# Define excluded file patterns
EXCLUDED_PATTERNS=(
  '.DS_Store'
  '.git'
  '.gitignore'
  '.vscode'
  'composer.json'
  'composer.lock'
  'phpstan.neon'
  'CLAUDE.md'
  'postman_collection.json'
  'README.md'
  'build_tools'
  'vendor'
)

# Create a temporary build directory
mkdir -p build

# Prepare the readme.txt file
composer readme
mv readme.txt build/

# Copy all necessary plugin files to the build directory
cp -R admin assets includes tools LICENSE wpnl-wordpress-natural-language.php build/

# Remove excluded files
for pattern in "${EXCLUDED_PATTERNS[@]}"; do
  find build -name "$pattern" -type f -delete 2>/dev/null
  find build -name "$pattern" -type d -exec rm -rf {} + 2>/dev/null || true
done

# Get version from composer.json
VERSION=$(jq -r '.version' composer.json)

# Create zip file
cd build
zip -r "../wpnl-${VERSION}.zip" *
cd ..

# Clean up
rm -rf build
