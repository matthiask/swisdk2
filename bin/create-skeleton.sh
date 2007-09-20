#!/bin/sh

SCRIPT=`readlink $0`

SKELETON_DIR="`dirname $SCRIPT`/../skeletons/"

to_lower() {
	echo $1|tr "[:upper:]" "[:lower:]"
}

create() {
	sed -e s/__CLASS__/$2/g $SKELETON_DIR/$1.skel > $3
}

usage() {
	echo >&2 "$0 (adminmodule|adminsite|contentsite) class"
}

case "$1" in
	am|adminmodule)
		create adminmodule $2 "`to_lower $2`%ctrl.php"
		;;
	as|adminsite)
		create adminsite $2 "`to_lower $2`%ctrl.php"
		;;
	cs|contentsite)
		create contentsite $2 All%ctrl.php
		;;
	*)
		usage
		;;
esac

