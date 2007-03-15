#!/bin/sh
cd i18n/locale
for i in *
do (
	cd $i/LC_MESSAGES/
	msgfmt swisdk.po --output-file=swisdk.mo
)
done
