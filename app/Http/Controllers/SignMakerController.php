<?php

namespace App\Http\Controllers;

use App\Models\SignJob;
use App\Models\SignTemplate;
use App\Services\SignMaker\SignRenderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Dompdf\Dompdf;
use Dompdf\Options;

class SignMakerController extends Controller
{
    public function __construct(private SignRenderService $render) {}

    public function templates()
    {
        if (!Config::get('sign_maker.enabled', true)) {
            return response()->json(['success' => false, 'message' => 'Chá»©c nÄƒng Sign Maker Ä‘ang táº¯t.'], 403);
        }
        $tpls = SignTemplate::orderBy('code')->get();
        return response()->json(['success' => true, 'data' => $tpls]);
    }

    /**
     * Preview 1 template -> tráº£ vá» HTML fragment (SVG) Ä‘á»ƒ UI render.
     */
    public function preview(Request $req)
    {
        try {
            if (!Config::get('sign_maker.enabled', true)) {
                return response()->json(['success' => false, 'message' => 'Chá»©c nÄƒng Sign Maker Ä‘ang táº¯t.'], 403);
            }

            $req->validate([
                'template_code' => 'required|string|max:100',
                'text'          => 'required|string|max:200',
                'font_size'     => 'nullable|numeric|min:8|max:200',
                'font_family'   => 'nullable|string|max:200',
            ], [], [
                'template_code' => 'mÃ£ máº«u',
                'text'          => 'ná»™i dung',
                'font_size'     => 'cá»¡ chá»¯',
                'font_family'   => 'font chá»¯',
            ]);

            $tpl  = SignTemplate::where('code', $req->string('template_code'))->firstOrFail();
            $text = $req->string('text')->toString();

            $data = $this->render->buildViewData($tpl, $text, [
                'style'       => $req->input('style', []),
                'font_size'   => $req->input('font_size'),
                'font_family' => $req->input('font_family'),
            ]);

            $html = view('sign-maker.partials.single', $data)->render();

            return response()->json(['success' => true, 'html' => $html]);
        } catch (\Throwable $e) {
            Log::error('SignMaker preview error', ['e' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'KhÃ´ng táº¡o Ä‘Æ°á»£c xem thá»­. Vui lÃ²ng kiá»ƒm tra máº«u hoáº·c káº¿t ná»‘i.'
            ], 422);
        }
    }

    /**
     * Export nhiá»u template thÃ nh 1 PDF (A3/A4/A5/A6/A7).
     */
    public function exportPdf(Request $req)
    {
        try {
            if (!config('sign_maker.enabled', true)) {
                return response()->json(['success' => false, 'message' => 'Chá»©c nÄƒng Sign Maker Ä‘ang táº¯t.'], 403);
            }

            $req->validate([
                'text'           => 'required|string|max:200',
                'template_codes' => 'required|array|min:1',
                'template_codes.*' => 'string|max:100',
                // Bá»• sung A5/A6/A7
                'paper'          => 'nullable|in:A3,A4,A5,A6,A7',
                'font_size'      => 'nullable|numeric|min:8|max:200',
                'font_family'    => 'nullable|string|max:200',
            ], [], [
                'text'           => 'ná»™i dung',
                'template_codes' => 'danh sÃ¡ch máº«u',
                'paper'          => 'khá»• giáº¥y',
                'font_size'      => 'cá»¡ chá»¯',
                'font_family'    => 'font chá»¯',
            ]);

            $paper = strtoupper($req->input('paper', 'A4'));
            if (!in_array($paper, ['A3','A4','A5','A6','A7'], true)) {
                $paper = 'A4';
            }

            $codes = $req->input('template_codes', []);
            $text  = $req->string('text')->toString();

            // Ghi job
            $job = SignJob::create([
                'user_id'        => optional($req->user())->id,
                'input_text'     => $text,
                'template_codes' => $codes,
                'export_type'    => 'pdf',
                'options'        => ['paper' => $paper],
                'status'         => SignJob::STATUS_PROCESSING,
                'started_at'     => now(),
            ]);

            $templates = SignTemplate::whereIn('code', $codes)->get();
            if ($templates->isEmpty()) {
                $job->update(['status' => SignJob::STATUS_FAILED, 'error_message' => 'KhÃ´ng tÃ¬m tháº¥y máº«u nÃ o.']);
                return response()->json(['success' => false, 'message' => 'KhÃ´ng tÃ¬m tháº¥y máº«u nÃ o.'], 422);
            }

            // Chuáº©n bá»‹ data cho view PDF
            $items = [];
            foreach ($templates as $tpl) {
                $items[] = $this->render->buildViewData($tpl, $text, [
                    'style'       => $req->input('style', []),
                    'font_size'   => $req->input('font_size'),
                    'font_family' => $req->input('font_family'),
                ]);
            }

            $pdfHtml = view('sign-maker.print', [
                'paper' => $paper,
                'items' => $items,
            ])->render();

            // Dompdf options (tiáº¿ng Viá»‡t)
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            // DejaVu Sans há»— trá»£ Unicode (tiáº¿ng Viá»‡t) â€“ Ä‘i kÃ¨m dompdf
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($pdfHtml);
            // Dompdf há»— trá»£ trá»±c tiáº¿p A3/A4/A5/A6/A7
            $dompdf->setPaper($paper, 'portrait');
            $dompdf->render();

            $folder   = 'sign_maker/exports/' . now()->format('Y/m/d');
            $slugText = Str::slug(mb_substr($text, 0, 40), '-');
            $filename = 'sign_' . $job->id . '_' . ($slugText ?: 'nhan') . '.pdf';
            $path     = $folder . '/' . $filename;

            Storage::disk('local')->put($path, $dompdf->output());

            $job->update([
                'status'       => SignJob::STATUS_DONE,
                'finished_at'  => now(),
                'result_paths' => ['pdf' => $path],
            ]);

            // LÆ°u Ã½: tham sá»‘ route pháº£i khá»›p vá»›i tÃªn param Ä‘á»‹nh nghÄ©a trong routes.
            // á»ž Ä‘Ã¢y dÃ¹ng key 'pathB64' cho khá»›p method download(string $pathB64)
            $downloadUrl = route('sign-maker.download', ['pathB64' => base64_encode($path)]);

            return response()->json([
                'success'      => true,
                'job_id'       => $job->id,
                'pdf_path'     => $path,
                'download_url' => $downloadUrl,
            ]);
        } catch (\Illuminate\Validation\ValidationException $ve) {
            return response()->json([
                'success' => false,
                'message' => $ve->getMessage(),
                'errors'  => $ve->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('SignMaker exportPdf error', ['e' => $e]);
            return response()->json([
                'success' => false,
                'message' => 'Xuáº¥t PDF tháº¥t báº¡i. Vui lÃ²ng thá»­ láº¡i hoáº·c liÃªn há»‡ há»— trá»£.',
            ], 500);
        }
    }

    /**
     * Táº£i file Ä‘Ã£ xuáº¥t (Ä‘Æ°á»ng dáº«n base64).
     */
    public function download(string $pathB64)
    {
        $path = base64_decode($pathB64);
        if (!is_string($path) || !Storage::disk('local')->exists($path)) {
            abort(404);
        }
        return response()->download(storage_path('app/' . $path));
    }
}
