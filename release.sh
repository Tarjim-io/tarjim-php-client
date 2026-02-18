#!/bin/bash

# Prompt for tag version
read -p "Enter new version tag (e.g. 1.3.0): " version

# Exit if tag is empty
if [ -z "$version" ]; then
  echo "❌ No tag entered. Aborting release."
  exit 1
fi

# Update VERSION file
php write-version.php "$version"

# Commit updated VERSION file
git add .
git commit -m "🔖 Update VERSION file for $version"

# Tag and push
git tag "$version"
git push origin master --tags
git push origin master

echo "✅ Released $version successfully!"
