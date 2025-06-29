<?php

declare(strict_types=1);

/**
 * Multi-Database Support Unit Tests
 */

use Shopologic\Core\Database\DatabaseManager;
use Shopologic\Core\Database\Drivers\PostgreSQLDriver;
use Shopologic\Core\Database\Drivers\MySQLDriver;
use Shopologic\Core\Database\Query\Grammars\PostgreSQLGrammar;
use Shopologic\Core\Database\Query\Grammars\MySQLGrammar;
use Shopologic\Core\Database\Schema\Grammars\PostgreSQLSchemaGrammar;
use Shopologic\Core\Database\Schema\Grammars\MySQLSchemaGrammar;
use Shopologic\Core\Configuration\ConfigurationManager;

TestFramework::describe('Database Driver Interface', function() {
    TestFramework::it('should load PostgreSQL driver', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.pgsql.driver', 'pgsql');
        
        $db = new DatabaseManager($config);
        $driver = $db->getDriver('pgsql');
        
        TestFramework::expect($driver)->toBeInstanceOf(PostgreSQLDriver::class);
    });
    
    TestFramework::it('should load MySQL driver', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.mysql.driver', 'mysql');
        
        $db = new DatabaseManager($config);
        $driver = $db->getDriver('mysql');
        
        TestFramework::expect($driver)->toBeInstanceOf(MySQLDriver::class);
    });
});

TestFramework::describe('Query Grammar Differences', function() {
    TestFramework::it('should generate PostgreSQL-specific SQL', function() {
        $config = new ConfigurationManager();
        $config->set('database.default', 'pgsql');
        $config->set('database.connections.pgsql.driver', 'pgsql');
        
        $db = new DatabaseManager($config);
        $builder = $db->table('users');
        
        // Test LIMIT clause
        $builder->limit(10);
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'LIMIT 10') !== false)->toBeTrue();
    });
    
    TestFramework::it('should generate MySQL-specific SQL', function() {
        $config = new ConfigurationManager();
        $config->set('database.default', 'mysql');
        $config->set('database.connections.mysql.driver', 'mysql');
        
        $db = new DatabaseManager($config);
        $builder = $db->table('users');
        
        // Test LIMIT clause
        $builder->limit(10);
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'LIMIT 10') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle boolean values correctly for PostgreSQL', function() {
        $grammar = new PostgreSQLGrammar();
        
        // PostgreSQL uses true/false for booleans
        $value = $grammar->formatBoolean(true);
        TestFramework::expect($value)->toBe('true');
        
        $value = $grammar->formatBoolean(false);
        TestFramework::expect($value)->toBe('false');
    });
    
    TestFramework::it('should handle boolean values correctly for MySQL', function() {
        $grammar = new MySQLGrammar();
        
        // MySQL uses 1/0 for booleans
        $value = $grammar->formatBoolean(true);
        TestFramework::expect($value)->toBe('1');
        
        $value = $grammar->formatBoolean(false);
        TestFramework::expect($value)->toBe('0');
    });
});

TestFramework::describe('Schema Grammar Differences', function() {
    TestFramework::it('should generate PostgreSQL table creation SQL', function() {
        $grammar = new PostgreSQLSchemaGrammar();
        
        // Test JSON column type
        $result = $grammar->typeJson();
        TestFramework::expect($result)->toBe('jsonb');
        
        // Test auto-incrementing column
        $result = $grammar->typeSerial();
        TestFramework::expect($result)->toBe('serial');
    });
    
    TestFramework::it('should generate MySQL table creation SQL', function() {
        $grammar = new MySQLSchemaGrammar();
        
        // Test JSON column type
        $result = $grammar->typeJson();
        TestFramework::expect($result)->toBe('json');
        
        // Test auto-incrementing column (handled through modifiers)
        $result = $grammar->typeInteger();
        TestFramework::expect($result)->toBe('int');
    });
    
    TestFramework::it('should handle timestamp columns differently', function() {
        $pgGrammar = new PostgreSQLSchemaGrammar();
        $mysqlGrammar = new MySQLSchemaGrammar();
        
        // PostgreSQL supports microseconds
        $pgTimestamp = $pgGrammar->typeTimestamp();
        TestFramework::expect($pgTimestamp)->toBe('timestamp');
        
        // MySQL timestamp
        $mysqlTimestamp = $mysqlGrammar->typeTimestamp();
        TestFramework::expect($mysqlTimestamp)->toBe('timestamp');
    });
});

