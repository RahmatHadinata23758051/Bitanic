<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Enforce append-only integrity on audit_logs at the database engine level
     * via triggers, preventing bypass through direct SQL or Query Builder.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION trg_audit_logs_append_only()
                RETURNS TRIGGER AS $$
                BEGIN
                    IF current_setting('app.allow_audit_mutation', true) = 'on'
                       AND EXISTS (SELECT 1 FROM pg_user WHERE usename = CURRENT_USER AND usesuper = true) THEN
                        RETURN OLD;
                    END IF;
                    RAISE EXCEPTION 'audit_logs is append-only at the database engine level. UPDATE and DELETE operations are prohibited.';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS trg_prevent_audit_logs_mutation ON audit_logs;
                CREATE TRIGGER trg_prevent_audit_logs_mutation
                BEFORE UPDATE OR DELETE ON audit_logs
                FOR EACH ROW
                EXECUTE FUNCTION trg_audit_logs_append_only();
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER IF NOT EXISTS trg_audit_logs_prevent_update
                BEFORE UPDATE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs is append-only at the database engine level. UPDATE prohibited.');
                END;

                CREATE TRIGGER IF NOT EXISTS trg_audit_logs_prevent_delete
                BEFORE DELETE ON audit_logs
                BEGIN
                    SELECT RAISE(ABORT, 'audit_logs is append-only at the database engine level. DELETE prohibited.');
                END;
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_prevent_audit_logs_mutation ON audit_logs;
                DROP FUNCTION IF EXISTS trg_audit_logs_append_only();
            SQL);
        } elseif ($driver === 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_audit_logs_prevent_update;
                DROP TRIGGER IF EXISTS trg_audit_logs_prevent_delete;
            SQL);
        }
    }
};
