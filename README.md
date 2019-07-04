# Fix absolute paths in database content

Script to help fix incorrect absolute URL paths in database stored content. This often occurs where content is entered 
on a test or staging URL and then you want to go live on a different URL. 

For example database content with the following HTML: 

    <img src="http://staging.domain.com/assets/img/person.jpg" alt="The team">

Will be translated to: 

    <img src="/assets/img/person.jpg" alt="The team">

This will update string data stored in a database as well as serialized data (arrays only).

If you find any issues with this script please create a pull request!

## Usage

Simple usage:

    php fixUrls.php staging.domain.com

Specify which database table/s to replace content in (you can add any number of tables at the end of the argument list, 
separated by spaces):

    php fixUrls.php staging.domain.com exp_channel_data

You can pass database parameters:

    php fixUrls.php staging.domain.com --host=localhost --username=user --password=abc123 --database=my_database 

You can also replace the base path for all links:

    php fixUrls.php staging.domain.com --basePathRemove=/sites/default/files/ --basePathReplace=/files/

For example, links such as:

    <img src="http://staging.domain.com/sites/default/files/person.jpg" alt="The team">

Will be translated to: 

    <img src="/files/person.jpg" alt="The team">

Output help documentation:

    php fixUrls.php --help

## Installation

This CLI scripts uses the [Symfony Console](http://symfony.com/doc/current/components/console/index.html) component. 
Use [Composer](http://getcomposer.org) to load this.

To install run the following commands:

```
wget https://github.com/studio24/fix-staging-urls/archive/v1.1.tar.gz -O - | tar -xz
cd fix-staging-urls-1.1
composer install
```

Or if you want to check out the Git repo:

```
git clone https://github.com/studio24/fix-staging-urls
cd fix-staging-urls
composer install
```

## License

MIT License (MIT)
Copyright (c) 2014-2019 Studio 24 Ltd (www.studio24.net)

