# Advanced MY_Model for CodeIgniter 3 🚀

A powerful extension of CodeIgniter 3's base Model class that brings modern ORM features to your CI3 applications. This package introduces Laravel-style eloquent features, advanced query capabilities, and robust database interaction layers while maintaining CodeIgniter's simplicity.

## ⚠️ Warning

**DO NOT USE THIS PACKAGE IN PRODUCTION**

This package is under active development and may contain critical bugs. It is primarily intended for personal use and testing. The current version has not undergone rigorous testing and may be unstable.

## 📝 Requirements

- PHP >= 8.0
- CodeIgniter 3.x
- `MySQL` Database



Add this line in `composer.json` before install or update.

```bash
"scripts": {
    "post-install-cmd": [
        "@php vendor/onlyphp/codeigniter3-model/scripts/install.php"
    ],
    "post-update-cmd": [
        "@php vendor/onlyphp/codeigniter3-model/scripts/update.php"
    ]
}
```

## 🔧 Installation

```bash
composer require onlyphp/codeigniter3-model
```
## ✨ Key Features

- 🔄 **Laravel Eloquent-style Query Builder**: Write expressive and chainable database queries like laravel
- 🔗 **Smart Relationship Handling**: Define and manage model relationships effortlessly
- 🚀 **Eager Loading**: Solve the N+1 query problem with efficient data loading
- 🛡️ **Security Layer**: XSS protection and output escaping
- ♻️ **Soft Deletes**: Safely handle record deletion with recovery options
- ✅ **Automatic Validation**: Built-in validation when creating or updating records
- 📝 **Advanced Pagination**: Flexible pagination with AJAX support
- 🎯 **Raw Query Support**: Execute complex custom SQL when needed
- 📦 **Batch Operations**: Efficient handling of multiple records

## 📚 Basic Model Configuration

Here's a complete example of how to set up your model with all available configurations:

```php
<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');

class User_model extends MY_Model
{
    // Database Configuration
    public $connection = 'default';       // Database connection group from config (OPTIONAL)
    public $table = 'users';              // Table name (REQUIRED)
    public $primaryKey = 'id';            // Primary key column (REQUIRED)
    
    // Fillable & Protected Fields
    public $fillable = [                  // Fields that can be mass assigned (REQUIRED)
        'name',
        'email',
        'password',
        'status'
    ];

    public $protected = ['id'];           // Fields that cannot be mass assigned (OPTIONAL)
    
    // Timestamp Configuration
    public $timestamps = TRUE;            // Enable/disable timestamps (OPTIONAL)
    public $timestamps_format = 'Y-m-d H:i:s'; // Define format, Default is Y-m-d H:i:s (OPTIONAL)
    public $timezone = 'Asia/Kuala_Lumpur'; // Define timezone, Default is Asia/Kuala_Lumpur (OPTIONAL)
    public $created_at = 'created_at';    // Created at column name (OPTIONAL)
    public $updated_at = 'updated_at';    // Updated at column name (OPTIONAL)
    public $deleted_at = 'deleted_at';    // Deleted at column name (OPTIONAL)
    
    // Soft Delete Configuration
    public $softDelete = true;            // Enable/disable soft deletes, Default is false (OPTIONAL)
    
    // Query Result Modifications
    public $appends = ['full_name'];      // Append custom attributes (OPTIONAL)
    public $hidden = ['password'];        // Hide specific columns (OPTIONAL)
    
    // Validation Rules
    public $_validationRules = [          // General validation rules
        'email' => 'required|valid_email|is_unique[users.email]',
        'name' => 'required|min_length[3]'
    ];
    
    public $_insertValidation = [         // Create-specific validation, if declare will override the _validationRules during create operation (OPTIONAL)
        'password' => 'required|min_length[6]'
    ];
    
    public $_updateValidation = [         // Update-specific validation, if declare will override the _validationRules during update operation (OPTIONAL)
        'password' => 'min_length[6]'
    ];

    public $_validationLang = 'english'; // Used to set validation language for error message, default is english (OPTIONAL)

    public function __construct()
    {
        parent::__construct();
    }

    // Custom attribute accessor
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }
}
```
## 📚 Usage Examples

### Basic Query Operations

