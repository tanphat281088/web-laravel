<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class KQKDExcelExport implements FromArray, WithEvents, WithTitle
{
    private array $summary;
    private array $series;
    private array $byCatMap; // [2=>[],5=>[],6=>[],7=>[],8=>[],10=>[]]
    private ?string $from;
    private ?string $to;

    // Mở rộng tới 14 cột (A..N) để bám sát layout PDF (01..13)
    private array $cols = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N'];

    public function __construct(array $summary, array $series, array $byCatMap, ?string $from, ?string $to)
    {
        $this->summary = $summary;
        $this->series  = $series;
        $this->byCatMap= $byCatMap;
        $this->from    = $from;
        $this->to      = $to;
    }

    public function title(): string
    {
        return 'Báo cáo KQKD';
    }

    public function array(): array
    {
        // Ghi toàn bộ bằng AfterSheet để kiểm soát merge/style
        return [[]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function(AfterSheet $event) {

                $sheet = $event->sheet->getDelegate();

                $S  = $this->summary ?? [];
                $by = $this->byCatMap ?? [];
                $series = $this->series ?? [];

                $fmt  = fn($v)=>number_format((int)$v);
                $base = max(0,(int)($S['01_doanh_thu_ban_hang'] ?? 0));
                $pct  = fn($v)=> $base ? (rtrim(rtrim(number_format(($v/$base)*100,1,'.',''), '0'),'.').'%') : '0%';

                // Màu thương hiệu
                $brandBg='FCE7EF'; $brandAccent='C83D5D'; $brandText='2E3A63'; $borderColor='D7CAD1';

                $row = 1;

                // ===== HEADER pastel =====
                $sheet->mergeCells("A{$row}:N".($row+2));
                $sheet->setCellValue("A{$row}",
                    "CÔNG TY CỔ PHẦN TRANG TRÍ PHÁT HOÀNG GIA\n".
                    "MST: 0319141372   Đ/c: Số 100 Nguyễn Minh Hoàng, Phường Bảy Hiền, TP. Hồ Chí Minh\n".
                    "Kỳ dữ liệu: ".($this->from ?: '...')." → ".($this->to ?: '...')."    Ngày xuất: ".date('d/m/Y H:i')
                );
                $sheet->getStyle("A{$row}:N".($row+2))->applyFromArray([
                    'alignment'=>[
                        'wrapText'=>true,
                        'vertical'=>Alignment::VERTICAL_CENTER,
                        'horizontal'=>Alignment::HORIZONTAL_LEFT
                    ],
                    'font'=>['bold'=>true,'size'=>11,'color'=>['rgb'=>$brandText]],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>$brandBg]],
                    'borders'=>['outline'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$brandAccent]]]
                ]);
                $row += 4;

                // ===== TIÊU ĐỀ =====
                $sheet->mergeCells("A{$row}:N{$row}");
                $sheet->setCellValue("A{$row}", "BÁO CÁO KẾT QUẢ KINH DOANH");
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true,'size'=>14,'color'=>['rgb'=>$brandAccent]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row += 2;

                // ===== TỔNG HỢP & CHI TIẾT =====
                // Header
                $sheet->mergeCells("A{$row}:C{$row}"); $sheet->setCellValue("A{$row}", "Chỉ tiêu");
                $sheet->mergeCells("D{$row}:G{$row}"); $sheet->setCellValue("D{$row}", "Diễn giải");
                $sheet->mergeCells("H{$row}:M{$row}"); $sheet->setCellValue("H{$row}", "Số tiền (VNĐ)");
                $sheet->setCellValue("N{$row}", "Tỷ lệ (%)");

                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$borderColor]]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;

                $borderAll = function($r1,$r2) use ($sheet,$borderColor){
                    $sheet->getStyle("A{$r1}:N{$r2}")->applyFromArray([
                        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$borderColor]]]
                    ]);
                };

                $writeLine = function(string $label, string $desc, $value) use (&$row,$sheet,$fmt,$pct){
                    $sheet->mergeCells("A{$row}:C{$row}");
                    $sheet->mergeCells("D{$row}:G{$row}");
                    $sheet->mergeCells("H{$row}:M{$row}");
                    $sheet->setCellValue("A{$row}", $label);
                    $sheet->setCellValue("D{$row}", $desc);
                    $sheet->setCellValue("H{$row}", $fmt($value));
                    $sheet->setCellValue("N{$row}", $label === '01. Doanh thu bán hàng và cung cấp dịch vụ' ? '100%' : $pct((int)$value));
                    $row++;
                };

                // 01
                $writeLine('01. Doanh thu bán hàng và cung cấp dịch vụ', 'Tổng doanh thu ghi nhận theo phiếu thu trong kỳ.', $S['01_doanh_thu_ban_hang'] ?? 0);

                // 02 + chi tiết
                $start02 = $row;
                $writeLine('02. Giá vốn hàng bán', 'Tổng chi phí giá vốn (dưới đây là các mục chi tiết).', $S['02_gia_von_hang_ban'] ?? 0);
                // Subheader
                $sheet->mergeCells("D{$row}:G{$row}"); $sheet->setCellValue("D{$row}", '- Mục chi tiết');
                $sheet->setCellValue("H{$row}", 'Số tiền (VNĐ)'); $sheet->setCellValue("M{$row}", ''); $sheet->setCellValue("N{$row}", 'Tỷ lệ (%)');
                $sheet->getStyle("D{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;
                foreach (($by[2] ?? []) as $it) {
                    $label = trim($it['category_name'] ?? 'Chưa phân loại');
                    $val   = (int)($it['total'] ?? 0);
                    $sheet->mergeCells("D{$row}:G{$row}");
                    $sheet->setCellValue("D{$row}", "- {$label}");
                    $sheet->mergeCells("H{$row}:M{$row}");
                    $sheet->setCellValue("H{$row}", $fmt($val));
                    $sheet->setCellValue("N{$row}", $pct($val));
                    $row++;
                }
                $borderAll($start02, $row-1);

                // 03
                $writeLine('03. Lợi nhuận gộp (01 − 02)', 'Chênh lệch giữa doanh thu và giá vốn.', $S['03_loi_nhuan_gop'] ?? 0);

                // 04
                $writeLine('04. Doanh thu hoạt động tài chính', 'Doanh thu tài chính phát sinh (phiếu thu loại “tài chính”).', $S['04_doanh_thu_hd_tai_chinh'] ?? 0);

                // 05 + chi tiết
                $start05 = $row;
                $writeLine('05. Chi phí tài chính', 'Các khoản chi phí tài chính trong kỳ.', $S['05_chi_phi_tai_chinh'] ?? 0);
                $sheet->mergeCells("D{$row}:G{$row}"); $sheet->setCellValue("D{$row}", '- Mục chi tiết');
                $sheet->setCellValue("H{$row}", 'Số tiền (VNĐ)'); $sheet->setCellValue("M{$row}", ''); $sheet->setCellValue("N{$row}", 'Tỷ lệ (%)');
                $sheet->getStyle("D{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;
                foreach (($by[5] ?? []) as $it) {
                    $label = trim($it['category_name'] ?? 'Chưa phân loại');
                    $val   = (int)($it['total'] ?? 0);
                    $sheet->mergeCells("D{$row}:G{$row}");
                    $sheet->setCellValue("D{$row}", "- {$label}");
                    $sheet->mergeCells("H{$row}:M{$row}");
                    $sheet->setCellValue("H{$row}", $fmt($val));
                    $sheet->setCellValue("N{$row}", $pct($val));
                    $row++;
                }
                $borderAll($start05, $row-1);

                // 06 + chi tiết
                $start06 = $row;
                $writeLine('06. Chi phí bán hàng', 'Các khoản chi phục vụ bán hàng (marketing, vận chuyển, khuyến mãi,…).', $S['06_chi_phi_ban_hang'] ?? 0);
                $sheet->mergeCells("D{$row}:G{$row}"); $sheet->setCellValue("D{$row}", '- Mục chi tiết');
                $sheet->setCellValue("H{$row}", 'Số tiền (VNĐ)'); $sheet->setCellValue("M{$row}", ''); $sheet->setCellValue("N{$row}", 'Tỷ lệ (%)');
                $sheet->getStyle("D{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;
                foreach (($by[6] ?? []) as $it) {
                    $label = trim($it['category_name'] ?? 'Chưa phân loại');
                    $val   = (int)($it['total'] ?? 0);
                    $sheet->mergeCells("D{$row}:G{$row}");
                    $sheet->setCellValue("D{$row}", "- {$label}");
                    $sheet->mergeCells("H{$row}:M{$row}");
                    $sheet->setCellValue("H{$row}", $fmt($val));
                    $sheet->setCellValue("N{$row}", $pct($val));
                    $row++;
                }
                $borderAll($start06, $row-1);

                // 07 + chi tiết
                $start07 = $row;
                $writeLine('07. Chi phí quản lý doanh nghiệp', 'Các khoản chi phục vụ quản trị vận hành (lương, BHXH, điện nước, VPP,…).', $S['07_chi_phi_quan_ly_dn'] ?? 0);
                $sheet->mergeCells("D{$row}:G{$row}"); $sheet->setCellValue("D{$row}", '- Mục chi tiết');
                $sheet->setCellValue("H{$row}", 'Số tiền (VNĐ)'); $sheet->setCellValue("M{$row}", ''); $sheet->setCellValue("N{$row}", 'Tỷ lệ (%)');
                $sheet->getStyle("D{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;
                foreach (($by[7] ?? []) as $it) {
                    $label = trim($it['category_name'] ?? 'Chưa phân loại');
                    $val   = (int)($it['total'] ?? 0);
                    $sheet->mergeCells("D{$row}:G{$row}");
                    $sheet->setCellValue("D{$row}", "- {$label}");
                    $sheet->mergeCells("H{$row}:M{$row}");
                    $sheet->setCellValue("H{$row}", $fmt($val));
                    $sheet->setCellValue("N{$row}", $pct($val));
                    $row++;
                }
                $borderAll($start07, $row-1);

                // 08 + chi tiết (MỚI)
                $start08 = $row;
                $writeLine('08. Chi phí đầu tư CCDC', 'Các khoản chi đầu tư, mua sắm và phân bổ CCDC.', $S['08_chi_phi_dau_tu_ccdc'] ?? 0);
                $sheet->mergeCells("D{$row}:G{$row}"); $sheet->setCellValue("D{$row}", '- Mục chi tiết');
                $sheet->setCellValue("H{$row}", 'Số tiền (VNĐ)'); $sheet->setCellValue("M{$row}", ''); $sheet->setCellValue("N{$row}", 'Tỷ lệ (%)');
                $sheet->getStyle("D{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;
                foreach (($by[8] ?? []) as $it) {
                    $label = trim($it['category_name'] ?? 'Chưa phân loại');
                    $val   = (int)($it['total'] ?? 0);
                    $sheet->mergeCells("D{$row}:G{$row}");
                    $sheet->setCellValue("D{$row}", "- {$label}");
                    $sheet->mergeCells("H{$row}:M{$row}");
                    $sheet->setCellValue("H{$row}", $fmt($val));
                    $sheet->setCellValue("N{$row}", $pct($val));
                    $row++;
                }
                $borderAll($start08, $row-1);

                // 09
                $writeLine('09. Lợi nhuận thuần từ HĐKD', '03 + 04 − 05 − 06 − 07 − 08.', $S['09_loi_nhuan_thuan_hd_kd'] ?? 0);

                // 10 + chi tiết
                $start10 = $row;
                $writeLine('10. Chi phí khác', 'Các khoản chi ngoài hoạt động thường xuyên.', $S['10_chi_phi_khac'] ?? 0);
                $sheet->mergeCells("D{$row}:G{$row}"); $sheet->setCellValue("D{$row}", '- Mục chi tiết');
                $sheet->setCellValue("H{$row}", 'Số tiền (VNĐ)'); $sheet->setCellValue("M{$row}", ''); $sheet->setCellValue("N{$row}", 'Tỷ lệ (%)');
                $sheet->getStyle("D{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;
                foreach (($by[10] ?? []) as $it) {
                    $label = trim($it['category_name'] ?? 'Chưa phân loại');
                    $val   = (int)($it['total'] ?? 0);
                    $sheet->mergeCells("D{$row}:G{$row}");
                    $sheet->setCellValue("D{$row}", "- {$label}");
                    $sheet->mergeCells("H{$row}:M{$row}");
                    $sheet->setCellValue("H{$row}", $fmt($val));
                    $sheet->setCellValue("N{$row}", $pct($val));
                    $row++;
                }
                $borderAll($start10, $row-1);

                // 11
                $writeLine('11. Thu nhập khác', 'Thu nhập ngoài hoạt động kinh doanh chính.', $S['11_thu_nhap_khac'] ?? 0);

                // 12 (12 = 10 − 11)
                $writeLine('12. Lợi nhuận khác (12 = 10 − 11)', 'Chi phí khác − Thu nhập khác.', $S['12_loi_nhuan_khac'] ?? 0);

                // 13
                $writeLine('13. Lợi nhuận trước thuế (13 = 09 + 12)', 'Lợi nhuận thuần HĐKD + Lợi nhuận khác.', $S['13_loi_nhuan_truoc_thue'] ?? 0);

                // ===== CHI TIẾT THEO THÁNG =====
                $row += 2;
                $sheet->mergeCells("A{$row}:N{$row}");
                $sheet->setCellValue("A{$row}","Chi tiết theo tháng");
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true,'color'=>['rgb'=>$brandText]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_LEFT]
                ]);
                $row++;

                $heads = [
                    'Kỳ (YYYY-MM)','01 Doanh thu','02 Giá vốn','03 Lợi nhuận gộp',
                    '04 DT tài chính','05 CP tài chính','06 CP bán hàng','07 CP QLDN',
                    '08 CP CCDC','09 LN thuần','10 CP khác','11 TN khác','12 LN khác','13 LNTT'
                ];
                foreach ($heads as $i=>$h) {
                    $sheet->setCellValue($this->cols[$i].$row, $h);
                }
                $sheet->getStyle("A{$row}:N{$row}")->applyFromArray([
                    'font'=>['bold'=>true],
                    'fill'=>['fillType'=>Fill::FILL_SOLID,'startColor'=>['rgb'=>'FAF7F8']],
                    'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$borderColor]]],
                    'alignment'=>['horizontal'=>Alignment::HORIZONTAL_CENTER]
                ]);
                $row++;

                $startData = $row;
                foreach ($series as $r) {
                    $vals = [
                        $r['ym'] ?? '',
                        $fmt($r['01'] ?? 0),
                        $fmt($r['02'] ?? 0),
                        $fmt($r['03'] ?? 0),
                        $fmt($r['04'] ?? 0),
                        $fmt($r['05'] ?? 0),
                        $fmt($r['06'] ?? 0),
                        $fmt($r['07'] ?? 0),
                        $fmt($r['08'] ?? 0),
                        $fmt($r['09'] ?? 0),
                        $fmt($r['10'] ?? 0),
                        $fmt($r['11'] ?? 0),
                        $fmt($r['12'] ?? 0),
                        $fmt($r['13'] ?? 0),
                    ];
                    foreach ($vals as $i=>$val) {
                        $sheet->setCellValue($this->cols[$i].$row, $val);
                    }
                    $row++;
                }
                if ($row > $startData) {
                    $sheet->getStyle("A{$startData}:N".($row-1))->applyFromArray([
                        'borders'=>['allBorders'=>['borderStyle'=>Border::BORDER_THIN,'color'=>['rgb'=>$borderColor]]]
                    ]);
                }

                // Rộng cột & canh phải số
                $sheet->getColumnDimension('A')->setWidth(16);
                $sheet->getColumnDimension('B')->setWidth(18);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(30);
                $sheet->getColumnDimension('E')->setWidth(24);
                $sheet->getColumnDimension('F')->setWidth(18);
                $sheet->getColumnDimension('G')->setWidth(18);
                $sheet->getColumnDimension('H')->setWidth(16);
                $sheet->getColumnDimension('I')->setWidth(14);
                $sheet->getColumnDimension('J')->setWidth(14);
                $sheet->getColumnDimension('K')->setWidth(14);
                $sheet->getColumnDimension('L')->setWidth(14);
                $sheet->getColumnDimension('M')->setWidth(14);
                $sheet->getColumnDimension('N')->setWidth(12);

                // Canh phải số tiền và %
                $sheet->getStyle("H1:M{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // tiền
                $sheet->getStyle("N1:N{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // %
            }
        ];
    }
}
