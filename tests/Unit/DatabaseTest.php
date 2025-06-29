<?php

declare(strict_types=1);

/**
 * Database Unit Tests
 */

use Shopologic\Core\Database\DatabaseManager;
use Shopologic\Core\Database\Builder;
use Shopologic\Core\Database\QueryBuilder;
use Shopologic\Core\Configuration\ConfigurationManager;

TestFramework::describe('Database Manager', function() {
    TestFramework::it('should create database manager instance', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        TestFramework::expect($db)->toBeInstanceOf(DatabaseManager::class);
    });
    
    TestFramework::it('should create table builders', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        
        $builder = $db->table('users');
        TestFramework::expect($builder)->toBeInstanceOf(Builder::class);
    });
    
    TestFramework::it('should handle raw queries', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        
        // Mock a simple raw query that doesn't require actual database
        $query = $db->raw('SELECT 1 as test');
        TestFramework::expect($query)->toBeInstanceOf('Shopologic\\Core\\Database\\Expression');
    });
});

TestFramework::describe('Query Builder', function() {
    TestFramework::it('should create query builder instance', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        TestFramework::expect($builder)->toBeInstanceOf(QueryBuilder::class);
    });
    
    TestFramework::it('should build select queries', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->select(['id', 'name'])->where('active', true);
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'SELECT') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'FROM "users"') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'WHERE') !== false)->toBeTrue();
    });
    
    TestFramework::it('should build insert queries', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->insert(['name' => 'John', 'email' => 'john@example.com']);
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'INSERT INTO') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, '"users"') !== false)->toBeTrue();
    });
    
    TestFramework::it('should build update queries', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->where('id', 1)->update(['name' => 'Jane']);
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'UPDATE') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'SET') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'WHERE') !== false)->toBeTrue();
    });
    
    TestFramework::it('should build delete queries', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->where('id', 1)->delete();
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'DELETE FROM') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'WHERE') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle joins', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->join('profiles', 'users.id', '=', 'profiles.user_id');
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'JOIN') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, '"profiles"') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle ordering', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->orderBy('name', 'desc');
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'ORDER BY') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'DESC') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle grouping', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->groupBy('status');
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'GROUP BY') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle limits', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->limit(10)->offset(20);
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'LIMIT') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'OFFSET') !== false)->toBeTrue();
    });
    
    TestFramework::it('should handle multiple where conditions', function() {
        $config = new ConfigurationManager();
        $db = new DatabaseManager($config);
        $builder = new QueryBuilder($db->getConnection(), 'users');
        
        $builder->where('active', true)
                ->where('role', 'admin')
                ->orWhere('role', 'manager');
        
        $sql = $builder->toSql();
        
        TestFramework::expect(strpos($sql, 'WHERE') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'AND') !== false)->toBeTrue();
        TestFramework::expect(strpos($sql, 'OR') !== false)->toBeTrue();
    });
});