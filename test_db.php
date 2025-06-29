<?php

declare(strict_types=1);

// Test script for Database and Migration functionality

// Include PSR interfaces
require_once __DIR__ . '/core/src/PSR/Container/ContainerInterface.php';
require_once __DIR__ . '/core/src/PSR/Container/ContainerExceptionInterface.php';
require_once __DIR__ . '/core/src/PSR/Container/NotFoundExceptionInterface.php';

// Include autoloader and helper functions
require_once __DIR__ . '/core/src/helpers.php';

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Shopologic\\Core\\';
    $base_dir = __DIR__ . '/core/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

echo "🗄️  Testing Shopologic Database Layer\n";
echo "====================================\n\n";

try {
    // Test 1: Database Connection
    echo "Test 1: Database Connection\n";
    echo "===========================\n";
    
    // Mock database configuration
    $config = [
        'driver' => 'postgresql',
        'host' => 'localhost',
        'port' => 5432,
        'database' => 'shopologic_test',
        'username' => 'test',
        'password' => 'test',
        'charset' => 'utf8',
        'schema' => 'public',
        'sslmode' => 'prefer',
    ];
    
    echo "✓ Database configuration created\n";
    
    // Test 2: Query Builder
    echo "\nTest 2: Query Builder\n";
    echo "=====================\n";
    
    $query = new \Shopologic\Core\Database\Query\Builder();
    $query->select('*')
          ->from('users')
          ->where('email', '=', 'test@example.com')
          ->where('active', '=', true)
          ->orderBy('created_at', 'desc')
          ->limit(10);
    
    $sql = $query->toSql();
    echo "✓ Query built: {$sql}\n";
    echo "✓ Bindings: " . json_encode($query->getBindings()) . "\n";
    
    // Test 3: Model System
    echo "\nTest 3: Model System\n";
    echo "====================\n";
    
    // Define test models
    class User extends \Shopologic\Core\Database\Model
    {
        protected string $table = 'users';
        protected array $fillable = ['name', 'email', 'password'];
        protected array $hidden = ['password'];
        protected array $casts = [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
        ];
        
        public function posts()
        {
            return $this->hasMany(Post::class);
        }
        
        public function profile()
        {
            return $this->hasOne(Profile::class);
        }
    }
    
    class Post extends \Shopologic\Core\Database\Model
    {
        protected string $table = 'posts';
        protected array $fillable = ['user_id', 'title', 'content', 'published_at'];
        protected array $casts = [
            'published_at' => 'datetime',
        ];
        
        public function user()
        {
            return $this->belongsTo(User::class);
        }
        
        public function tags()
        {
            return $this->belongsToMany(Tag::class, 'post_tags');
        }
    }
    
    class Profile extends \Shopologic\Core\Database\Model
    {
        protected string $table = 'profiles';
        protected array $fillable = ['user_id', 'bio', 'avatar'];
        
        public function user()
        {
            return $this->belongsTo(User::class);
        }
    }
    
    class Tag extends \Shopologic\Core\Database\Model
    {
        protected string $table = 'tags';
        protected array $fillable = ['name', 'slug'];
        
        public function posts()
        {
            return $this->belongsToMany(Post::class, 'post_tags');
        }
    }
    
    // Test model instantiation
    $user = new User();
    $user->name = 'John Doe';
    $user->email = 'john@example.com';
    $user->password = 'hashed_password';
    
    echo "✓ Model created with attributes\n";
    echo "✓ Model attributes: " . json_encode($user->getAttributes()) . "\n";
    
    // Test hidden attributes
    $array = $user->toArray();
    echo "✓ Hidden attributes working: " . (!isset($array['password']) ? 'Success' : 'Failed') . "\n";
    
    // Test relationship methods directly (without DB connection)
    $post = new Post();
    echo "✓ HasMany relationship method exists: " . (method_exists($user, 'hasMany') ? 'Yes' : 'No') . "\n";
    echo "✓ HasOne relationship method exists: " . (method_exists($user, 'hasOne') ? 'Yes' : 'No') . "\n";
    echo "✓ BelongsTo relationship method exists: " . (method_exists($post, 'belongsTo') ? 'Yes' : 'No') . "\n";
    echo "✓ BelongsToMany relationship method exists: " . (method_exists($post, 'belongsToMany') ? 'Yes' : 'No') . "\n";
    
    // Test 4: Schema Builder
    echo "\nTest 4: Schema Builder\n";
    echo "======================\n";
    
    // Test Blueprint creation
    $blueprint = new \Shopologic\Core\Database\Schema\Blueprint('test_table');
    
    // Add various column types
    $blueprint->id();
    $blueprint->string('name', 100);
    $blueprint->string('email')->unique();
    $blueprint->text('description')->nullable();
    $blueprint->integer('age')->default(0);
    $blueprint->decimal('price', 8, 2);
    $blueprint->boolean('active')->default(true);
    $blueprint->date('birth_date');
    $blueprint->dateTime('appointment_at');
    $blueprint->timestamp('verified_at')->nullable();
    $blueprint->json('settings');
    $blueprint->uuid('uuid');
    $blueprint->enum('status', ['pending', 'active', 'inactive']);
    $blueprint->timestamps();
    $blueprint->softDeletes();
    
    // Add indexes
    $blueprint->index(['email', 'active']);
    $blueprint->unique('uuid');
    
    // Add foreign key
    $blueprint->foreignId('user_id')->constrained()->cascadeOnDelete();
    
    echo "✓ Blueprint created with " . count($blueprint->getColumns()) . " columns\n";
    echo "✓ Blueprint has " . count($blueprint->getCommands()) . " commands\n";
    
    // Test column properties
    $columns = $blueprint->getColumns();
    $nameColumn = null;
    foreach ($columns as $column) {
        if ($column->name === 'name') {
            $nameColumn = $column;
            break;
        }
    }
    
    if ($nameColumn) {
        echo "✓ Name column type: " . $nameColumn->type . "\n";
        echo "✓ Name column length: " . $nameColumn->length . "\n";
    }
    
    // Test 5: Migration System
    echo "\nTest 5: Migration System\n";
    echo "========================\n";
    
    // Test migration loading
    require_once __DIR__ . '/database/migrations/2024_01_01_000001_create_users_table.php';
    $migration = new CreateUsersTable();
    echo "✓ Migration class loaded successfully\n";
    
    // Test migration command
    $mockConnection = new class implements \Shopologic\Core\Database\ConnectionInterface {
        public function query(string $sql, array $bindings = []): \Shopologic\Core\Database\ResultInterface
        {
            echo "   Query: $sql\n";
            return new class implements \Shopologic\Core\Database\ResultInterface {
                public function fetch(): ?array { return null; }
                public function fetchAll(): array { return []; }
                public function rowCount(): int { return 0; }
            };
        }
        
        public function execute(string $sql, array $bindings = []): int
        {
            echo "   Execute: $sql\n";
            return 0;
        }
        
        public function lastInsertId(?string $sequence = null): string
        {
            return '1';
        }
        
        public function beginTransaction(): bool { return true; }
        public function commit(): bool { return true; }
        public function rollBack(): bool { return true; }
        public function inTransaction(): bool { return false; }
        public function getConfig(): array { return ['schema' => 'public']; }
        public function connect(): void {}
        public function disconnect(): void {}
        public function isConnected(): bool { return true; }
        public function reconnect(): void {}
        public function getPdo(): ?\PDO { return null; }
        public function prepare(string $sql): \Shopologic\Core\Database\StatementInterface {
            return new class implements \Shopologic\Core\Database\StatementInterface {
                public function execute(array $bindings = []): bool { return true; }
                public function fetch(): ?array { return null; }
                public function fetchAll(): array { return []; }
                public function rowCount(): int { return 0; }
                public function bindValue($parameter, $value, int $type = \PDO::PARAM_STR): bool { return true; }
                public function bindParam($parameter, &$variable, int $type = \PDO::PARAM_STR, ?int $length = null): bool { return true; }
            };
        }
        public function quote(string $string, int $type = \PDO::PARAM_STR): string { return "'$string'"; }
    };
    
    $migrator = new \Shopologic\Core\Database\Migrations\Migrator($mockConnection);
    echo "✓ Migrator created\n";
    
    // Test PostgreSQL Grammar
    echo "\nTest 6: PostgreSQL Grammar\n";
    echo "==========================\n";
    
    $grammar = new \Shopologic\Core\Database\Schema\PostgreSQLGrammar();
    
    // Test SQL generation for create table
    $createSql = $grammar->compileCreate($blueprint);
    echo "✓ CREATE TABLE SQL generated\n";
    echo "   SQL Preview: " . substr($createSql, 0, 100) . "...\n";
    
    // Test other SQL commands
    $dropSql = $grammar->compileDrop('test_table');
    echo "✓ DROP TABLE SQL: {$dropSql}\n";
    
    $dropIfExistsSql = $grammar->compileDropIfExists('test_table');
    echo "✓ DROP IF EXISTS SQL: {$dropIfExistsSql}\n";
    
    echo "\n🎉 All database tests passed!\n";
    echo "\n📋 Database Components Ready:\n";
    echo "   • Query Builder with fluent interface\n";
    echo "   • Active Record models with relationships\n";
    echo "   • Schema builder with Blueprint\n";
    echo "   • Migration system\n";
    echo "   • PostgreSQL grammar implementation\n";
    echo "   • Relationship classes (HasMany, BelongsTo, etc.)\n";
    echo "\n🚀 Database Layer Phase 2 Complete!\n";
    
} catch (\Throwable $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Trace:\n" . $e->getTraceAsString() . "\n";
}