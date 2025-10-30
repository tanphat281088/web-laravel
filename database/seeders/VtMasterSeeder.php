<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VtMasterSeeder extends Seeder
{
    public function run(): void
    {
        $now  = now();
        $conf = config('vt_master');

        $cats   = array_values(array_filter((array)($conf['categories'] ?? []), fn($s) => trim((string)$s) !== ''));
        $groups = array_values(array_filter((array)($conf['groups']     ?? []), fn($s) => trim((string)$s) !== ''));
        $units  = array_values(array_filter((array)($conf['units']      ?? []), fn($s) => trim((string)$s) !== ''));

        DB::transaction(function () use ($now, $cats, $groups, $units) {
            $this->syncTable('vt_categories', $cats,   $now);
            $this->syncTable('vt_groups',     $groups, $now);
            $this->syncTable('vt_units',      $units,  $now);
        });
    }

    /**
     * Đồng bộ bảng master theo danh sách tên từ config:
     * - XÓA các bản ghi không còn trong config.
     * - UPSERT các bản ghi trong config (thêm mới nếu thiếu; nếu đã có thì cập nhật active=1, updated_at).
     * - KHÔNG thay đổi created_at khi cập nhật.
     */
    private function syncTable(string $table, array $names, $now): void
    {
        if (!Schema::hasTable($table)) return;

        // Chuẩn hoá & khử trùng danh sách
        $norm = [];
        foreach ($names as $i => $name) {
            $name = $this->normalizeName($name);
            if ($name === '' || isset($norm[$name])) continue;
            $norm[$name] = $i;
        }
        $keepNames = array_keys($norm);

        // 1) XÓA mọi mục không còn trong config
        if (count($keepNames) > 0) {
            DB::table($table)->whereNotIn('name', $keepNames)->delete();
        } else {
            DB::table($table)->delete();
        }

        // 2) Chuẩn bị rows để upsert
        $rows = [];
        foreach ($keepNames as $idx => $name) {
            $rows[] = [
                'code'       => $this->codeFromName($name, $idx),
                'name'       => $name,
                'active'     => 1,
                'created_at' => $now, // chỉ dùng khi INSERT
                'updated_at' => $now,
            ];
        }

        // 3) UPSERT theo 'name'
        // - Cột cập nhật khi trùng: active, updated_at, code (KHÔNG cập nhật created_at)
        // - Insert mới sẽ ghi cả created_at/updated_at
        if (!empty($rows)) {
            DB::table($table)->upsert(
                $rows,
                ['name'],                 // unique-by
                ['active','updated_at','code'] // columns to update
            );
        }
    }

    private function codeFromName(string $name, int $i): string
    {
        // Tạo code gọn, latin-only, UPPER, dài <= 20 + _NNN
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name);
        if ($ascii === false) $ascii = $name;
        $base  = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_', $ascii));
        $base  = trim($base, '_');
        if ($base === '') $base = 'ITEM';
        return substr($base, 0, 20) . '_' . str_pad((string)($i + 1), 3, '0', STR_PAD_LEFT);
    }

    /** Chuẩn hoá chuỗi để tránh biến thể unicode (bó ≠ bó) gây đụng UNIQUE */
    private function normalizeName(string $s): string
    {
        $s = trim($s);
        if ($s === '') return '';

        // Về dạng NFC nếu ext intl có sẵn (tránh khác biệt ký tự tổ hợp)
        if (class_exists(\Normalizer::class)) {
            $s = \Normalizer::normalize($s, \Normalizer::FORM_C);
        }

        // Gom khoảng trắng
        $s = preg_replace('/\s+/u', ' ', $s);

        return $s ?? '';
    }
}
