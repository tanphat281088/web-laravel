<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use App\Class\CustomResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    /**
     * GET /api/expense-categories/parents
     * Trả về danh sách "Danh mục CHA" (active) dưới dạng options [{value,label,code}]
     * Ví dụ: COGS, BH, QLDN, TC, CHI_KHAC
     */
    public function parents()
    {
        try {
            $rows = ExpenseCategory::query()
                ->active()
                ->parents()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return CustomResponse::success(ExpenseCategory::toOptions($rows));
        } catch (\Throwable $e) {
            return CustomResponse::error('Lỗi lấy danh mục cha: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/expense-categories/options?parent_code=COGS
     * Trả về danh sách "Danh mục CON" theo mã cha (active) dưới dạng options [{value,label,code}]
     * - parent_code: mã ổn định của cha (vd: COGS, BH, QLDN, TC, CHI_KHAC)
     */
public function options(Request $request)
{
    try {
        // Chấp nhận cả parent_id (ưu tiên) hoặc parent_code
        $parentId   = $request->query('parent_id');
        $parentCode = $request->query('parent_code');

        if (empty($parentId) && empty($parentCode)) {
            return CustomResponse::error('Thiếu tham số parent_id hoặc parent_code', 422);
        }

        // Nếu gửi code → resolve ra id
        if (empty($parentId) && !empty($parentCode)) {
            $parentId = ExpenseCategory::query()->where('code', $parentCode)->value('id');
            if (!$parentId) {
                return CustomResponse::success([]); // không có danh mục cha này
            }
        }

        $rows = ExpenseCategory::query()
            ->active()
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return CustomResponse::success(ExpenseCategory::toOptions($rows));
    } catch (\Throwable $e) {
        return CustomResponse::error('Lỗi lấy danh mục con: ' . $e->getMessage(), 500);
    }
}


    /**
     * GET /api/expense-categories/tree
     * Trả về cây danh mục (CHA → CON) đang active, phục vụ cache/hiển thị nhanh ở FE
     */
    public function tree()
    {
        try {
            $tree = ExpenseCategory::getActiveTree();
            return CustomResponse::success($tree);
        } catch (\Throwable $e) {
            return CustomResponse::error('Lỗi lấy cây danh mục: ' . $e->getMessage(), 500);
        }
    }
}
