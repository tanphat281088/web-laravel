<?php

namespace App\Services\VT;

use App\Models\VtItem;
use App\Models\VtStock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VtStockService
{
    /**
     * Lấy (hoặc tạo) snapshot tồn cho 1 vật tư.
     */
    public function getOrCreateStock(int $vtItemId): VtStock
    {
        return VtStock::firstOrCreate(
            ['vt_item_id' => $vtItemId],
            ['so_luong_ton' => 0, 'gia_tri_ton' => null]
        );
    }

    /**
     * Cộng tồn khi nhập (RECEIPT/OPENING).
     * - ASSET: cập nhật số lượng + giá trị tồn (bình quân gia quyền).
     * - CONSUMABLE: chỉ cập nhật số lượng (bỏ qua giá trị).
     */
    public function applyReceipt(int $vtItemId, int $soLuong, ?float $donGiaNhap = null): void
    {
        if ($soLuong <= 0) {
            throw new InvalidArgumentException('Số lượng nhập phải > 0');
        }

        /** @var VtItem $item */
        $item = VtItem::findOrFail($vtItemId);
        $stock = $this->getOrCreateStock($vtItemId);

        // Cập nhật số lượng
        $newQty = $stock->so_luong_ton + $soLuong;

        if ($item->loai === 'ASSET') {
            // Giá trị tồn hiện tại
            $currentValue = (float) ($stock->gia_tri_ton ?? 0.0);
            $inValue = (float) (($donGiaNhap ?? 0.0) * $soLuong);

            // Giá trị tồn mới
            $newValue = $currentValue + $inValue;

            $stock->so_luong_ton = $newQty;
            $stock->gia_tri_ton  = $newValue;
        } else {
            // CONSUMABLE
            $stock->so_luong_ton = $newQty;
            // không đụng gia_tri_ton
        }

        $stock->save();
    }

    /**
     * Trừ tồn khi xuất (ISSUE).
     * - ASSET: dùng đơn giá bình quân hiện hành để trừ giá trị.
     * - CONSUMABLE: chỉ trừ số lượng.
     * Trả về mảng ['avg_cost_used' => float, 'value_out' => float] cho báo cáo.
     */
    public function applyIssue(int $vtItemId, int $soLuong): array
    {
        if ($soLuong <= 0) {
            throw new InvalidArgumentException('Số lượng xuất phải > 0');
        }

        /** @var VtItem $item */
        $item  = VtItem::findOrFail($vtItemId);
        $stock = $this->getOrCreateStock($vtItemId);

        if ($stock->so_luong_ton < $soLuong) {
            throw new InvalidArgumentException('Số lượng xuất vượt quá tồn hiện tại');
        }

        $result = ['avg_cost_used' => 0.0, 'value_out' => 0.0];

        if ($item->loai === 'ASSET') {
            $currentQty   = max(0, (int)$stock->so_luong_ton);
            $currentValue = (float) ($stock->gia_tri_ton ?? 0.0);
            $avg          = $currentQty > 0 ? ($currentValue / $currentQty) : 0.0;

            $valueOut = $avg * $soLuong;

            $newQty   = $currentQty - $soLuong;
            $newValue = max(0.0, $currentValue - $valueOut);

            $stock->so_luong_ton = $newQty;
            $stock->gia_tri_ton  = $newValue;
            $stock->save();

            $result['avg_cost_used'] = round($avg, 2);
            $result['value_out']     = round($valueOut, 2);
        } else {
            // CONSUMABLE
            $stock->so_luong_ton = $stock->so_luong_ton - $soLuong;
            $stock->save();
        }

        return $result;
    }

    /**
     * Điều chỉnh tồn (ADJUST).
     * - delta > 0: tăng giống nhập (ASSET dùng đơn giá bình quân hiện hành hoặc 0 nếu chưa có).
     * - delta < 0: giảm giống xuất (ASSET dùng đơn giá bình quân hiện hành).
     * Trả về mảng ['avg_cost_used' => float, 'value_delta' => float] cho báo cáo.
     */
    public function applyAdjust(int $vtItemId, int $delta): array
    {
        if ($delta === 0) {
            return ['avg_cost_used' => 0.0, 'value_delta' => 0.0];
        }

        /** @var VtItem $item */
        $item  = VtItem::findOrFail($vtItemId);
        $stock = $this->getOrCreateStock($vtItemId);

        $result = ['avg_cost_used' => 0.0, 'value_delta' => 0.0];

        if ($delta > 0) {
            // Tăng tồn
            if ($item->loai === 'ASSET') {
                $currentQty   = (int) $stock->so_luong_ton;
                $currentValue = (float) ($stock->gia_tri_ton ?? 0.0);
                $avg          = $currentQty > 0 ? ($currentValue / $currentQty) : 0.0; // nếu chưa có, coi 0

                $inValue = $avg * $delta;
                $stock->so_luong_ton = $currentQty + $delta;
                $stock->gia_tri_ton  = $currentValue + $inValue;
                $stock->save();

                $result['avg_cost_used'] = round($avg, 2);
                $result['value_delta']   = round($inValue, 2);
            } else {
                $stock->so_luong_ton = $stock->so_luong_ton + $delta;
                $stock->save();
            }
        } else {
            // Giảm tồn
            $abs = abs($delta);
            if ($stock->so_luong_ton < $abs) {
                throw new InvalidArgumentException('Điều chỉnh giảm vượt quá tồn hiện tại');
            }

            if ($item->loai === 'ASSET') {
                $currentQty   = (int) $stock->so_luong_ton;
                $currentValue = (float) ($stock->gia_tri_ton ?? 0.0);
                $avg          = $currentQty > 0 ? ($currentValue / $currentQty) : 0.0;

                $outValue = $avg * $abs;
                $stock->so_luong_ton = $currentQty - $abs;
                $stock->gia_tri_ton  = max(0.0, $currentValue - $outValue);
                $stock->save();

                $result['avg_cost_used'] = round($avg, 2);
                $result['value_delta']   = -round($outValue, 2);
            } else {
                $stock->so_luong_ton = $stock->so_luong_ton - $abs;
                $stock->save();
            }
        }

        return $result;
    }

    /**
     * Tính đơn giá bình quân hiện hành (ASSET).
     */
    public function getAvgCost(int $vtItemId): float
    {
        $stock = $this->getOrCreateStock($vtItemId);
        $qty   = (int) $stock->so_luong_ton;
        $val   = (float) ($stock->gia_tri_ton ?? 0.0);
        return $qty > 0 ? round($val / $qty, 2) : 0.0;
    }
}
