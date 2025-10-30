<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use League\Csv\Reader;

class VtOpeningSeeder extends Seeder
{
    public function run(): void
    {
        // Đường dẫn CSV trong storage/app/
        $csvPath = 'vt_opening.csv';
        if (!Storage::exists($csvPath)) {
            $this->command->warn("⚠️ Không tìm thấy file storage/app/{$csvPath}. Bỏ qua seeding tồn đầu.");
            return;
        }

        $stream = Storage::readStream($csvPath);
        $csv = Reader::createFromStream($stream);
        $csv->setHeaderOffset(0); // dòng đầu là header

        $rows = [];
        foreach ($csv->getRecords() as $r) {
            // Chuẩn hóa key
            $rows[] = [
                'ma_vt'        => trim((string)($r['ma_vt'] ?? $r['Mã VT'] ?? '')),
                'ten_vt'       => trim((string)($r['ten_vt'] ?? $r['Tên VT'] ?? '')),
                'danh_muc_vt'  => trim((string)($r['danh_muc_vt'] ?? $r['Danh mục VT'] ?? '')),
                'nhom_vt'      => trim((string)($r['nhom_vt'] ?? $r['Nhóm'] ?? '')),
                'don_vi_tinh'  => trim((string)($r['don_vi_tinh'] ?? $r['ĐVT'] ?? '')),
                'loai'         => strtoupper(trim((string)($r['loai'] ?? $r['Loại'] ?? 'CONSUMABLE'))), // ASSET|CONSUMABLE
                'so_luong'     => (int)($r['ton_dau'] ?? $r['Tồn đầu'] ?? 0),
                'don_gia'      => isset($r['don_gia']) ? (float)$r['don_gia'] : (isset($r['Đơn giá']) ? (float)$r['Đơn giá'] : null),
                'ngay_ct'      => trim((string)($r['ngay_nhap'] ?? $r['Ngày nhập'] ?? '2025-09-08')),
                'ghi_chu'      => trim((string)($r['ghi_chu'] ?? $r['Ghi chú'] ?? '')),
            ];
        }

        if (empty($rows)) {
            $this->command->warn("⚠️ File CSV rỗng. Bỏ qua seeding tồn đầu.");
            return;
        }

        // Uỷ quyền cho service đã viết ở File #8
        /** @var \App\Services\VT\VtLedgerService $svc */
        $svc = app(\App\Services\VT\VtLedgerService::class);
        $result = $svc->importOpening($rows);

        $this->command->info("✅ Import OPENING: created={$result['created']}, skipped={$result['skipped']}");
    }
}
