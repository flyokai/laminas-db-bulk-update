<?php
/**
 * laminas-db-bulk-update example — INSERT … ON DUPLICATE KEY UPDATE with deferred ID resolution.
 *
 * Imports product rows whose `category_code` must be looked up against an existing categories
 * table. The `SingleTableResolver` batches the SELECT IN(…) into a single round-trip.
 *
 * Run from project root, **after** `vendor/bin/flyok-setup install`, or against any MySQL DB
 * with the schema below:
 *
 *   CREATE TABLE demo_category (category_id INT AUTO_INCREMENT PRIMARY KEY, code VARCHAR(64) UNIQUE);
 *   INSERT INTO demo_category (code) VALUES ('electronics'),('books'),('clothing');
 *   CREATE TABLE demo_product (
 *      product_id INT AUTO_INCREMENT PRIMARY KEY,
 *      sku VARCHAR(64) UNIQUE,
 *      name VARCHAR(255),
 *      price DECIMAL(12,4),
 *      category_id INT
 *   );
 *
 *   php vendor/flyokai/laminas-db-bulk-update/examples/upsert_with_resolver.php
 */

require __DIR__ . '/../../../../vendor/autoload.php';

use Flyokai\LaminasDbBulkUpdate\InsertOnDuplicate;
use Flyokai\LaminasDbBulkUpdate\Resolver\SingleTableResolver;
use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Sql;

$adapter = new Adapter([
    'driver'   => 'Pdo_Mysql',
    'hostname' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'database' => $_ENV['DB_NAME'] ?? 'app',
    'username' => $_ENV['DB_USER'] ?? 'root',
    'password' => $_ENV['DB_PASS'] ?? '',
]);
$sql = new Sql($adapter);

$rows = [
    ['SKU-1001', 'Phone',     499.99, 'electronics'],
    ['SKU-1002', 'Tablet',    349.99, 'electronics'],
    ['SKU-1003', 'Cookbook',   29.99, 'books'      ],
    ['SKU-1004', 'T-Shirt',    19.99, 'clothing'   ],
    ['SKU-1005', 'New cat',    99.99, 'gardening'  ],   // unknown — will throw or be NULL
];

// Resolver: code → category_id, batched
$resolver = new SingleTableResolver(
    adapter:     $adapter,
    table:       'demo_category',
    sourceField: 'code',
    targetField: 'category_id',
);

$insert = InsertOnDuplicate::create('demo_product', ['sku', 'name', 'price', 'category_id'])
    ->onDuplicate(['name', 'price', 'category_id'])
    ->withResolver($resolver)
    ->withNullOnUnresolved();    // unknown 'gardening' becomes NULL instead of throwing

foreach ($rows as [$sku, $name, $price, $code]) {
    $insert->withRow($sku, $name, $price, $resolver->unresolved($code));
}

// Single SELECT IN(…) for all four codes
$resolver->resolve();

$insert->executeIfNotEmpty($sql);

echo "Imported " . count($rows) . " rows. Unresolved categories were set to NULL.\n";

// Verify:
$stmt = $sql->prepareStatementForSqlObject(
    $sql->select('demo_product')->columns(['sku', 'name', 'category_id'])
);
foreach ($stmt->execute() as $row) {
    printf("  %s  %-12s  category_id=%s\n", $row['sku'], $row['name'], $row['category_id'] ?? 'NULL');
}
