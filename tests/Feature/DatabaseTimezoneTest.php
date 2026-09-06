<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * AppServiceProvider::boot() sets the MySQL session's time_zone on every new connection
 * to match PHP's own current app.timezone offset. Without it, a column relying purely on
 * MySQL's own CURRENT_TIMESTAMP/useCurrent() default (no PHP-side $timestamps writing it)
 * silently used the MySQL SERVER's timezone instead — audit_logs.created_at being the
 * reported case, since AuditLog has $timestamps=false.
 */
class DatabaseTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_mysql_session_timezone_matches_the_apps_current_offset(): void
    {
        $sessionTimezone = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;

        $this->assertSame(now()->format('P'), $sessionTimezone);
    }

    public function test_a_db_default_timestamp_column_agrees_with_the_apps_clock(): void
    {
        $log = AuditLog::create([
            'action' => 'test.timezone_check',
            'entity_type' => 'test',
            'metadata' => [],
        ]);

        $this->assertLessThanOrEqual(5, now()->diffInSeconds($log->fresh()->created_at, true));
    }
}