```php
// Basic query with where clause
$users = $this->user_model
    ->where('status', 'active')
    ->orderBy('created_at', 'desc')
    ->get();

// Complex where conditions
$posts = $this->post_model
    ->whereNotNull('published_at')
    ->whereBetween('created_at', ['2024-01-01', '2024-12-31'])
    ->get();
```

### Soft Deletes

```php
// Enable soft deletes in your model
protected $softDelete = true;
protected $deleted_at = 'deleted_at'; // (OPTIONAL) Default is deleted_at

// Soft delete a record
$this->user_model->destroy($id);

// Include soft deleted records in query
$allUsers = $this->user_model
    ->withTrashed()
    ->get();

// Restore soft deleted record
$this->user_model->restore($id);
```

## 🔄 Relationships and Eager Loading

### Defining Relationships

```php
// User Model with relationships
class User_model extends MY_Model
{
    public $table = 'users';
    public $primaryKey = 'id';

    public function posts()
    {
        return $this->hasMany('Post_model', 'user_id', 'id');
    }

    public function profile()
    {
        return $this->hasOne('Profile_model', 'user_id', 'id');
    }
}

// Using relationships with eager loading
$users = $this->user_model
    ->with(['posts' => function($query) {
        $query->select('id, title, content')
            ->where('status', 'published');
    }])
    ->with('profile')
    ->get();
```

## 📄 Pagination Examples

### Basic Pagination

```php
// Controller method for basic pagination
public function listUsers()
{
    $search = $this->input->get('search');
    $page = $this->input->get('page', 1);
    
    $users = $this->user_model
        ->where('status', 'active')
        ->paginate(10, $page, $search);
        
    $this->load->view('users/list', ['users' => $users]);
}
```

### Ajax DataTables Integration

```php
// Controller method for DataTables
public function getUsersData()
{
    $paginateData = $this->user_model
        ->setPaginateFilterColumn([
            null,           // Row number column
            'name',         // Searchable columns
            'email',
            'status'
        ])
        ->with('profile')  // Eager load relationships
        ->paginate_ajax($_POST);
        
    // Format the response
    if (!empty($paginateData['data'])) {
        foreach ($paginateData['data'] as $key => $user) {
            $paginateData['data'][$key] = [
                ($key + 1),
                $user['name'],
                $user['email'],
                $user['profile']['phone'],
                $this->_generateActions($user['id'])
            ];
        }
    }
    
    echo json_encode($paginateData);
}

private function _generateActions($id)
{
    return '
        <button onclick="editUser('.$id.')" class="btn btn-sm btn-primary">Edit</button>
        <button onclick="deleteUser('.$id.')" class="btn btn-sm btn-danger">Delete</button>
    ';
}
```

## 🔒 Security Features

### XSS Protection

```php
// Enable XSS protection for all output
$users = $this->user_model
    ->safeOutput()
    ->get();

// Exclude specific fields from XSS protection
$posts = $this->post_model
    ->safeOutputWithException(['content', 'html_description'])
    ->get();
```

### Validation

```php
// Model with validation rules
class User_model extends MY_Model
{
    public $_validationRules = [
        'email' => 'required|valid_email|is_unique[users.email]',
        'username' => 'required|alpha_numeric|min_length[4]|max_length[20]',
        'password' => 'required|min_length[6]'
    ];
}

// Controller
class UserController extends CI_Controller
{
    public function singleData()
    {
        // Create with validation
        $userData = [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ];

        // Create record with validation
        $response = $this->user_model->create($userData);
    }

    public function multipleData()
    {
        // Batch create multiple records
        $usersData = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com']
        ];

        $this->user_model->batchCreate($usersData);
    }
}
```

## 🔍 Advanced Query Examples

