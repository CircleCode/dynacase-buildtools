#!/usr/bin/env bash

set -o errexit
set -o nounset

POT_FILE="$1"
LOCALE="$2" #format: fr_FR.UTF8
PO_FILE="$3"

echo "updating $PO_FILE from $POT_FILE"

if [ ! -e "$PO_FILE" ]; then
    echo "$PO_FILE does not exists, initializing it"
    msginit --locale=$LOCALE \
            --no-translator \
            -i "$POT_FILE" \
            -o "$PO_FILE"
else
    # the po file already exists, merge it
    msgmerge --sort-output \
             --verbose \
             --no-fuzzy-matching \
             -o "$PO_FILE.new" \
             "$PO_FILE" \
             "$POT_FILE"
    mv "$PO_FILE.new" "$PO_FILE"
fi