# Katoni APIs Client Library for PHP #

## Installation

The Katoni API Client Library can be installed with [Composer](https://getcomposer.org/). Run this command:

```sh
composer require katoni/api-php-client:^1.0
```

## Usage

> **Note:** This library requires PHP 5.4 or greater.

Simple GET example of fetching products.

```php
// include your composer dependencies
require_once 'vendor/autoload.php';

$client = new Katoni\Client();
$client->setDeveloperKey("YOUR_APP_KEY");

$results = $client->get('/products');

foreach ($results as $item) {
    echo $item['name'], "<br /> \n";
}
```