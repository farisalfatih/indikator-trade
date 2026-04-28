-- ── Migration: upgrade summary_performance ke single-row design ──────────────
-- Aman dijalankan berkali-kali (idempotent)

DO $$
BEGIN
    -- Cek apakah kolom id masih bertipe SERIAL/BIGSERIAL (sequence-owned integer)
    -- Jika iya, migrate ke fixed-id design
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'summary_performance_btcusdt'
          AND column_name = 'id'
          AND column_default LIKE 'nextval%'
    ) THEN
        -- 1. Hapus sequence dependency
        ALTER TABLE summary_performance_btcusdt ALTER COLUMN id DROP DEFAULT;

        -- 2. Hapus semua baris kecuali yang terbaru (calculated_at MAX)
        DELETE FROM summary_performance_btcusdt
        WHERE id NOT IN (
            SELECT id FROM summary_performance_btcusdt
            ORDER BY calculated_at DESC
            LIMIT 1
        );

        -- 3. Set id baris yang tersisa ke 1
        UPDATE summary_performance_btcusdt SET id = 1;

        -- 4. Tambah constraint
        ALTER TABLE summary_performance_btcusdt
            ADD CONSTRAINT chk_single_row CHECK (id = 1);

        -- 5. Set default supaya INSERT tanpa id juga ke 1
        ALTER TABLE summary_performance_btcusdt
            ALTER COLUMN id SET DEFAULT 1;

        RAISE NOTICE 'summary_performance_btcusdt migrated to single-row design.';
    ELSE
        RAISE NOTICE 'summary_performance_btcusdt already migrated, skipping.';
    END IF;
END $$;
