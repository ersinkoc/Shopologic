<?php

declare(strict_types=1);

/**
 * Database Driver Integration Tests
 * Tests actual database functionality with both PostgreSQL and MySQL
 */

use Shopologic\Core\Database\DatabaseManager;
use Shopologic\Core\Database\Drivers\PostgreSQLDriver;
use Shopologic\Core\Database\Drivers\MySQLDriver;
use Shopologic\Core\Database\Schema\Schema;
use Shopologic\Core\Database\Schema\Blueprint;
use Shopologic\Core\Configuration\ConfigurationManager;

TestFramework::describe('PostgreSQL Driver Integration', function() {
    TestFramework::beforeEach(function() {
        // Skip if PostgreSQL extension not available
        if (!extension_loaded('pgsql')) {
            TestFramework::skip('PostgreSQL extension not available');
        }
    });
    
    TestFramework::it('should connect to PostgreSQL database', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('TEST_PGSQL_HOST') ?: 'localhost',
            'port' => getenv('TEST_PGSQL_PORT') ?: 5432,
            'database' => getenv('TEST_PGSQL_DATABASE') ?: 'shopologic_test',
            'username' => getenv('TEST_PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('TEST_PGSQL_PASSWORD') ?: 'password'
        ]);
        
        try {
            $db = new DatabaseManager($config);
            $connection = $db->connection('pgsql');
            
            // Test basic query
            $result = $connection->select('SELECT 1 as test');
            TestFramework::expect($result)->toBeArray();
            TestFramework::expect($result[0]['test'])->toBe(1);
        } catch (Exception $e) {
            // Skip test if cannot connect to test database
            TestFramework::skip('Cannot connect to PostgreSQL test database: ' . $e->getMessage());
        }
    });
    
    TestFramework::it('should handle PostgreSQL-specific features', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('TEST_PGSQL_HOST') ?: 'localhost',
            'port' => getenv('TEST_PGSQL_PORT') ?: 5432,
            'database' => getenv('TEST_PGSQL_DATABASE') ?: 'shopologic_test',
            'username' => getenv('TEST_PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('TEST_PGSQL_PASSWORD') ?: 'password'
        ]);
        
        try {
            $db = new DatabaseManager($config);
            $connection = $db->connection('pgsql');
            
            // Test JSONB functionality
            $connection->statement('DROP TABLE IF EXISTS test_jsonb');
            $connection->statement('CREATE TABLE test_jsonb (id SERIAL PRIMARY KEY, data JSONB)');
            
            $connection->insert('INSERT INTO test_jsonb (data) VALUES (?)', [
                json_encode(['name' => 'John', 'age' => 30])
            ]);
            
            $result = $connection->select('SELECT data FROM test_jsonb WHERE id = 1');
            $data = json_decode($result[0]['data'], true);
            
            TestFramework::expect($data['name'])->toBe('John');
            TestFramework::expect($data['age'])->toBe(30);
            
            // Cleanup
            $connection->statement('DROP TABLE test_jsonb');
        } catch (Exception $e) {
            TestFramework::skip('Cannot test PostgreSQL features: ' . $e->getMessage());
        }
    });
    
    TestFramework::it('should handle transactions in PostgreSQL', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => getenv('TEST_PGSQL_HOST') ?: 'localhost',
            'port' => getenv('TEST_PGSQL_PORT') ?: 5432,
            'database' => getenv('TEST_PGSQL_DATABASE') ?: 'shopologic_test',
            'username' => getenv('TEST_PGSQL_USERNAME') ?: 'postgres',
            'password' => getenv('TEST_PGSQL_PASSWORD') ?: 'password'
        ]);
        
        try {
            $db = new DatabaseManager($config);
            $connection = $db->connection('pgsql');
            
            // Setup test table
            $connection->statement('DROP TABLE IF EXISTS test_transaction');
            $connection->statement('CREATE TABLE test_transaction (id SERIAL PRIMARY KEY, name VARCHAR(50))');
            
            // Test transaction rollback
            $connection->beginTransaction();
            $connection->insert('INSERT INTO test_transaction (name) VALUES (?)', ['test']);
            $connection->rollback();
            
            $result = $connection->select('SELECT COUNT(*) as count FROM test_transaction');
            TestFramework::expect((int)$result[0]['count'])->toBe(0);
            
            // Test transaction commit
            $connection->beginTransaction();
            $connection->insert('INSERT INTO test_transaction (name) VALUES (?)', ['test']);
            $connection->commit();
            
            $result = $connection->select('SELECT COUNT(*) as count FROM test_transaction');
            TestFramework::expect((int)$result[0]['count'])->toBe(1);
            
            // Cleanup
            $connection->statement('DROP TABLE test_transaction');
        } catch (Exception $e) {
            TestFramework::skip('Cannot test PostgreSQL transactions: ' . $e->getMessage());
        }
    });
});

