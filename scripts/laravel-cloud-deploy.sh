#!/usr/bin/env bash

set -Eeuo pipefail

php artisan migrate --force
