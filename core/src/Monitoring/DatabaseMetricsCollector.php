<?php

declare(strict_types=1);

namespace Shopologic\Core\Monitoring;

use Shopologic\Core\Configuration\ConfigurationManager;

/**
 * Database Metrics Collector
 * 
 * Collects database performance and usage metrics
 */
class DatabaseMetricsCollector implements MetricsCollectorInterface
{
    private ConfigurationManager $config;
    private ?\PDO $connection = null;
    
    public function __construct()
    {
        $this->config = new ConfigurationManager();
    }
    
    /**
     * Collect database metrics
     */
    public function collect(): array
    {
        return [
            'connection' => $this->getConnectionMetrics(),
            'performance' => $this->getPerformanceMetrics(),
            'queries' => $this->getQueryMetrics(),
            'tables' => $this->getTableMetrics(),
            'locks' => $this->getLockMetrics(),
            'replication' => $this->getReplicationMetrics()
        ];
    }
    
    /**
     * Get database connection metrics
     */
    private function getConnectionMetrics(): array
    {
        $metrics = [
            'host' => $this->config->get('database.host', 'unknown'),
            'database' => $this->config->get('database.database', 'unknown'),
            'driver' => $this->config->get('database.connection', 'unknown'),
            'connected' => false,
            'connection_time' => 0,
            'version' => 'unknown'
        ];
        
        try {
            $start = microtime(true);
            $connection = $this->getConnection();
            $metrics['connection_time'] = (microtime(true) - $start) * 1000;
            $metrics['connected'] = true;
            
            // Get database version
            $versionResult = $connection->query('SELECT version()');
            if ($versionResult) {
                $metrics['version'] = $versionResult->fetchColumn();
            }
            
            // Get connection count
            $connResult = $connection->query("SELECT count(*) FROM pg_stat_activity");
            if ($connResult) {
                $metrics['active_connections'] = (int)$connResult->fetchColumn();
            }
            
            // Get max connections
            $maxConnResult = $connection->query("SHOW max_connections");
            if ($maxConnResult) {
                $metrics['max_connections'] = (int)$maxConnResult->fetchColumn();
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get database performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        $metrics = [
            'cache_hit_ratio' => 0,
            'transactions_per_second' => 0,
            'deadlocks' => 0,
            'slow_queries' => 0
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Cache hit ratio
            $cacheResult = $connection->query("
                SELECT 
                    round(
                        (sum(heap_blks_hit) / (sum(heap_blks_hit) + sum(heap_blks_read))) * 100, 2
                    ) as cache_hit_ratio
                FROM pg_statio_user_tables 
                WHERE sum(heap_blks_hit) + sum(heap_blks_read) > 0
            ");
            
            if ($cacheResult) {
                $metrics['cache_hit_ratio'] = (float)$cacheResult->fetchColumn();
            }
            
            // Transaction statistics
            $txnResult = $connection->query("
                SELECT 
                    xact_commit + xact_rollback as total_transactions,
                    xact_commit,
                    xact_rollback,
                    deadlocks
                FROM pg_stat_database 
                WHERE datname = current_database()
            ");
            
            if ($txnResult) {
                $txnData = $txnResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['total_transactions'] = (int)$txnData['total_transactions'];
                $metrics['committed_transactions'] = (int)$txnData['xact_commit'];
                $metrics['rolled_back_transactions'] = (int)$txnData['xact_rollback'];
                $metrics['deadlocks'] = (int)$txnData['deadlocks'];
            }
            
            // Buffer usage
            $bufferResult = $connection->query("
                SELECT 
                    buffers_alloc,
                    buffers_checkpoint,
                    buffers_clean,
                    buffers_backend
                FROM pg_stat_bgwriter
            ");
            
            if ($bufferResult) {
                $bufferData = $bufferResult->fetch(\PDO::FETCH_ASSOC);
                $metrics['buffer_allocations'] = (int)$bufferData['buffers_alloc'];
                $metrics['checkpoint_buffers'] = (int)$bufferData['buffers_checkpoint'];
                $metrics['clean_buffers'] = (int)$bufferData['buffers_clean'];
                $metrics['backend_buffers'] = (int)$bufferData['buffers_backend'];
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get query metrics
     */
    private function getQueryMetrics(): array
    {
        $metrics = [
            'total_queries' => 0,
            'slow_queries' => [],
            'most_time_consuming' => [],
            'most_called' => []
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Get query statistics (requires pg_stat_statements extension)
            $queryStatsResult = $connection->query("
                SELECT 
                    query,
                    calls,
                    total_time,
                    mean_time,
                    rows,
                    100.0 * shared_blks_hit / nullif(shared_blks_hit + shared_blks_read, 0) AS hit_percent
                FROM pg_stat_statements 
                ORDER BY total_time DESC 
                LIMIT 10
            ");
            
            if ($queryStatsResult) {
                while ($row = $queryStatsResult->fetch(\PDO::FETCH_ASSOC)) {
                    $queryInfo = [
                        'query' => substr($row['query'], 0, 100) . '...',
                        'calls' => (int)$row['calls'],
                        'total_time' => (float)$row['total_time'],
                        'mean_time' => (float)$row['mean_time'],
                        'rows' => (int)$row['rows'],
                        'hit_percent' => (float)$row['hit_percent']
                    ];
                    
                    $metrics['most_time_consuming'][] = $queryInfo;
                    
                    // Consider slow if mean time > 1000ms
                    if ($queryInfo['mean_time'] > 1000) {
                        $metrics['slow_queries'][] = $queryInfo;
                    }
                }
            }
            
            // Get total query count
            $totalResult = $connection->query("SELECT sum(calls) FROM pg_stat_statements");
            if ($totalResult) {
                $metrics['total_queries'] = (int)$totalResult->fetchColumn();
            }
            
        } catch (\Exception $e) {
            // pg_stat_statements might not be available
            $metrics['error'] = 'Query statistics not available (pg_stat_statements extension required)';
        }
        
        return $metrics;
    }
    
    /**
     * Get table metrics
     */
    private function getTableMetrics(): array
    {
        $metrics = [
            'table_count' => 0,
            'total_size' => 0,
            'largest_tables' => [],
            'table_statistics' => []
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Get table count
            $countResult = $connection->query("
                SELECT count(*) 
                FROM information_schema.tables 
                WHERE table_schema = 'public'
            ");
            
            if ($countResult) {
                $metrics['table_count'] = (int)$countResult->fetchColumn();
            }
            
            // Get table sizes
            $sizeResult = $connection->query("
                SELECT 
                    schemaname,
                    tablename,
                    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
                    pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
                FROM pg_tables 
                WHERE schemaname = 'public'
                ORDER BY size_bytes DESC 
                LIMIT 10
            ");
            
            if ($sizeResult) {
                $totalSize = 0;
                while ($row = $sizeResult->fetch(\PDO::FETCH_ASSOC)) {
                    $tableInfo = [
                        'name' => $row['tablename'],
                        'size' => $row['size'],
                        'size_bytes' => (int)$row['size_bytes']
                    ];
                    
                    $metrics['largest_tables'][] = $tableInfo;
                    $totalSize += $tableInfo['size_bytes'];
                }
                $metrics['total_size'] = $totalSize;
            }
            
            // Get table statistics
            $statsResult = $connection->query("
                SELECT 
                    schemaname,
                    tablename,
                    n_tup_ins as inserts,
                    n_tup_upd as updates,
                    n_tup_del as deletes,
                    n_live_tup as live_tuples,
                    n_dead_tup as dead_tuples,
                    last_vacuum,
                    last_autovacuum,
                    last_analyze,
                    last_autoanalyze
                FROM pg_stat_user_tables 
                ORDER BY n_tup_ins + n_tup_upd + n_tup_del DESC 
                LIMIT 5
            ");
            
            if ($statsResult) {
                while ($row = $statsResult->fetch(\PDO::FETCH_ASSOC)) {
                    $metrics['table_statistics'][] = [
                        'name' => $row['tablename'],
                        'inserts' => (int)$row['inserts'],
                        'updates' => (int)$row['updates'],
                        'deletes' => (int)$row['deletes'],
                        'live_tuples' => (int)$row['live_tuples'],
                        'dead_tuples' => (int)$row['dead_tuples'],
                        'last_vacuum' => $row['last_vacuum'],
                        'last_autovacuum' => $row['last_autovacuum'],
                        'last_analyze' => $row['last_analyze'],
                        'last_autoanalyze' => $row['last_autoanalyze']
                    ];
                }
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get lock metrics
     */
    private function getLockMetrics(): array
    {
        $metrics = [
            'active_locks' => 0,
            'waiting_locks' => 0,
            'lock_types' => [],
            'blocking_queries' => []
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Get lock counts
            $lockResult = $connection->query("
                SELECT 
                    mode,
                    count(*) as count,
                    count(*) FILTER (WHERE granted = false) as waiting
                FROM pg_locks 
                GROUP BY mode
                ORDER BY count DESC
            ");
            
            if ($lockResult) {
                $totalLocks = 0;
                $totalWaiting = 0;
                
                while ($row = $lockResult->fetch(\PDO::FETCH_ASSOC)) {
                    $count = (int)$row['count'];
                    $waiting = (int)$row['waiting'];
                    
                    $metrics['lock_types'][] = [
                        'mode' => $row['mode'],
                        'count' => $count,
                        'waiting' => $waiting
                    ];
                    
                    $totalLocks += $count;
                    $totalWaiting += $waiting;
                }
                
                $metrics['active_locks'] = $totalLocks;
                $metrics['waiting_locks'] = $totalWaiting;
            }
            
            // Get blocking queries
            $blockingResult = $connection->query("
                SELECT 
                    blocked_locks.pid AS blocked_pid,
                    blocked_activity.usename AS blocked_user,
                    blocking_locks.pid AS blocking_pid,
                    blocking_activity.usename AS blocking_user,
                    blocked_activity.query AS blocked_statement,
                    blocking_activity.query AS current_statement_in_blocking_process
                FROM pg_catalog.pg_locks blocked_locks
                JOIN pg_catalog.pg_stat_activity blocked_activity ON blocked_activity.pid = blocked_locks.pid
                JOIN pg_catalog.pg_locks blocking_locks ON blocking_locks.locktype = blocked_locks.locktype
                    AND blocking_locks.DATABASE IS NOT DISTINCT FROM blocked_locks.DATABASE
                    AND blocking_locks.relation IS NOT DISTINCT FROM blocked_locks.relation
                    AND blocking_locks.page IS NOT DISTINCT FROM blocked_locks.page
                    AND blocking_locks.tuple IS NOT DISTINCT FROM blocked_locks.tuple
                    AND blocking_locks.virtualxid IS NOT DISTINCT FROM blocked_locks.virtualxid
                    AND blocking_locks.transactionid IS NOT DISTINCT FROM blocked_locks.transactionid
                    AND blocking_locks.classid IS NOT DISTINCT FROM blocked_locks.classid
                    AND blocking_locks.objid IS NOT DISTINCT FROM blocked_locks.objid
                    AND blocking_locks.objsubid IS NOT DISTINCT FROM blocked_locks.objsubid
                    AND blocking_locks.pid != blocked_locks.pid
                JOIN pg_catalog.pg_stat_activity blocking_activity ON blocking_activity.pid = blocking_locks.pid
                WHERE NOT blocked_locks.GRANTED
                LIMIT 5
            ");
            
            if ($blockingResult) {
                while ($row = $blockingResult->fetch(\PDO::FETCH_ASSOC)) {
                    $metrics['blocking_queries'][] = [
                        'blocked_pid' => (int)$row['blocked_pid'],
                        'blocked_user' => $row['blocked_user'],
                        'blocking_pid' => (int)$row['blocking_pid'],
                        'blocking_user' => $row['blocking_user'],
                        'blocked_query' => substr($row['blocked_statement'], 0, 100) . '...',
                        'blocking_query' => substr($row['current_statement_in_blocking_process'], 0, 100) . '...'
                    ];
                }
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get replication metrics
     */
    private function getReplicationMetrics(): array
    {
        $metrics = [
            'is_master' => false,
            'is_replica' => false,
            'replicas' => [],
            'replication_lag' => 0
        ];
        
        try {
            $connection = $this->getConnection();
            
            // Check if this is a master
            $masterResult = $connection->query("SELECT pg_is_in_recovery()");
            if ($masterResult) {
                $metrics['is_replica'] = (bool)$masterResult->fetchColumn();
                $metrics['is_master'] = !$metrics['is_replica'];
            }
            
            if ($metrics['is_master']) {
                // Get replica information
                $replicaResult = $connection->query("
                    SELECT 
                        client_addr,
                        client_hostname,
                        client_port,
                        state,
                        sent_lsn,
                        write_lsn,
                        flush_lsn,
                        replay_lsn,
                        write_lag,
                        flush_lag,
                        replay_lag
                    FROM pg_stat_replication
                ");
                
                if ($replicaResult) {
                    while ($row = $replicaResult->fetch(\PDO::FETCH_ASSOC)) {
                        $metrics['replicas'][] = [
                            'client_addr' => $row['client_addr'],
                            'client_hostname' => $row['client_hostname'],
                            'client_port' => (int)$row['client_port'],
                            'state' => $row['state'],
                            'write_lag' => $row['write_lag'],
                            'flush_lag' => $row['flush_lag'],
                            'replay_lag' => $row['replay_lag']
                        ];
                    }
                }
            }
            
            if ($metrics['is_replica']) {
                // Get replication lag for replica
                $lagResult = $connection->query("
                    SELECT 
                        EXTRACT(EPOCH FROM (now() - pg_last_xact_replay_timestamp())) AS lag_seconds
                ");
                
                if ($lagResult) {
                    $metrics['replication_lag'] = (float)$lagResult->fetchColumn();
                }
            }
            
        } catch (\Exception $e) {
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }
    
    /**
     * Get database connection
     */
    private function getConnection(): \PDO
    {
        if ($this->connection === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s',
                $this->config->get('database.host'),
                $this->config->get('database.port', 5432),
                $this->config->get('database.database')
            );
            
            $this->connection = new \PDO(
                $dsn,
                $this->config->get('database.username'),
                $this->config->get('database.password'),
                [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
                ]
            );
        }
        
        return $this->connection;
    }
}