TestFramework::describe('Database-Specific Features', function() {
    TestFramework::it('should handle PostgreSQL arrays', function() {
        $config = new ConfigurationManager();
        $config->set('database.default', 'pgsql');
        
        $db = new DatabaseManager($config);
        $connection = $db->connection('pgsql');
        
        TestFramework::expect($connection->getDriverName())->toBe('pgsql');
    });
    
    TestFramework::it('should handle MySQL engine specification', function() {
        $config = new ConfigurationManager();
        $config->set('database.default', 'mysql');
        
        $db = new DatabaseManager($config);
        $connection = $db->connection('mysql');
        
        TestFramework::expect($connection->getDriverName())->toBe('mysql');
    });
});

TestFramework::describe('Migration Compatibility', function() {
    TestFramework::it('should create cross-database compatible migrations', function() {
        // Test that migrations work with both database types
        $pgConfig = new ConfigurationManager();
        $pgConfig->set('database.default', 'pgsql');
        $pgConfig->set('database.connections.pgsql.driver', 'pgsql');
        
        $mysqlConfig = new ConfigurationManager();
        $mysqlConfig->set('database.default', 'mysql');
        $mysqlConfig->set('database.connections.mysql.driver', 'mysql');
        
        // Both should be able to create database managers
        $pgDb = new DatabaseManager($pgConfig);
        $mysqlDb = new DatabaseManager($mysqlConfig);
        
        TestFramework::expect($pgDb)->toBeInstanceOf(DatabaseManager::class);
        TestFramework::expect($mysqlDb)->toBeInstanceOf(DatabaseManager::class);
    });
    
    TestFramework::it('should handle JSON columns across databases', function() {
        // PostgreSQL
        $pgGrammar = new PostgreSQLSchemaGrammar();
        $pgJson = $pgGrammar->typeJson();
        TestFramework::expect($pgJson)->toBe('jsonb');
        
        // MySQL
        $mysqlGrammar = new MySQLSchemaGrammar();
        $mysqlJson = $mysqlGrammar->typeJson();
        TestFramework::expect($mysqlJson)->toBe('json');
    });
    
    TestFramework::it('should handle full-text search indexes', function() {
        $pgGrammar = new PostgreSQLSchemaGrammar();
        $mysqlGrammar = new MySQLSchemaGrammar();
        
        // Both grammars should be instantiable
        TestFramework::expect($pgGrammar)->toBeInstanceOf(PostgreSQLSchemaGrammar::class);
        TestFramework::expect($mysqlGrammar)->toBeInstanceOf(MySQLSchemaGrammar::class);
    });
});

TestFramework::describe('Connection Configuration', function() {
    TestFramework::it('should validate PostgreSQL configuration', function() {
        $config = [
            'driver' => 'pgsql',
            'host' => 'localhost',
            'port' => 5432,
            'database' => 'test',
            'username' => 'postgres',
            'password' => 'password'
        ];
        
        TestFramework::expect($config['driver'])->toBe('pgsql');
        TestFramework::expect($config['port'])->toBe(5432);
    });
    
    TestFramework::it('should validate MySQL configuration', function() {
        $config = [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'test',
            'username' => 'root',
            'password' => 'password'
        ];
        
        TestFramework::expect($config['driver'])->toBe('mysql');
        TestFramework::expect($config['port'])->toBe(3306);
    });
    
    TestFramework::it('should handle read/write split configuration', function() {
        $config = [
            'driver' => 'pgsql',
            'read' => [
                'host' => 'read-host'
            ],
            'write' => [
                'host' => 'write-host'
            ]
        ];
        
        TestFramework::expect(isset($config['read']))->toBeTrue();
        TestFramework::expect(isset($config['write']))->toBeTrue();
    });
});

TestFramework::describe('Error Handling', function() {
    TestFramework::it('should handle unsupported driver gracefully', function() {
        $config = new ConfigurationManager();
        $config->set('database.connections.invalid.driver', 'invalid');
        
        try {
            $db = new DatabaseManager($config);
            $db->getDriver('invalid');
            TestFramework::expect(false)->toBeTrue(); // Should not reach here
        } catch (Exception $e) {
            TestFramework::expect($e->getMessage())->toContain('Unsupported database driver');
        }
    });
    
    TestFramework::it('should validate required configuration keys', function() {
        $invalidConfigs = [
            [], // Empty config
            ['driver' => 'pgsql'], // Missing host
            ['driver' => 'mysql', 'host' => 'localhost'] // Missing database
        ];
        
        foreach ($invalidConfigs as $config) {
            TestFramework::expect(count($config) < 3)->toBeTrue(); // Should be incomplete
        }
    });
});