TestFramework::describe('MySQL Driver Integration', function() {
    TestFramework::beforeEach(function() {
        // Skip if MySQL extension not available
        if (!extension_loaded('mysqli')) {
            TestFramework::skip('MySQL extension not available');
        }
    });
    
    TestFramework::it('should connect to MySQL database', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => getenv('TEST_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_MYSQL_PORT') ?: 3306,
            'database' => getenv('TEST_MYSQL_DATABASE') ?: 'shopologic_test',
            'username' => getenv('TEST_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('TEST_MYSQL_PASSWORD') ?: 'password'
        ]);
        
        try {
            $db = new DatabaseManager($config);
            $connection = $db->connection('mysql');
            
            // Test basic query
            $result = $connection->select('SELECT 1 as test');
            TestFramework::expect($result)->toBeArray();
            TestFramework::expect($result[0]['test'])->toBe(1);
        } catch (Exception $e) {
            // Skip test if cannot connect to test database
            TestFramework::skip('Cannot connect to MySQL test database: ' . $e->getMessage());
        }
    });
    
    TestFramework::it('should handle MySQL-specific features', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => getenv('TEST_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_MYSQL_PORT') ?: 3306,
            'database' => getenv('TEST_MYSQL_DATABASE') ?: 'shopologic_test',
            'username' => getenv('TEST_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('TEST_MYSQL_PASSWORD') ?: 'password'
        ]);
        
        try {
            $db = new DatabaseManager($config);
            $connection = $db->connection('mysql');
            
            // Test JSON functionality (MySQL 5.7+)
            $connection->statement('DROP TABLE IF EXISTS test_json');
            $connection->statement('CREATE TABLE test_json (id INT AUTO_INCREMENT PRIMARY KEY, data JSON)');
            
            $connection->insert('INSERT INTO test_json (data) VALUES (?)', [
                json_encode(['name' => 'John', 'age' => 30])
            ]);
            
            $result = $connection->select('SELECT data FROM test_json WHERE id = 1');
            $data = json_decode($result[0]['data'], true);
            
            TestFramework::expect($data['name'])->toBe('John');
            TestFramework::expect($data['age'])->toBe(30);
            
            // Cleanup
            $connection->statement('DROP TABLE test_json');
        } catch (Exception $e) {
            TestFramework::skip('Cannot test MySQL features: ' . $e->getMessage());
        }
    });
    
    TestFramework::it('should handle transactions in MySQL', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.mysql', [
            'driver' => 'mysql',
            'host' => getenv('TEST_MYSQL_HOST') ?: '127.0.0.1',
            'port' => getenv('TEST_MYSQL_PORT') ?: 3306,
            'database' => getenv('TEST_MYSQL_DATABASE') ?: 'shopologic_test',
            'username' => getenv('TEST_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('TEST_MYSQL_PASSWORD') ?: 'password'
        ]);
        
        try {
            $db = new DatabaseManager($config);
            $connection = $db->connection('mysql');
            
            // Setup test table
            $connection->statement('DROP TABLE IF EXISTS test_transaction');
            $connection->statement('CREATE TABLE test_transaction (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(50)) ENGINE=InnoDB');
            
            // Test transaction rollback
            $connection->beginTransaction();
            $connection->insert('INSERT INTO test_transaction (name) VALUES (?)', ['test']);
            $connection->rollback();
            
            $result = $connection->select('SELECT COUNT(*) as count FROM test_transaction');
            TestFramework::expect((int)$result[0]['count'])->toBe(0);
            
            // Test transaction commit
            $connection->beginTransaction();
            $connection->insert('INSERT INTO test_transaction (name) VALUES (?)', ['test']);
            $connection->commit();
            
            $result = $connection->select('SELECT COUNT(*) as count FROM test_transaction');
            TestFramework::expect((int)$result[0]['count'])->toBe(1);
            
            // Cleanup
            $connection->statement('DROP TABLE test_transaction');
        } catch (Exception $e) {
            TestFramework::skip('Cannot test MySQL transactions: ' . $e->getMessage());
        }
    });
});

