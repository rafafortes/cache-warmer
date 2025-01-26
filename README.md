# Cache Warmer for Magento 2

Cache Warmer is a PHP tool designed to crawl and cache all internal URLs of a Magento 2 store to improve page load speeds by preloading content into Varnish cache.

## Features
- Crawls all internal URLs starting from the homepage.
- Extracts URLs from the store's `sitemap.xml`.
- Handles URL normalization and parameter variations.
- Skips unwanted file types (e.g., images, PDFs).
- Displays real-time processing details such as response codes and load times.

## Installation

### Using Composer
To install the package via Composer, run the following command:

```bash
composer require rafafortes/cache-warmer
```

Alternatively, add the package manually to your `composer.json`:

```json
{
    "require": {
        "rafafortes/cache-warmer": "^1.0"
    }
}
```

Then, run:

```bash
composer install
```

## Usage

Once installed, you can run the script from the command line:

```bash
php vendor/rafafortes/cache-warmer/src/UrlFetcher.php <baseUrl> <sitemapUrl>
```

**Example:**

```bash
php vendor/rafafortes/cache-warmer/src/UrlFetcher.php https://example.com https://example.com/sitemap.xml
```

### Output
The script will output information about each processed URL, including:

- Loading time
- HTTP response code
- Total number of URLs processed
- Execution time

## Requirements
- PHP 8.0 or higher
- Composer

## Configuration

Ensure your project follows the correct directory structure for autoloading:

```
/project-root
├── composer.json
├── src
│   └── UrlFetcher.php
└── README.md
```

## Contributing
Contributions are welcome! Feel free to submit a pull request or open an issue to suggest improvements.

## License
This project is licensed under the MIT License.

## Author
Developed by [Rafa Fortes](https://github.com/rafafortes).

