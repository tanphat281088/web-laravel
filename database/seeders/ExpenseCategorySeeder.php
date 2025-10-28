<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // ===================== PARENTS =====================
            // Bổ sung CHA CCDC (line=8) theo yêu cầu, giữ nguyên các CHA cũ
            $parents = [
                ['code' => 'COGS',     'name' => 'Giá vốn hàng bán',       'statement_line' => 2,  'sort_order' => 1],
                ['code' => 'BH',       'name' => 'Chi phí bán hàng',       'statement_line' => 6,  'sort_order' => 2],
                ['code' => 'QLDN',     'name' => 'Chi phí quản lý DN',     'statement_line' => 7,  'sort_order' => 3],
                ['code' => 'TC',       'name' => 'Chi phí tài chính',      'statement_line' => 5,  'sort_order' => 4],
                ['code' => 'CHI_KHAC', 'name' => 'Chi phí khác',           'statement_line' => 10, 'sort_order' => 5],
                ['code' => 'CCDC',     'name' => 'Chi phí đầu tư CCDC',    'statement_line' => 8,  'sort_order' => 6], // MỚI
            ];

            $parentIds = [];
            foreach ($parents as $p) {
                $row = ExpenseCategory::query()->updateOrCreate(
                    ['code' => $p['code']],
                    [
                        'name'           => $p['name'],
                        'parent_id'      => null,
                        'statement_line' => $p['statement_line'],
                        'sort_order'     => $p['sort_order'],
                        'is_active'      => true,
                    ]
                );
                $parentIds[$p['code']] = $row->id;
            }

            // ===================== CHILDREN =====================
            // Bổ sung thêm các CON còn thiếu: HOÀN_HÀNG, THƯỞNG_DOANH_SỐ, PLNV, CCDC_MUA/CCDC_KHAU
            $children = [
                // ------- COGS -------
                ['code' => 'HOA',       'name' => 'Hoa tươi',                        'parent' => 'COGS'],
                ['code' => 'PK',        'name' => 'Phụ kiện & vật tư',               'parent' => 'COGS'],
                ['code' => 'INAN',      'name' => 'In ấn (tem/nhãn/banner)',         'parent' => 'COGS'],
                ['code' => 'SHIP_COGS', 'name' => 'Phí ship phục vụ đơn',            'parent' => 'COGS'],
                ['code' => 'CAY',       'name' => 'Cây/lan hồ điệp',                 'parent' => 'COGS'],
                ['code' => 'BKE',       'name' => 'Bánh kèm/Phụ trợ',                'parent' => 'COGS'],
                ['code' => 'GV_KHAC',   'name' => 'Giá vốn khác',                     'parent' => 'COGS'],

                // ------- BH (Chi phí bán hàng) -------
                ['code' => 'MKT',        'name' => 'Marketing/Quảng cáo',            'parent' => 'BH'],
                ['code' => 'HH',         'name' => 'Hoa hồng bán hàng',              'parent' => 'BH'],
                ['code' => 'KMAI',       'name' => 'Khuyến mãi/Chiết khấu',          'parent' => 'BH'],
                ['code' => 'SHIP_BH',    'name' => 'Phí ship bán hàng',              'parent' => 'BH'],
                ['code' => 'HOAN_HANG',  'name' => 'Chi phí hoàn hàng',              'parent' => 'BH'],       // MỚI
                ['code' => 'THUONG_DS',  'name' => 'Chi phí thưởng đạt doanh số',    'parent' => 'BH'],       // MỚI
                ['code' => 'BH_KHAC',    'name' => 'Chi bán hàng khác',              'parent' => 'BH'],

                // ------- QLDN (Chi phí quản lý DN) -------
                ['code' => 'LNV',       'name' => 'Lương nhân viên',                 'parent' => 'QLDN'],
                ['code' => 'PLNV',      'name' => 'Chi phí trong nhân viên',         'parent' => 'QLDN'],     // MỚI
                ['code' => 'BHXH',      'name' => 'BHXH',                             'parent' => 'QLDN'],
                ['code' => 'VPP',       'name' => 'VPP, thay mực, máy in…',          'parent' => 'QLDN'],
                ['code' => 'MB',        'name' => 'Thuê mặt bằng',                    'parent' => 'QLDN'],
                ['code' => 'DNVT',      'name' => 'Điện, nước, internet…',           'parent' => 'QLDN'],
                ['code' => 'SC',        'name' => 'Sửa chữa',                         'parent' => 'QLDN'],
                ['code' => 'XX',        'name' => 'Xăng xe, bảo dưỡng xe…',          'parent' => 'QLDN'],
                ['code' => 'NH',        'name' => 'Phí ngân hàng',                    'parent' => 'QLDN'],
                ['code' => 'DVN',       'name' => 'Dịch vụ mua ngoài (PM, TK số, báo cáo thuế)', 'parent' => 'QLDN'],
                ['code' => 'THUE',      'name' => 'Thuế/Hóa đơn',                     'parent' => 'QLDN'],
                ['code' => 'QL_KHAC',   'name' => 'Chi QLDN khác',                    'parent' => 'QLDN'],

                // ------- TC (Chi phí tài chính) -------
                ['code' => 'TC_PHI',    'name' => 'Chi phí tài chính/Phí',           'parent' => 'TC'],

                // ------- CHI_KHAC -------
                ['code' => 'CK_KHAC',   'name' => 'Chi phí khác',                     'parent' => 'CHI_KHAC'],

                // ------- CCDC (Chi phí đầu tư CCDC) -------
                ['code' => 'CCDC_MUA',  'name' => 'Mua CCDC',                         'parent' => 'CCDC'],     // MỚI
                ['code' => 'CCDC_KHAU', 'name' => 'Khấu hao/Phân bổ CCDC',           'parent' => 'CCDC'],     // MỚI
            ];

            $order = 1;
            foreach ($children as $c) {
                ExpenseCategory::query()->updateOrCreate(
                    ['code' => $c['code']],
                    [
                        'name'           => $c['name'],
                        'parent_id'      => $parentIds[$c['parent']] ?? null,
                        // Giữ logic: child kế thừa statement_line của CHA
                        'statement_line' => ExpenseCategory::query()
                            ->where('id', $parentIds[$c['parent']] ?? 0)
                            ->value('statement_line'),
                        'sort_order'     => $order++,
                        'is_active'      => true,
                    ]
                );
            }
        });
    }
}
