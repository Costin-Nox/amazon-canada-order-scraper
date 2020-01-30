# Amazon.ca Order Scraper
A scraper for your amazon orders on the canadian store.

The code will compute stats such as money spent, # of order, #of items, #of returns, value of returned items
It will also create a csv file with every item you ordered.

I build this to help me track spending and to easily identify my items for tax purposes. It's a shame amazon canada does not have the option to export your orders.

# Requirments

PHP >7.1.x

# Usage

1) Open session.ini and add your sessionid and user agent. You can get this by going to any amazon page while logged in on chrome, opening the inspector, going to network, document and refresh the page. Select the document and look in the headers sections.

The session id will be a very long string, copy and paste the whole thing in between the ''

The user agent will be there as well, copy it and paste it into the ini file.

2) run the code using > php scrape.php -r

This will fetch the last 6 months

To specify a year:

> php scrape.php -r -y=2020

This would look at all orders from 2020

To specify a filename to save, other than default.
 > php scrape -r -y=2020 -f='output.csv'

# Warning!

This code requires the session and useragent, the combination allows *anyone* to access your account while the session is active. The code is open source and clear, you can see what is done with these and that it is not sent anywhere. If something like this ends up hosted, please do not use it, they can hijack your session.
