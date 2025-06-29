<?php

declare(strict_types=1);

namespace Shopologic\Core\Database;

/**
 * Prepared statement wrapper
 */
class PreparedStatement implements StatementInterface
{
    private $statement;
    private DatabaseConnection $connection;

    public function __construct($statement, DatabaseConnection $connection)
    {
        $this->statement = $statement;
        $this->connection = $connection;
    }

    public function execute(array $params = []): ResultInterface
    {
        if (is_callable($this->statement)) {
            // PostgreSQL-style (closure)
            return call_user_func($this->statement, $params);
        }
        
        // MySQL-style (mysqli_stmt)
        if ($this->statement instanceof \mysqli_stmt) {
            if (!empty($params)) {
                $types = '';
                $values = [];
                
                foreach ($params as $param) {
                    if (is_int($param)) {
                        $types .= 'i';
                    } elseif (is_float($param)) {
                        $types .= 'd';
                    } elseif (is_bool($param)) {
                        $types .= 'i';
                        $param = $param ? 1 : 0;
                    } else {
                        $types .= 's';
                        $param = (string)$param;
                    }
                    $values[] = $param;
                }
                
                $refs = [];
                $refs[] = $types;
                foreach ($values as $key => $value) {
                    $refs[] = &$values[$key];
                }
                
                call_user_func_array([$this->statement, 'bind_param'], $refs);
            }
            
            if (!$this->statement->execute()) {
                throw new DatabaseException($this->statement->error);
            }
            
            $result = $this->statement->get_result();
            
            if ($result === false) {
                // For non-SELECT queries
                return new MySQLResult(null, $this->connection->getDriver()->getConnection(), $this->statement->affected_rows);
            }
            
            return new MySQLResult($result, $this->connection->getDriver()->getConnection());
        }
        
        throw new DatabaseException('Unknown statement type');
    }

    public function close(): void
    {
        if ($this->statement instanceof \mysqli_stmt) {
            $this->statement->close();
        }
        
        $this->statement = null;
    }
}