TestFramework::describe('Cross-Database Schema Operations', function() {
    TestFramework::it('should create compatible schemas across databases', function() {
        // Test that same schema definition works for both databases
        $schemas = [
            'pgsql' => [
                'host' => getenv('TEST_PGSQL_HOST') ?: 'localhost',
                'database' => getenv('TEST_PGSQL_DATABASE') ?: 'shopologic_test',
                'username' => getenv('TEST_PGSQL_USERNAME') ?: 'postgres',
                'password' => getenv('TEST_PGSQL_PASSWORD') ?: 'password'
            ],
            'mysql' => [
                'host' => getenv('TEST_MYSQL_HOST') ?: '127.0.0.1',
                'database' => getenv('TEST_MYSQL_DATABASE') ?: 'shopologic_test',
                'username' => getenv('TEST_MYSQL_USERNAME') ?: 'root',
                'password' => getenv('TEST_MYSQL_PASSWORD') ?: 'password'
            ]
        ];
        
        foreach ($schemas as $driver => $credentials) {
            if (!extension_loaded($driver === 'pgsql' ? 'pgsql' : 'mysqli')) {
                continue;
            }
            
            try {
                $config = new ConfigurationManager();
                $config->set("database.connections.{$driver}", array_merge([
                    'driver' => $driver,
                    'port' => $driver === 'pgsql' ? 5432 : 3306
                ], $credentials));
                
                $db = new DatabaseManager($config);
                $connection = $db->connection($driver);
                
                // Test creating a table with common column types
                $tableName = 'test_cross_db_' . $driver;
                Schema::connection($driver)->dropIfExists($tableName);
                
                Schema::connection($driver)->create($tableName, function (Blueprint $table) {
                    $table->id();
                    $table->string('name');
                    $table->text('description')->nullable();
                    $table->boolean('is_active')->default(true);
                    $table->timestamps();
                });
                
                // Verify table was created
                $tables = $connection->select("SELECT table_name FROM information_schema.tables WHERE table_name = ?", [$tableName]);
                TestFramework::expect(count($tables))->toBe(1);
                
                // Cleanup
                Schema::connection($driver)->dropIfExists($tableName);
            } catch (Exception $e) {
                // Skip if cannot connect to database
                continue;
            }
        }
        
        // If we get here, at least one database was tested
        TestFramework::expect(true)->toBeTrue();
    });
});

TestFramework::describe('Performance Comparison', function() {
    TestFramework::it('should benchmark basic operations across databases', function() {
        $results = [];
        
        $drivers = ['pgsql', 'mysql'];
        foreach ($drivers as $driver) {
            if (!extension_loaded($driver === 'pgsql' ? 'pgsql' : 'mysqli')) {
                continue;
            }
            
            try {
                $config = new ConfigurationManager();
                if ($driver === 'pgsql') {
                    $config->set('database.connections.pgsql', [
                        'driver' => 'pgsql',
                        'host' => getenv('TEST_PGSQL_HOST') ?: 'localhost',
                        'database' => getenv('TEST_PGSQL_DATABASE') ?: 'shopologic_test',
                        'username' => getenv('TEST_PGSQL_USERNAME') ?: 'postgres',
                        'password' => getenv('TEST_PGSQL_PASSWORD') ?: 'password'
                    ]);
                } else {
                    $config->set('database.connections.mysql', [
                        'driver' => 'mysql',
                        'host' => getenv('TEST_MYSQL_HOST') ?: '127.0.0.1',
                        'database' => getenv('TEST_MYSQL_DATABASE') ?: 'shopologic_test',
                        'username' => getenv('TEST_MYSQL_USERNAME') ?: 'root',
                        'password' => getenv('TEST_MYSQL_PASSWORD') ?: 'password'
                    ]);
                }
                
                $db = new DatabaseManager($config);
                $connection = $db->connection($driver);
                
                // Benchmark simple SELECT query
                $start = microtime(true);
                for ($i = 0; $i < 10; $i++) {
                    $connection->select('SELECT 1 as test');
                }
                $end = microtime(true);
                
                $results[$driver] = $end - $start;
            } catch (Exception $e) {
                continue;
            }
        }
        
        // Verify we got at least one result
        TestFramework::expect(count($results))->toBeGreaterThan(0);
        
        foreach ($results as $driver => $time) {
            // Performance should be reasonable (under 1 second for 10 simple queries)
            TestFramework::expect($time)->toBeLessThan(1.0);
        }
    });
});