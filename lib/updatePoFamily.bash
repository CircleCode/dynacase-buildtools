#!/usr/bin/env bash

set -o errexit
set -o nounset

INPUT_DIR="$1"
LOCALE="$2" #format: fr_FR.UTF8
OUTPUT_DIR="$3"

MYDIR="$(dirname "$(readlink -f "$0")")"

for filename in `find "$INPUT_DIR" -name "*.pot" -printf "%f$IFS"`; do

    basefilename=`basename $filename .pot`

    echo "translating family $basefilename into $LOCALE";

    $MYDIR/updatePoFile.bash "$INPUT_DIR/$filename" "$LOCALE" "$OUTPUT_DIR/family_${basefilename}.po"

done;