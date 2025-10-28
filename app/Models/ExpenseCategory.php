<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class ExpenseCategory extends Model
{
    /**
     * Bảng master Danh mục chi (cha → con) phục vụ KQKD Mức A.
     * - code: mã ngắn, unique (vd: COGS, BH, QLDN, TC, CHI_KHAC, HOA, PK, INAN, ...)
     * - name: tên hiển thị
     * - parent_id: quan hệ cha → con (nullable đối với nhóm CHA)
     * - statement_line: dòng KQKD (02/05/06/07/10)
     */
    protected $table = 'expense_categories';

    protected $guarded = [];

    protected $casts = [
        'parent_id'      => 'integer',
        'statement_line' => 'integer',
        'sort_order'     => 'integer',
        'is_active'      => 'boolean',
    ];

    /* ====================== Quan hệ ====================== */

    // Cha của một danh mục con
    public function parent()
    {
        return $this->belongsTo(ExpenseCategory::class, 'parent_id');
    }

    // Danh sách con của một danh mục cha
    public function children()
    {
        return $this->hasMany(ExpenseCategory::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    // Các phiếu chi gắn về danh mục này
    public function phieuChis()
    {
        return $this->hasMany(PhieuChi::class, 'category_id');
    }

    /* ====================== Scopes tiện ích ====================== */

    // Chỉ lấy danh mục đang active
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Chỉ lấy nhóm CHA (parent_id IS NULL)
    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    // Lấy nhóm CON theo mã cha
    public function scopeChildrenOfCode($query, string $parentCode)
    {
        return $query->whereIn('parent_id', function ($q) use ($parentCode) {
            $q->select('id')
              ->from('expense_categories')
              ->where('code', $parentCode);
        });
    }

    // Tìm theo code (mã ổn định)
    public function scopeCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    /* ====================== Helpers ====================== */

    /**
     * Trả về mảng options chuẩn [{ value, label }]
     */
    public static function toOptions(Collection $rows): array
    {
        return $rows->map(fn ($r) => [
            'value' => $r->id,
            'label' => $r->name,
            'code'  => $r->code,
        ])->values()->all();
    }

    /**
     * Lấy cây cha → con (active) để FE có thể cache/hiển thị nhanh.
     */
    public static function getActiveTree(): array
    {
        $parents = static::query()->active()->parents()->orderBy('sort_order')->orderBy('name')->get();
        $ids     = $parents->pluck('id')->all();

        $children = static::query()
            ->active()
            ->whereIn('parent_id', $ids)
            ->orderBy('sort_order')->orderBy('name')
            ->get()
            ->groupBy('parent_id');

        return $parents->map(function ($p) use ($children) {
            return [
                'id'             => $p->id,
                'code'           => $p->code,
                'name'           => $p->name,
                'statement_line' => $p->statement_line,
                'children'       => isset($children[$p->id])
                    ? $children[$p->id]->map(fn ($c) => [
                        'id'             => $c->id,
                        'code'           => $c->code,
                        'name'           => $c->name,
                        'statement_line' => $c->statement_line,
                    ])->values()->all()
                    : [],
            ];
        })->values()->all();
    }
}