```php
// Complex query with multiple conditions
$activeUsers = $this->user_model
    ->select('users.*, COUNT(posts.id) as post_count')
    ->leftJoin('posts', 'posts.user_id = users.id')
    ->where('users.status', 'active')
    ->whereYear('users.created_at', '>=', date('Y'))
    ->whereExists(function($query) {
        $query->select(1)
            ->from('user_logins')
            ->whereRaw('user_logins.user_id = users.id');
    })
    ->groupBy('users.id')
    ->having('post_count >', 5)
    ->orderBy('post_count', 'DESC')
    ->with(['profile', 'settings'])
    ->get();

// Batch operations
$this->user_model->batchCreate([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com']
]);

// Transaction example
$this->db->trans_begin();
try {
    $userId = $this->user_model->create($userData);
    $this->profile_model->create(['user_id' => $userId, ...]);
    $this->db->trans_commit();
} catch (Exception $e) {
    $this->db->trans_rollback();
    throw $e;
}

// Complex query with multiple conditions
$users = $this->user_model
    ->select('users.*, roles.name as role_name')
    ->where('status', 'active')
    ->whereYear('created_at', '2024')
    ->whereNotNull('email_verified_at')
    ->leftJoin('roles', 'roles.id = users.role_id')
    ->orderBy('created_at', 'DESC')
    ->get();

// Chunk processing for large datasets
$this->user_model->chunk(100, function($users) {
    foreach ($users as $user) {
        // Process each user
    }
});

// ---------------
// PLUCK EXAMPLES
// ---------------

// Example 1: Get all usernames as a simple array
$usernames = $this->user_model->pluck('username');
// Result: ['john_doe', 'jane_smith', 'bob_jones', ...]

// Example 2: Get usernames keyed by user ID
$usernamesById = $this->user_model->pluck('username', 'id');
// Result: [1 => 'john_doe', 2 => 'jane_smith', 3 => 'bob_jones', ...]

// Example 3: Get values from related models using dot notation
$this->user_model->with('profile');
$userCities = $this->user_model->pluck('profile.city');
// Result: ['New York', 'Los Angeles', 'Chicago', ...]

// Example 4: Get values from related models with custom keys
$this->user_model->with(['profile', 'role']);
$userCitiesByRole = $this->user_model->pluck('profile.city', 'role.name');
// Result: ['admin' => 'New York', 'editor' => 'Los Angeles', ...]

// ---------------
// LAZY EXAMPLES
// ---------------

// Setup for examples
$users = $this->User_model
    ->where('status', 'active')
    ->lazy(200);

// Example 1: count() - Get the total number of items in the collection
$totalUsers = $users->count();
echo "Total active users: " . $totalUsers;
// Note: count() iterates through the entire collection the first time
// it's called, then caches the result for subsequent calls

// Example 2: first() - Get the first item from the collection
// Without callback
$firstUser = $users->first();
echo "First user ID: " . $firstUser['id'];

// With callback to find specific item
$adminUser = $users->first(function($user) {
    return $user['role'] === 'admin';
}, null);
echo "First admin user: " . ($adminUser ? $adminUser['username'] : 'No admin found');

// Example 3: pluck() - Extract a specific key from all items
$usernames = $users->pluck('username');
foreach ($usernames as $username) {
    echo "Username: " . $username . "<br>";
}

// Example 4: chunk() - Split the collection into smaller collections
$userChunks = $users->chunk(5);
foreach ($userChunks as $index => $chunk) {
    echo "Processing chunk #" . ($index + 1) . "<br>";
    foreach ($chunk as $user) {
        echo "- Processing user: " . $user['username'] . "<br>";
    }
}

// Example 5: map() - Transform each item in the collection
$transformedUsers = $users->map(function($user) {
    $user['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
    $user['is_adult'] = $user['age'] >= 18;
    return $user;
});

foreach ($transformedUsers as $user) {
    echo $user['full_name'] . " is " . ($user['is_adult'] ? 'an adult' : 'a minor') . "<br>";
}

// Example 6: filter() - Keep only items that pass the truth test
$adultUsers = $users->filter(function($user) {
    return $user['age'] >= 18;
});

foreach ($adultUsers as $user) {
    echo "Adult user: " . $user['username'] . " (age: " . $user['age'] . ")<br>";
}

// Example 7: reject() - Remove items that pass the truth test
$nonAdminUsers = $users->reject(function($user) {
    return $user['role'] === 'admin';
});

foreach ($nonAdminUsers as $user) {
    echo "Non-admin user: " . $user['username'] . "<br>";
}

// Example 8: each() - Execute code for each item
$users->each(function($user) {
    echo "Processing user " . $user['id'] . ": " . $user['username'] . "<br>";
    // Perform operations on the user
    return true; // continue iteration (return false to break)
});

// Example 9: tap() - Perform an operation on the collection and return it
$users->tap(function($collection) {
    echo "The collection has " . $collection->count() . " items.<br>";
})->each(function($user) {
    // Process each user
});

// Example 10: all() - Get all items as an array
$allUsers = $users->all();
print_r($allUsers); // Full array of all users

// Example 11: implode() - Concatenate values of a given key as a string
$allUsernames = $users->implode('username', ', ');
echo "All usernames: " . $allUsernames;

// Example 12: take() - Get a specific number of items
$firstTenUsers = $users->take(10);
foreach ($firstTenUsers as $user) {
    echo "User from first 10: " . $user['username'] . "<br>";
}

// Example 13: skip() - Skip a specific number of items
$afterFirstHundred = $users->skip(100)->take(10);
foreach ($afterFirstHundred as $user) {
    echo "User after first 100: " . $user['username'] . "<br>";
}

// Example 14: Chaining multiple methods
$result = $this->User_model
    ->where('status', 'active')
    ->lazy(150)
    ->filter(function($user) {
        return $user['age'] >= 21;
    })
    ->map(function($user) {
        $user['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
        return $user;
    })
    ->skip(10)
    ->take(5)
    ->pluck('full_name')
    ->all();

print_r($result); // Array of 5 full names, after skipping the first 10

// Example 15: Using setChunkSize to change chunk size after creation
$users = $this->User_model->lazy(); // Default chunk size
$users->setChunkSize(50); // Change to smaller chunks for memory optimization

// Example 16: Using lazy collection with complex queries
$specialUsers = $this->User_model
    ->with(['profile', 'orders'])
    ->where('status', 'active')
    ->where('created_at >', date('Y-m-d', strtotime('-30 days')))
    ->orderBy('created_at', 'DESC')
    ->lazy(75)
    ->filter(function($user) {
        // Only users with complete profiles
        return !empty($user['profile']) && 
               !empty($user['profile']['address']) && 
               !empty($user['profile']['phone']);
    });

// Example 17: Memory-efficient large data export
$fileName = 'users_export_' . date('Y-m-d') . '.csv';
$file = fopen($fileName, 'w');
fputcsv($file, ['ID', 'Username', 'Email', 'Full Name', 'Created Date']);

$this->User_model
    ->lazy(200)
    ->each(function($user) use ($file) {
        fputcsv($file, [
            $user['id'],
            $user['username'],
            $user['email'],
            $user['first_name'] . ' ' . $user['last_name'],
            $user['created_at']
        ]);
    });

fclose($file);
echo "Export completed to $fileName";

// Example 18: Data aggregation without loading everything into memory
$stats = [
    'total' => 0,
    'age_sum' => 0,
    'min_age' => PHP_INT_MAX,
    'max_age' => 0,
    'by_country' => []
];

$this->User_model
    ->lazy(300)
    ->each(function($user) use (&$stats) {
        $stats['total']++;
        $stats['age_sum'] += $user['age'];
        $stats['min_age'] = min($stats['min_age'], $user['age']);
        $stats['max_age'] = max($stats['max_age'], $user['age']);
        
        $country = $user['country'];
        if (!isset($stats['by_country'][$country])) {
            $stats['by_country'][$country] = 0;
        }
        $stats['by_country'][$country]++;
    });

$stats['avg_age'] = $stats['age_sum'] / $stats['total'];
print_r($stats);

// ---------------
// FILTER EXAMPLES
// ---------------

// Example 1: Filter active premium users
$premiumUsers = $this->user_model->filter(function($user) {
    return $user['subscription_type'] === 'premium' && $user['is_active'] === 1;
});

// ---------------
// SORT BY EXAMPLES
// ---------------

// Example 1: Get all users with their relationships and sort by name
$users = $this->User_model->with('profile', 'profile.roles', 'department')
    ->setAppends(['status_badge'])
    ->safeOutputWithException(['status_badge'])
    ->withTrashed()
    ->sortBy('name');

// Example 2: Sort by role rank (from the first profile record)
$sortedByRoleRank = $this->User_model->sortBy('profile.0.roles.role_rank', SORT_DESC);

// Example 3: Sort by department name (handles null values)
$sortedByDept = $this->User_model->sortBy(function($user) {
    return $user['department'] ? $user['department']['department_name'] : null;
});

// Example 4: Sort by multiple criteria - first by department name, then by name
$sortedMultiple = $this->User_model->sortByMultiple([
    ['department.department_name', 'asc'],
    ['name', 'asc']
]);

// Example 5: Sort by main profile role rank
$sortedByMainRole = $this->User_model->sortBy(function($user) {
    if (empty($user['profile'])) return null;
    
    // Find the main profile
    foreach ($user['profile'] as $profile) {
        if ($profile['is_main'] == '1') {
            return $profile['roles']['role_rank'];
        }
    }
    
    return null;
});

// ---------------
// EXISTS / DOES NOT EXISTS EXAMPLES
// ---------------

// Example 1: Check if there are any pending orders
if ($this->order_model->where('status', 'pending')->doesntExist()) {
    // Logic process
}

// Example 2: Show empty state for products
if ($this->product_model->where('category_id', 5)->exists()) {
    // Logic process
}

// Example 3: Set the value to the variable
$hasAdmin = $this->User_model->where('is_role', 'superadmin')->exists();

// ---------------
// CONTAINS EXAMPLES
// ---------------

// Example 1: Check if any premium users exist
$hasPremiumUsers = $this->user_model->contains('subscription_type', 'premium');

// Example 2: Check with callback
$hasNewOrders = $this->order_model->contains(function($order) {
    return strtotime($order['created_at']) > strtotime('-24 hours');
});

$hasITDepartment = $this->User_model->contains(function($user) {
    return isset($user['department']) && $user['department']['department_code'] === 'IT';
});

// Example 3: Check if any superadmin users exist
$hasSuperAdmin = $this->User_model->contains('name', 'SUPER ADMINISTRATOR');

// Example 4: Check if first roles is rank up to 5000
$hasHighRankRole = $this->User_model->contains('profile.0.roles.role_rank', '>', '5000');

```

