<?php

namespace App\Services\VT;

use App\Models\VtItem;
use App\Models\VtLedger;
use App\Models\VtStock;
use App\Models\VtReceipt;
use App\Models\VtReceiptItem;
use App\Models\VtIssue;
use App\Models\VtIssueItem;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VtLedgerService
{
    public function __construct(
        protected VtStockService $stockService
    ) {}

    // ------------------------------
    // Helpers
    // ------------------------------

    /**
     * Sinh số chứng từ dạng PREFIX-YYYYMMDD-HHMMSS-XXXX (UPPER).
     * Có kiểm tra đụng số trong bảng tương ứng (PNVT -> vt_receipts, PXVT -> vt_issues), retry tối đa 5 lần.
     */
    public function genSoCt(string $prefix = 'VT'): string
    {
        $prefix = strtoupper(trim($prefix));
        $attempts = 0;
        do {
            $stamp = Carbon::now()->format('YmdHis');
            $code  = sprintf('%s-%s-%04d', $prefix, $stamp, random_int(0, 9999));
            $exists = false;

            if ($prefix === 'PNVT') {
                $exists = VtReceipt::where('so_ct', $code)->exists();
            } elseif ($prefix === 'PXVT') {
                $exists = VtIssue::where('so_ct', $code)->exists();
            } else {
                // dự phòng: kiểm tra cả hai
                $exists = VtReceipt::where('so_ct', $code)->exists()
                       || VtIssue::where('so_ct', $code)->exists();
            }

            $attempts++;
        } while ($exists && $attempts < 5);

        if ($exists) {
            // fallback cực hiếm
            $code = $prefix . '-' . Carbon::now()->format('YmdHis') . '-' . strtoupper(substr(uniqid('', true), -4));
        }

        return strtoupper($code);
    }

    protected function currentUserId(): ?int
    {
        return Auth::id();
    }

    // ------------------------------
    // OPENING (import tồn đầu)
    // ------------------------------
    public function importOpening(array $rows): array
    {
        $userId = $this->currentUserId();
        $created = 0; $skipped = 0;

        DB::transaction(function () use (&$rows, $userId, &$created, &$skipped) {
            foreach ($rows as $r) {
                $sl = (int) ($r['so_luong'] ?? 0);
                if ($sl <= 0) { $skipped++; continue; }

                /** @var VtItem $item */
                $item = VtItem::firstOrCreate(
                    ['ma_vt' => $r['ma_vt']],
                    [
                        'ten_vt'         => $r['ten_vt'] ?? $r['ma_vt'],
                        'danh_muc_vt'    => $r['danh_muc_vt'] ?? null,
                        'nhom_vt'        => $r['nhom_vt'] ?? null,
                        'don_vi_tinh'    => $r['don_vi_tinh'] ?? null,
                        'loai'           => $r['loai'] ?? 'CONSUMABLE',
                        'trang_thai'     => 1,
                        'nguoi_tao'      => $userId,
                        'nguoi_cap_nhat' => $userId,
                    ]
                );

                $ngay = $r['ngay_ct'] ?? $r['ngay_nhap'] ?? Carbon::now()->toDateString();
                $donGia = null;
                if ($item->loai === 'ASSET') {
                    $donGia = (float) ($r['don_gia'] ?? 0.0);
                }

                // Ghi ledger OPENING
                VtLedger::create([
                    'vt_item_id'    => $item->id,
                    'ngay_ct'       => $ngay,
                    'loai_ct'       => VtLedger::CT_OPENING,
                    'so_luong_in'   => $sl,
                    'so_luong_out'  => 0,
                    'don_gia'       => $donGia,
                    'tham_chieu'    => 'OPENING',
                    'ghi_chu'       => Arr::get($r, 'ghi_chu'),
                    'nguoi_tao'     => $userId,
                    'nguoi_cap_nhat'=> $userId,
                ]);

                // Áp dụng vào tồn
                $this->stockService->applyReceipt($item->id, $sl, $donGia);

                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    // ------------------------------
    // RECEIPT (Phiếu nhập VT)
    // payload: ngay_ct, nha_cung_cap_id?, tham_chieu?, ghi_chu?, items: [{vt_item_id, so_luong, don_gia?, ghi_chu?}]
    // GHI CHÚ: Controller đã bỏ/ignore so_ct từ client. Ở đây chỉ chấp nhận so_ct gửi nội bộ (update giữ số cũ).
    // ------------------------------
    public function createReceipt(array $payload): VtReceipt
    {
        $userId = $this->currentUserId();
        $ngayCt = $payload['ngay_ct'] ?? Carbon::now()->toDateString();
        $items  = $payload['items'] ?? [];
        if (empty($items)) throw new InvalidArgumentException('Phiếu nhập không có dòng vật tư');

        // Nếu payload có so_ct (gọi nội bộ khi update) thì giữ; nếu không thì auto sinh PNVT-...
        $soCt = isset($payload['so_ct']) ? strtoupper(trim((string)$payload['so_ct'])) : $this->genSoCt('PNVT');

        return DB::transaction(function () use ($payload, $ngayCt, $items, $userId, $soCt) {
            $receipt = VtReceipt::create([
                'so_ct'           => $soCt,
                'ngay_ct'         => $ngayCt,
                'nha_cung_cap_id' => $payload['nha_cung_cap_id'] ?? null,
                'tham_chieu'      => $payload['tham_chieu'] ?? null,
                'ghi_chu'         => $payload['ghi_chu'] ?? null,
                'tong_so_luong'   => 0,
                'tong_gia_tri'    => null,
                'nguoi_tao'       => $userId,
                'nguoi_cap_nhat'  => $userId,
            ]);

            $tongSL = 0; $tongGT = 0.0;

            foreach ($items as $it) {
                $vtItemId = (int) $it['vt_item_id'];
                $soLuong  = (int) $it['so_luong'];
                $donGia   = isset($it['don_gia']) ? (float)$it['don_gia'] : null;

                if ($soLuong <= 0) throw new InvalidArgumentException('Số lượng nhập phải > 0');

                $item = VtItem::findOrFail($vtItemId);

                // Lưu dòng phiếu
                VtReceiptItem::create([
                    'vt_receipt_id'  => $receipt->id,
                    'vt_item_id'     => $vtItemId,
                    'so_luong'       => $soLuong,
                    'don_gia'        => $donGia,
                    'ghi_chu'        => $it['ghi_chu'] ?? null,
                    'nguoi_tao'      => $userId,
                    'nguoi_cap_nhat' => $userId,
                ]);

                // Ledger RECEIPT
                VtLedger::create([
                    'vt_item_id'    => $vtItemId,
                    'ngay_ct'       => $ngayCt,
                    'loai_ct'       => VtLedger::CT_RECEIPT,
                    'so_luong_in'   => $soLuong,
                    'so_luong_out'  => 0,
                    'don_gia'       => $item->loai === 'ASSET' ? ($donGia ?? 0.0) : null,
                    'tham_chieu'    => $receipt->so_ct,
                    'ghi_chu'       => $it['ghi_chu'] ?? null,
                    'nguoi_tao'     => $userId,
                    'nguoi_cap_nhat'=> $userId,
                ]);

                // Update tồn
                $this->stockService->applyReceipt($vtItemId, $soLuong, $donGia);

                $tongSL += $soLuong;
                if ($item->loai === 'ASSET') {
                    $tongGT += (($donGia ?? 0.0) * $soLuong);
                }
            }

            $receipt->update([
                'tong_so_luong'  => $tongSL,
                'tong_gia_tri'   => $tongGT > 0 ? $tongGT : null,
                'nguoi_cap_nhat' => $userId,
            ]);

            return $receipt->fresh();
        });
    }

    public function updateReceipt(int $receiptId, array $payload): VtReceipt
    {
        $userId  = $this->currentUserId();
        $receipt = VtReceipt::with('items')->findOrFail($receiptId);

        return DB::transaction(function () use ($receipt, $payload, $userId) {
            // Revert tồn & xóa ledger/items cũ
            foreach ($receipt->items as $old) {
                $this->reverseReceiptLine($old->vt_item_id, $old->so_luong);
            }
            VtLedger::where('tham_chieu', $receipt->so_ct)
                    ->where('loai_ct', VtLedger::CT_RECEIPT)
                    ->delete();
            $receipt->items()->delete();

            // Ghi lại như create — giữ số CT cũ
            $payload['so_ct']           = $receipt->so_ct;
            $payload['ngay_ct']         = $payload['ngay_ct'] ?? $receipt->ngay_ct->toDateString();
            $payload['nha_cung_cap_id'] = $payload['nha_cung_cap_id'] ?? $receipt->nha_cung_cap_id;

            $updated = $this->createReceipt($payload);

            // Cập nhật thông tin mô tả của header (ghi chú/tham chiếu) nếu đổi
            $receipt->update([
                'ngay_ct'         => $payload['ngay_ct'] ?? $receipt->ngay_ct,
                'nha_cung_cap_id' => $payload['nha_cung_cap_id'] ?? $receipt->nha_cung_cap_id,
                'tham_chieu'      => $payload['tham_chieu'] ?? $receipt->tham_chieu,
                'ghi_chu'         => $payload['ghi_chu'] ?? $receipt->ghi_chu,
                'nguoi_cap_nhat'  => $userId,
            ]);

            return $updated;
        });
    }

    public function deleteReceipt(int $receiptId): void
    {
        $receipt = VtReceipt::with('items')->findOrFail($receiptId);

        DB::transaction(function () use ($receipt) {
            // Revert tồn và xóa ledger
            foreach ($receipt->items as $old) {
                $this->reverseReceiptLine($old->vt_item_id, $old->so_luong);
            }
            VtLedger::where('tham_chieu', $receipt->so_ct)
                    ->where('loai_ct', VtLedger::CT_RECEIPT)
                    ->delete();
            $receipt->items()->delete();
            $receipt->delete();
        });
    }

    protected function reverseReceiptLine(int $vtItemId, int $soLuong): void
    {
        // Revert nhập = xuất đúng số lượng đã nhập (đơn giá dùng BQ hiện hành)
        $this->stockService->applyIssue($vtItemId, $soLuong);
    }

    // ------------------------------
    // ISSUE (Phiếu xuất VT)
    // payload: ngay_ct, ly_do(BAN|HUY|CHUYEN|KHAC), tham_chieu?, ghi_chu?, items: [{vt_item_id, so_luong, ghi_chu?}]
    // GHI CHÚ: Controller đã bỏ/ignore so_ct từ client. Ở đây chỉ chấp nhận so_ct gửi nội bộ (update giữ số cũ).
    // ------------------------------
    public function createIssue(array $payload): VtIssue
    {
        $userId = $this->currentUserId();
        $ngayCt = $payload['ngay_ct'] ?? Carbon::now()->toDateString();
        $lyDo   = $payload['ly_do'] ?? 'KHAC';
        $items  = $payload['items'] ?? [];
        if (empty($items)) throw new InvalidArgumentException('Phiếu xuất không có dòng vật tư');

        // Nếu payload có so_ct (gọi nội bộ khi update) thì giữ; nếu không thì auto sinh PXVT-...
        $soCt = isset($payload['so_ct']) ? strtoupper(trim((string)$payload['so_ct'])) : $this->genSoCt('PXVT');

        return DB::transaction(function () use ($payload, $ngayCt, $lyDo, $items, $userId, $soCt) {
            $issue = VtIssue::create([
                'so_ct'          => $soCt,
                'ngay_ct'        => $ngayCt,
                'ly_do'          => $lyDo,
                'tham_chieu'     => $payload['tham_chieu'] ?? null,
                'ghi_chu'        => $payload['ghi_chu'] ?? null,
                'tong_so_luong'  => 0,
                'tong_gia_tri'   => null,
                'nguoi_tao'      => $userId,
                'nguoi_cap_nhat' => $userId,
            ]);

            $tongSL = 0; $tongGT = 0.0;

            foreach ($items as $it) {
                $vtItemId = (int) $it['vt_item_id'];
                $soLuong  = (int) $it['so_luong'];
                if ($soLuong <= 0) throw new InvalidArgumentException('Số lượng xuất phải > 0');

                $item = VtItem::findOrFail($vtItemId);

                // Tính & trừ tồn
                $issueCalc = $this->stockService->applyIssue($vtItemId, $soLuong);
                $avgUsed   = $issueCalc['avg_cost_used'] ?? 0.0;
                $valueOut  = $issueCalc['value_out'] ?? 0.0;

                // Lưu dòng phiếu
                VtIssueItem::create([
                    'vt_issue_id'    => $issue->id,
                    'vt_item_id'     => $vtItemId,
                    'so_luong'       => $soLuong,
                    'ghi_chu'        => $it['ghi_chu'] ?? null,
                    'nguoi_tao'      => $userId,
                    'nguoi_cap_nhat' => $userId,
                ]);

                // Ledger ISSUE
                VtLedger::create([
                    'vt_item_id'    => $vtItemId,
                    'ngay_ct'       => $ngayCt,
                    'loai_ct'       => VtLedger::CT_ISSUE,
                    'so_luong_in'   => 0,
                    'so_luong_out'  => $soLuong,
                    'don_gia'       => $item->loai === 'ASSET' ? $avgUsed : null,
                    'tham_chieu'    => $issue->so_ct,
                    'ghi_chu'       => $it['ghi_chu'] ?? null,
                    'nguoi_tao'     => $userId,
                    'nguoi_cap_nhat'=> $userId,
                ]);

                $tongSL += $soLuong;
                if ($item->loai === 'ASSET') {
                    $tongGT += $valueOut;
                }
            }

            $issue->update([
                'tong_so_luong'  => $tongSL,
                'tong_gia_tri'   => $tongGT > 0 ? $tongGT : null,
                'nguoi_cap_nhat' => $userId,
            ]);

            return $issue->fresh();
        });
    }

    public function updateIssue(int $issueId, array $payload): VtIssue
    {
        $userId = $this->currentUserId();
        $issue  = VtIssue::with('items')->findOrFail($issueId);

        return DB::transaction(function () use ($issue, $payload, $userId) {
            // Revert tồn & xóa ledger/items cũ
            foreach ($issue->items as $old) {
                // Revert xuất = nhập lại đúng số lượng
                $this->stockService->applyReceipt($old->vt_item_id, $old->so_luong, null);
            }
            VtLedger::where('tham_chieu', $issue->so_ct)
                    ->where('loai_ct', VtLedger::CT_ISSUE)
                    ->delete();
            $issue->items()->delete();

            // Ghi lại như create — giữ số CT cũ
            $payload['so_ct']   = $issue->so_ct;
            $payload['ngay_ct'] = $payload['ngay_ct'] ?? $issue->ngay_ct->toDateString();
            $payload['ly_do']   = $payload['ly_do'] ?? $issue->ly_do;

            $updated = $this->createIssue($payload);

            // Cập nhật thông tin mô tả header
            $issue->update([
                'ngay_ct'        => $payload['ngay_ct'] ?? $issue->ngay_ct,
                'ly_do'          => $payload['ly_do'] ?? $issue->ly_do,
                'tham_chieu'     => $payload['tham_chieu'] ?? $issue->tham_chieu,
                'ghi_chu'        => $payload['ghi_chu'] ?? $issue->ghi_chu,
                'nguoi_cap_nhat' => $userId,
            ]);

            return $updated;
        });
    }

    public function deleteIssue(int $issueId): void
    {
        $issue = VtIssue::with('items')->findOrFail($issueId);

        DB::transaction(function () use ($issue) {
            // Revert tồn & xóa ledger
            foreach ($issue->items as $old) {
                $this->stockService->applyReceipt($old->vt_item_id, $old->so_luong, null);
            }
            VtLedger::where('tham_chieu', $issue->so_ct)
                    ->where('loai_ct', VtLedger::CT_ISSUE)
                    ->delete();
            $issue->items()->delete();
            $issue->delete();
        });
    }

    // ------------------------------
    // Truy vấn báo cáo
    // ------------------------------
    public function listLedger(array $filters = []): array
    {
        $q = VtLedger::query()
            ->with(['item:id,ma_vt,ten_vt,don_vi_tinh,loai'])
            ->orderBy('ngay_ct','desc')
            ->orderBy('id','desc');

        if (!empty($filters['vt_item_id'])) {
            $q->where('vt_item_id', (int)$filters['vt_item_id']);
        }
        if (!empty($filters['from'])) {
            $q->whereDate('ngay_ct', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $q->whereDate('ngay_ct', '<=', $filters['to']);
        }
        if (!empty($filters['loai_ct'])) {
            $q->where('loai_ct', $filters['loai_ct']);
        }
        if (!empty($filters['q'])) {
            $like = '%'.trim($filters['q']).'%';
            $q->where(function($qq) use ($like) {
                $qq->where('tham_chieu','like',$like)->orWhere('ghi_chu','like',$like);
            });
        }

        // paginate
        $perPage = (int) ($filters['per_page'] ?? 20);
        $page    = (int) ($filters['page'] ?? 1);
        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        return [
            'collection' => $paginator->items(),
            'total'      => $paginator->total(),
            'pagination' => [
                'current_page'  => $paginator->currentPage(),
                'last_page'     => $paginator->lastPage(),
                'per_page'      => $paginator->perPage(),
            ]
        ];
    }

    public function listStocks(array $filters = []): array
    {
        $q = VtStock::query()
            ->with(['item:id,ma_vt,ten_vt,don_vi_tinh,loai,danh_muc_vt,nhom_vt'])
            ->orderBy('id','desc');

        if (!empty($filters['loai'])) {
            $q->whereHas('item', fn($qq) => $qq->where('loai', $filters['loai']));
        }
        if (!empty($filters['q'])) {
            $like = '%'.trim($filters['q']).'%';
            $q->whereHas('item', function($qq) use ($like) {
                $qq->where('ma_vt','like',$like)
                   ->orWhere('ten_vt','like',$like)
                   ->orWhere('danh_muc_vt','like',$like)
                   ->orWhere('nhom_vt','like',$like);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page    = (int) ($filters['page'] ?? 1);
        $paginator = $q->paginate($perPage, ['*'], 'page', $page);

        return [
            'collection' => array_map(function($row){
                // làm phẳng dữ liệu cho FE
                return [
                    'id'           => $row->id,
                    'vt_item_id'   => $row->vt_item_id,
                    'ma_vt'        => $row->item->ma_vt ?? null,
                    'ten_vt'       => $row->item->ten_vt ?? null,
                    'don_vi_tinh'  => $row->item->don_vi_tinh ?? null,
                    'loai'         => $row->item->loai ?? null,
                    'danh_muc_vt'  => $row->item->danh_muc_vt ?? null,
                    'nhom_vt'      => $row->item->nhom_vt ?? null,
                    'so_luong_ton' => (int) $row->so_luong_ton,
                    'gia_tri_ton'  => $row->gia_tri_ton !== null ? (float)$row->gia_tri_ton : null,
                ];
            }, $paginator->items()),
            'total'      => $paginator->total(),
            'pagination' => [
                'current_page'  => $paginator->currentPage(),
                'last_page'     => $paginator->lastPage(),
                'per_page'      => $paginator->perPage(),
            ]
        ];
    }
}
