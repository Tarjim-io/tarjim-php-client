#!/bin/sh

# Exit on error
set -e

# Get the latest Git tag (e.g., v1.2.3)
VERSION=$(git describe --tags --abbrev=0)

# Save it to a file
echo "$VERSION" > VERSION

# Optional: print to confirm
echo "✔️ Version written to VERSION file: $VERSION"