## 📄 License

This project is licensed under the MIT License.

## 🏷️ Changelog

<details>
<summary>Click to view changelog</summary>

### v1.0.0 (2025-01-01)
- Initial release
- Basic query builder functionality
- Soft delete implementation
- Basic relationship handling
- Security layer implementation

### v1.0.9 (2025-04-04)
- Introduce the lazy(), cursor(), filter(), pluck(), contains(), exists(), doesntExist(), sortBy(), sortByMultiple() method.
- Fixed issue with eager load.
- Improved performanced.

</details>

## 📫 Support

For bugs and feature requests, please use the [GitHub Issues](https://github.com/faizzul95/MY_Model/issues) page.

## 🙏 Acknowledgments

- Inspired by Laravel's Eloquent ORM
- Built on CodeIgniter 3's solid foundation

## 📄 Basic Documentation

#### Collection Methods

| Function        | Description                                                                                                                                       |
|-----------------|---------------------------------------------------------------------------------------------------------------------------------------------------|
| `chunk()`       | Process data in chunks to handle large datasets efficiently. Similar to Laravel's `chunk()`.                                                      |
| `cursor()`      | Returns a generator that lazily iterates over query results one record at a time with chunk size 1000. Similar to Laravel's `cursor()`.           | 
| `lazy()`        | Returns a lazy collection of results, loading items as needed to conserve memory. Similar to Laravel's `lazy()` method.                           | 
| `filter()`      | Filters the collection using a callback function, keeping only items that pass the given truth test. Similar to Laravel's collection `filter()`.  | 
| `pluck()`       | Retrieves all values for a given key from the collection. Similar to Laravel's collection `pluck()` method.                                       | 
| `contains()`    | Determines if the collection contains a given item or if a given key-value pair exists in the collection. Similar to Laravel's collection `contains()` method. |
| `get()`         | Retrieves all data from the database based on the specified criteria.                                                                             |
| `fetch()`       | Retrieves a single record from the database based on the specified criteria.                                                                      |
| `first()`       | Retrieves the first record based on the query.                                                                                                    |
| `last()`        | Retrieves the last record based on the query.                                                                                                     |
| `count()`       | Counts the number of records matching the specified criteria.                                                                                     |
| `find()`        | Finds a record by its primary key (ID).                                                                                                           |
| `toSql()`       | Returns the SQL query string (without eager loading query).                                                                                       |
| `exists()`      | Determines if the collection has at least one item. Returns true if the collection has one or more items. Similar to Laravel's collection `exists()`.    | 
| `doesntExist()` | Determines if the collection is empty. Returns true if the collection has no items. Similar to Laravel's collection `doesntExist()`.                    | 
| `sortBy()`         | Sorts the collection by the given key. Similar to Laravel's collection `sortBy()` method.                                                              | 
| `sortByMultiple()` | Sorts the collection by multiple keys. Allows you to specify an array of keys and their corresponding sort directions. Similar to Laravel's `sortBy()` but with multiple criteria. |

<hr>

#### Query Functions

| Function        | Description                                                                                                                                       |
|-----------------|---------------------------------------------------------------------------------------------------------------------------------------------------|
| `rawQuery()`    | Execute raw SQL queries directly. Useful for complex queries not supported by active record.                                                      |
| `table()`       | Specifies the database table for the query.                                                                                                       |
| `select()`      | Defines the columns to retrieve in a query. Similar to CodeIgniter’s `select()`.                                                                  |
| `where()`       | Adds a basic WHERE clause to the query. Similar to Laravel's `where()`.                                                                           |
| `orWhere()`     | Adds an OR WHERE clause. Similar to Laravel's `orWhere()`.                                                                                        |
| `whereNull()`   | Adds a WHERE clause to check for `NULL` values. Similar to Laravel's `whereNull()`.                                                               |
| `orWhereNull()` | Adds an OR WHERE clause to check for `NULL` values. Similar to Laravel's `orWhereNull()`.                                                         |
| `whereNotNull()`| Adds a WHERE clause to check for non-NULL values. Similar to Laravel's `whereNotNull()`.                                                          |
| `orWhereNotNull()`| Adds an OR WHERE clause to check for non-NULL values. Similar to Laravel's `orWhereNotNull()`.                                                  |
| `whereExists()` | Adds a WHERE EXISTS clause. Similar to Laravel's `whereExists()`.                                                                                 |
| `orWhereExists()`| Adds an OR WHERE EXISTS clause. Similar to Laravel's `orWhereExists()`.                                                                          |
| `whereNotExists()`| Adds a WHERE NOT EXISTS clause. Similar to Laravel's `whereNotExists()`.                                                                        |
| `orWhereNotExists()`| Adds an OR WHERE NOT EXISTS clause. Similar to Laravel's `orWhereNotExists()`.                                                                |
| `whereNot()`    | Adds a WHERE NOT clause for negating conditions. Similar to Laravel's `whereNot()`.                                                               |
| `orWhereNot()`  | Adds an OR WHERE NOT clause for negating conditions. Similar to Laravel's `orWhereNot()`.                                                         |
| `whereTime()`   | Adds a WHERE clause for a time comparison. Similar to Laravel's `whereTime()`.                                                                    |
| `orWhereTime()` | Adds an OR WHERE clause for a time comparison. Similar to Laravel's `orWhereTime()`.                                                              |
| `whereDate()`   | Adds a WHERE clause for a date comparison. Similar to Laravel's `whereDate()`.                                                                    |
| `orWhereDate()` | Adds an OR WHERE clause for a date comparison. Similar to Laravel's `orWhereDate()`.                                                              |
| `whereDay()`    | Adds a WHERE clause for a specific day. Similar to Laravel's `whereDay()`.                                                                        |
| `orWhereDay()`  | Adds an OR WHERE clause for a specific day. Similar to Laravel's `orWhereDay()`.                                                                  |
| `whereYear()`   | Adds a WHERE clause for a specific year. Similar to Laravel's `whereYear()`.                                                                      |
| `orWhereYear()` | Adds an OR WHERE clause for a specific year. Similar to Laravel's `orWhereYear()`.                                                                |
| `whereMonth()`  | Adds a WHERE clause for a specific month. Similar to Laravel's `whereMonth()`.                                                                    |
| `orWhereMonth()`| Adds an OR WHERE clause for a specific month. Similar to Laravel's `orWhereMonth()`.                                                              |
| `whereIn()`     | Adds a WHERE IN clause. Similar to Laravel's `whereIn()`.                                                                                         |
| `orWhereIn()`   | Adds an OR WHERE IN clause. Similar to Laravel's `orWhereIn()`.                                                                                   |
| `whereNotIn()`  | Adds a WHERE NOT IN clause. Similar to Laravel's `whereNotIn()`.                                                                                  |
| `orWhereNotIn()`| Adds an OR WHERE NOT IN clause. Similar to Laravel's `orWhereNotIn()`.                                                                            |
| `whereBetween()`| Adds a WHERE BETWEEN clause. Similar to Laravel's `whereBetween()`.                                                                               |
| `orWhereBetween()`| Adds an OR WHERE BETWEEN clause. Similar to Laravel's `orWhereBetween()`.                                                                       |
| `whereNotBetween()`| Adds a WHERE NOT BETWEEN clause. Similar to Laravel's `whereNotBetween()`.                                                                     |
| `orWhereNotBetween()`| Adds an OR WHERE NOT BETWEEN clause. Similar to Laravel's `orWhereNotBetween()`.                                                             |
| `join()`        | Adds an INNER JOIN to the query. Similar to CodeIgniter’s `join()`.                                                                               |
| `rightJoin()`   | Adds a RIGHT JOIN to the query. Similar to Laravel's `rightJoin()`.                                                                               |
| `leftJoin()`    | Adds a LEFT JOIN to the query. Similar to Laravel's `leftJoin()`.                                                                                 |
| `innerJoin()`   | Adds an INNER JOIN to the query. Same as `join()`.                                                                                                |
| `outerJoin()`   | Adds an OUTER JOIN to the query. Similar to Laravel's `outerJoin()`.                                                                              |
| `limit()`       | Limits the number of records returned. Similar to CodeIgniter's `limit()`.                                                                        |
| `offset()`      | Skips a number of records before starting to return records. Similar to CodeIgniter's `offset()`.                                                 |
| `orderBy()`     | Adds an ORDER BY clause. Similar to Laravel's `orderBy()`.                                                                                        |
| `groupBy()`     | Adds a GROUP BY clause. Similar to Laravel's `groupBy()`.                                                                                         |
| `groupByRaw()`  | Adds a raw GROUP BY clause. Similar to Laravel's `groupByRaw()`.                                                                                  |
| `having()`      | Adds a HAVING clause. Similar to Laravel's `having()`.                                                                                            |
| `havingRaw()`   | Adds a raw HAVING clause. Similar to Laravel's `havingRaw()`.                                                                                     |
| `withTrashed()` | Retrieves both soft deleted and non-deleted records from the database. When using this method, the results include records that have been soft deleted (i.e., those with a `deleted_at` timestamp) alongside active records.                                                                                                                                         |
| `onlyTrashed()` | Retrieves only the records that have been soft deleted (i.e., records with a `deleted_at` timestamp). This method excludes active (non-deleted) records from the query results. |

<hr>

#### Pagination Functions

| Function                 | Description                                                                                                                                      |
|--------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `setPaginateFilterColumn()` | Sets the filter conditions for pagination. If not set, all columns from the main table are queried.                                           |
| `paginate()`             | Custom pagination method that works without the datatable library. Allows paginating results based on the specified criteria.                    |
| `paginate_ajax()`        | Pagination method specifically designed to work with AJAX requests and integrate with datatables.                                                |
| `paginate_select_input()`| Custom method for handling pagination with the select input (e.g., input for changing the number of results per page).                           |

<hr>

#### Relationship Functions (in model only)

| Function      | Description                                                                                                                                      |
|---------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `hasMany()`   | Defines a one-to-many relationship. Similar to Laravel's `hasMany()`.                                                                            |
| `hasOne()`    | Defines a one-to-one relationship. Similar to Laravel's `hasOne()`.                                                                              |

<hr>

#### Eager Load Functions

| Function      | Description                                                       |
|---------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| `with()`      | Eager loads related models to prevent N+1 query performance issues. Similar to Laravel's `with()`.                                            |
| `withCount()` | Adds a count of related models as an additional attribute.                                                                                    |
| `withSum()`   | Calculates the sum of a specific column from related models.                                                                                  |
| `withMax()`   | Retrieves the maximum value of a specific column from related models.                                                                         |
| `withMin()`   | Retrieves the minimum value of a specific column from related models.                                                                         |
| `whereHas()`          | Filters models based on the existence of a related model that meets a specific condition.                                             |
| `orWhereHas()`        | Adds an OR condition to filter models based on the existence of a related model that meets a specific condition.                      |
| `whereDoesntHave()`   | Filters models that do not have any related models matching a specific condition.                                                     |
| `orWhereDoesntHave()` | Adds an OR condition to filter models that do not have any related models matching a specific condition.                              |

<hr>

#### CRUD Functions

| Function           | Description                                                                                                                                      |
|--------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `create()`         | Inserts a single new record in the database based on the provided data (will return the last inserted id).                                       |
| `batchCreate()`    | Inserts a multiple new record in the database based on the provided data in one operation.                                                       |
| `patch()`          | Updates a specific record by its primary key (ID) set at `$primaryKey` property in model.                                                        |
| `patchAll()`       | Updates multiple existing records based on specified conditions in one operation.                                                                |
| `batchPatch()`     | Updates a multiple existing record by using specific column/primarykey (does not required any where condition).                                  |
| `destroy()`        | Deletes a specific record by its primary key (ID) set at the `$primaryKey` property in the model. If soft delete is enabled ($softDelete = true), the record is not permanently removed but flagged as deleted by setting a `deleted_at` or `$_deleted_at_field` property to timestamp. |
| `destroyAll()`     | Deletes multiple records based on specified conditions. If soft delete is enabled ($softDelete = true), the record is not permanently removed but flagged as deleted by setting a `deleted_at` or `$_deleted_at_field` property to timestamp. |
| `forceDestroy()`   | Permanently deletes a specific record by its primary key (ID) set at the `$primaryKey` property in the model, bypassing the soft delete mechanism if it is enabled. This method removes the record entirely from the database.  |
| `insertOrUpdate()` | Determines whether to insert or update a record based on given conditions. Similar to Laravel's `updateOrInsert()`.                              |
| `restore()`        | Restores a soft-deleted record by removing the deleted_at timestamp, making the record active again. This method only applies to records that have been soft deleted (i.e., those with a non-null deleted_at timestamp). If soft deletes are enabled ($softDelete = true), the restore() function allows you to undo the soft delete and recover the record.  |
| `toSqlPatch()`  | Returns the SQL query string for updating data.                                                                                                    |
| `toSqlCreate()` | Returns the SQL query string for inserting single data.                                                                                            |
| `toSqlDestroy()`| Returns the SQL query string for deleting single data.                                                                                             |

<hr>

#### CRUD Validation Functions

| Function                   | Description                                                                                                                                      |
|----------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `skipValidation()`          | Ignores all validation rules for inserts and updates.                                                                                           |
| `setValidationRules()`      | Sets or overrides existing validation rules for the model on the fly.                                                                           |
| `setCustomValidationRules()`| Adds or changes existing validation rules that are already set in the model.                                                                    |
| `setCreateValidationRules()`| Adds or changes existing validation rules for create operation only.                                                                            |
| `setPatchValidationRules()` | Adds or changes existing validation rules for update operation only.                                                                            |

<hr>

#### Security Functions

| Function                   | Description                                                                                                                                      |
|----------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `safeOutput()`             | Escapes output to prevent XSS attacks. All data, including eager loaded and appended data, will be filtered.                                     |
| `safeOutputWithException()`| Same as `safeOutput()`, but allows specific fields to be excluded from escaping.                                                                 |

<hr>

#### Helper Functions

| Function                   | Description                                                                                                                                      |
|----------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------|
| `toArray()`                | Converts the result set to an array format (Default).                                                                                            |
| `toObject()`               | Converts the result set to an object format.                                                                                                     |
| `toJson()`                 | Converts the result set to JSON format.                                                                                                          |
| `showColumnHidden()`       | Displays hidden columns by removing the `$hidden` property temporarily.                                                                          |
| `setColumnHidden()`        | Dynamically sets columns to be hidden, similar to Laravel's `$hidden` model property.                                                            |
| `setAppends()`             | Dynamically appends custom attributes to the result set, similar to Laravel's `$appends` model property.                                         |
