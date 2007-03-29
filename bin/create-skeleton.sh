#!/bin/sh

SKELETON_DIR="`dirname $0`/../skeletons/"

to_lower() {
	echo $1|tr "[:upper:]" "[:lower:]"
}

create() {
	sed -e s/__CLASS__/$2/g $SKELETON_DIR/$1.skel > $3
}

usage() {
	echo >&2 "$0 (adminmodule|contentsite) class"
}

case "$1" in
	am|adminmodule)
		create adminmodule $2 "`to_lower $2`_ctrl.php"
		;;
	cs|contentsite)
		create contentsite $2 All_ctrl.php
		;;
	*)
		usage
		;;
esac

