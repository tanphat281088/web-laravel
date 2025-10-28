<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class KQKDExport implements FromView
{
    protected array $summary;
    protected array $series;
    protected ?string $from;
    protected ?string $to;

    public function __construct(array $summary, array $series, ?string $from = null, ?string $to = null)
    {
        $this->summary = $summary;
        $this->series  = $series;
        $this->from    = $from;
        $this->to      = $to;
    }

    public function view(): View
    {
        return view('exports.kqkd', [
            'summary' => $this->summary,
            'series'  => $this->series,
            'from'    => $this->from,
            'to'      => $this->to,
        ]);
    }
}
