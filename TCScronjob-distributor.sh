#!/bin/bash
# php ~/public_html/dw-20230404/TCSenhancement/TCScronjob-maildistributor.php >>~/public_html/dw-20230404/TCSenhancement/TCScronjob-maildistributor.log 2>&1
# php ~/public_html/dw/TCSenhancement/TCScronjob-maildistributorNew.php >>~/public_html/dw/TCSenhancement/TCScronjob-maildistributorNew.log 2>&1
# php ~/public_html/dw/TCSenhancement/TCScronjob-maildistributorNext.php >>~/public_html/dw/TCSenhancement/TCScronjob-maildistributorNext.log 2>&1
STATUS_FILE=~/public_html/dw/TCSenhancement/maildistributor/maildistribution.running;
# echo "`date`: maildistribution starts!"  >>~/public_html/dw/TCSenhancement/maildistributor/TCScronjob-maildistributor.log 2>&1
if test -f "$STATUS_FILE";
then
	echo "mail distribution already running!" >>~/public_html/dw/TCSenhancement/maildistributor/TCScronjob-maildistributor.log 2>&1
else
	# STATUS_FILE exists until php script ends
	touch "$STATUS_FILE"
	php ~/public_html/dw/TCSenhancement/maildistributor/TCScronjob-maildistributor.php >>~/public_html/dw/TCSenhancement/maildistributor/TCScronjob-maildistributor.log 2>&1
	rm "$STATUS_FILE"
fi
# echo "`date`: maildistribution ends!" >>~/public_html/dw/TCSenhancement/maildistributor/TCScronjob-maildistributor.log 2>&1
