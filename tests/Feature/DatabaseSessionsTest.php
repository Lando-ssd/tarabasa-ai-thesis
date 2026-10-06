<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Render keeps sessions in the database (its disk is wiped on every sleep). The first run against TiDB on
 * 2026-10-06 showed the table had never been created, so every page returned a 500. These tests pin that
 * the table exists after the migrations and that a page really works with the database driver.
 */
class DatabaseSessionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sessions_table_exists_with_the_columns_laravel_writes(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
        $this->assertTrue(Schema::hasColumns('sessions', ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity']));
    }

    public function test_a_page_works_and_a_session_row_is_written_with_the_database_driver(): void
    {
        config(['session.driver' => 'database']);

        $this->get('/login')->assertOk();

        $this->assertGreaterThan(0, DB::table('sessions')->count(), 'The page did not write a session row.');
    }
}
