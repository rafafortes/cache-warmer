# Cache Warmer for Magento 2

Cache Warmer is a PHP tool designed to crawl and cache all internal URLs of a Magento 2 store to improve page load speeds by preloading content into Varnish cache.

## Features
- Crawls all internal URLs starting from the homepage.
- Extracts URLs from the store's `sitemap.xml`.
- Handles URL normalization and parameter variations.
- Skips unwanted file types (e.g., images, PDFs).
- Skips URLs based on a blacklist file with customizable patterns.
- Displays real-time processing details such as response codes and load times.
- Shows time in milliseconds for each URL loaded.
- **Multi-threading:** Supports parallel processing of URLs to speed up the crawling process.
- **Debug mode:** Outputs all links found on each accessed page.

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
php vendor/rafafortes/cache-warmer/src/UrlFetcher.php <baseUrl> <sitemapUrl> [--debug] [<threads>]
```

**Example:**

```bash
php vendor/rafafortes/cache-warmer/src/UrlFetcher.php https://example.com https://example.com/sitemap.xml
```

### Multi-threading
You can specify the number of threads to process URLs in parallel by adding the number as the last parameter.

**Example with 5 threads:**
```bash
php vendor/rafafortes/cache-warmer/src/UrlFetcher.php https://example.com https://example.com/sitemap.xml --debug 5
```

If the number of threads is not specified, the default is 1 thread.

### Debug Mode
You can enable debug mode by adding the `--debug` parameter to the command. This mode outputs all URLs found on each accessed page.

**Example:**
```bash
php vendor/rafafortes/cache-warmer/src/UrlFetcher.php https://example.com https://example.com/sitemap.xml --debug
```

### Blacklist Configuration
You can exclude specific URLs by adding patterns to a file named `blacklist` located in the same directory as the script. Each line of the file should contain a pattern to be ignored.

**Example `blacklist` file:**
```
productalert/add
checkout/cart
customer/account
```

The script will automatically load and apply the blacklist, skipping any URLs that contain the specified patterns.

### Output
The script will output information about each processed URL, including:

- Loading time (in milliseconds)
- HTTP response code
- Total number of URLs processed
- Execution time
- Number of skipped duplicate URLs
- URLs skipped due to blacklist rules (if any)

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
├── blacklist
└── README.md
```

## Contributing
Contributions are welcome! Feel free to submit a pull request or open an issue to suggest improvements.

## License
This project is licensed under the MIT License.

## Author
Developed by [Rafa Fortes](https://github.com/rafafortes).