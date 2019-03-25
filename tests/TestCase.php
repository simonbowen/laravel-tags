<?php

namespace Spatie\Tags\Test;

use DB;
use Dotenv\Dotenv;
use Spatie\Tags\TagsServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    public function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase($this->app);
    }

    protected function getPackageProviders($app)
    {
        return [
            TagsServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetUp($app)
    {
        //If we're not in travis, load our local .env file
        if (empty(getenv('CI'))) {
            $dotenv = Dotenv::create(realpath(__DIR__.'/..'));
            $dotenv->load();
        }

        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'database' => env('DB_DATABASE', 'laravel_tags'),
            'username' => env('DB_USERNAME', 'username'),
            'password' => env('DB_PASSWORD', 'password'),
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => '',
            'strict' => false,
        ]);
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        $this->dropAllTables($app);

        include_once __DIR__.'/../database/migrations/create_tag_tables.php.stub';

        (new \CreateTagTables())->up();

        $app['db']->connection()->getSchemaBuilder()->create('test_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });

        $app['db']->connection()->getSchemaBuilder()->create('test_another_models', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
    }

    protected function dropAllTables($app)
    {
        $driver = $app['config']['database']['default'];
        ($driver == 'pgsql') ? $this->dropAllPostgresTables() : $this->dropAllMySQLTables();
    }

    protected function dropAllPostgresTables()
    {
        $query = "SELECT * FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY schemaname, tablename;";
        $rows = collect(DB::select($query));

        if($rows->isEmpty()) {
            return;
        }

        $rows
            ->map(function ($row) {
                return $row->tablename;
            })->each(function (string $tableName) {
                DB::statement("DROP TABLE {$tableName}");
            });
    }

    protected function dropAllMySQLTables()
    {
        $rows = collect(DB::select('SHOW TABLES'));

        if ($rows->isEmpty()) {
            return;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');

        $rows
            ->map(function ($row) {
                return $row->Tables_in_laravel_tags;
            })
            ->each(function (string $tableName) {
                DB::statement("DROP TABLE {$tableName}");
            });

        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
}
