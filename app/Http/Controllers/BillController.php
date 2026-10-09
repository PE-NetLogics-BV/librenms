<?php

namespace App\Http\Controllers;

use App\Facades\LibrenmsConfig;
use App\Http\Requests\StoreBillRequest;
use App\Http\Requests\UpdateBillRequest;
use App\Models\Bill;
use App\Models\Port;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LibreNMS\Billing;
use LibreNMS\Util\Color;
use LibreNMS\Util\Html;
use LibreNMS\Util\Number;
use LibreNMS\Util\Url;

class BillController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Bill::class);

        // legacy links: bills/view=add/port=123 and bills?bill_type=cdr&state=over
        $vars = Url::parseLegacyPathVars();
        $port = isset($vars['port']) ? Port::hasAccess($request->user())->find($vars['port']) : null;

        return view('bills.index', [
            'filters' => [
                'period' => $vars['period'] ?? '',
                'bill_type' => in_array($vars['bill_type'] ?? '', ['cdr', 'quota']) ? $vars['bill_type'] : '',
                'state' => in_array($vars['state'] ?? '', ['under', 'over']) ? $vars['state'] : '',
            ],
            'open_create' => ($vars['view'] ?? null) === 'add' && $request->user()->can('create', Bill::class),
            'port' => $port,
        ]);
    }

    public function show(Request $request, Bill $bill): View
    {
        $this->authorize('view', $bill);

        // legacy links: bill/bill_id=1/view=history/detail=all and bill/1?view=edit
        $vars = Url::parseLegacyPathVars();
        $view = $vars['view'] ?? 'quick';
        $options = $this->viewOptions($request, $bill);
        abort_unless(isset($options[$view]), in_array($view, ['edit', 'delete', 'reset']) ? 403 : 404);

        [$from, $to] = Billing::getDates($bill->bill_day);
        $data = [
            'bill' => $bill,
            'view' => $view,
            'template' => in_array($view, ['quick', 'accurate']) ? 'graphs' : $view,
            'options' => $options,
            'ports' => $bill->ports()->with('device')->orderBy('ports.device_id')->get(),
            'period' => [
                'from' => Carbon::parse($from)->format(LibrenmsConfig::get('dateformat.date', 'Y-m-d')),
                'to' => Carbon::parse($to)->format(LibrenmsConfig::get('dateformat.date', 'Y-m-d')),
            ],
        ];

        return view('bills.show', $data + match ($view) {
            'quick', 'accurate' => $this->usageData($bill, $view, $vars, $from, $to),
            'transfer' => $this->transferData($bill, $from, $to),
            'history' => $this->historyData($bill, $vars),
            default => [],
        });
    }

    /**
     * @return array<string, array{text: string, link: string}>
     */
    private function viewOptions(Request $request, Bill $bill): array
    {
        $views = [
            'quick' => __('Quick Graphs'),
            'accurate' => __('Accurate Graphs'),
            'transfer' => __('Transfer Graphs'),
            'history' => __('Historical Graphs'),
        ];
        if ($request->user()->can('update', $bill)) {
            $views['edit'] = __('Edit');
        }
        if ($request->user()->can('delete', $bill)) {
            $views['delete'] = __('Delete');
        }
        if ($request->user()->can('update', $bill)) {
            $views['reset'] = __('Reset');
        }

        $options = [];
        foreach ($views as $key => $text) {
            $options[$key] = ['text' => $text, 'link' => route('bill.show', ['bill' => $bill, 'view' => $key])];
        }

        return $options;
    }

    /**
     * Summary and graphs of the current period (quick: bill_bits, accurate: bill_historicbits)
     *
     * @param  array<string, mixed>  $vars
     * @return array<string, mixed>
     */
    private function usageData(Bill $bill, string $view, array $vars, string $from, string $to): array
    {
        $graph = ['id' => $bill->bill_id, 'width' => 1190, 'height' => 250] + ($bill->bill_type === 'quota' ? ['ave' => 'yes'] : ['95th' => 'yes']);
        if ($view === 'accurate') {
            $graph += ['type' => 'bill_historicbits', 'bill_code' => (string) ($vars['bill_code'] ?? '')];
        } else {
            $graph = ['type' => 'bill_bits', 'total' => 1, 'dir' => $bill->dir_95th, 'width' => 1000, 'height' => 200] + $graph;
        }

        $now = now();
        $graphs = [
            __('Billing View') => $graph + ['from' => strtotime($from), 'to' => strtotime($to)],
            __('24 Hour View') => $graph + ['from' => $now->copy()->subDay()->getTimestamp(), 'to' => $now->getTimestamp()],
            __('Monthly View') => $graph + ['from' => $now->copy()->subMonth()->getTimestamp(), 'to' => $now->getTimestamp()],
        ];

        if ($bill->bill_type === 'quota') {
            $percent = Number::calculatePercent($bill->total_data, $bill->bill_quota);
            $summary = [
                'title' => __('Quota Bill'),
                'used' => Billing::formatBytes($bill->total_data),
                'allowed' => Billing::formatBytes($bill->bill_quota),
                'detail' => __('Average rate') . ' ' . Number::formatSi($bill->rate_average, 2, 0, 'bps'),
                'predicted' => Billing::formatBytes(Billing::getPredictedUsage($bill->bill_day, $bill->total_data)),
            ];
        } else {
            $percent = Number::calculatePercent($bill->rate_95th, $bill->bill_cdr);
            $summary = [
                'title' => __('CDR / 95th Bill'),
                'used' => Number::formatSi($bill->rate_95th, 2, 0, '') . 'bps',
                'allowed' => Number::formatSi($bill->bill_cdr, 2, 0, '') . 'bps',
                'detail' => '(95th%ile)',
                'predicted' => Number::formatSi(Billing::getPredictedUsage($bill->bill_day, $bill->rate_95th), 2, 0, '') . 'bps',
            ];
        }
        $summary['percent'] = $percent;
        $summary['bar'] = Html::percentageBar(200, 10, $percent, right_text: $percent . '%');

        return ['summary' => $summary, 'graphs' => $graphs];
    }

    /**
     * Transferred data so far, averaged and extrapolated to the end of the period
     *
     * @return array<string, mixed>
     */
    private function transferData(Bill $bill, string $from, string $to): array
    {
        $elapsed_days = max(1, (int) ceil((time() - strtotime($from)) / 86400));
        $total_days = max(1, (int) round((strtotime($to) - strtotime($from)) / 86400));
        $allowed = $bill->bill_type === 'quota' ? Billing::formatBytes($bill->bill_quota) : '-';

        $row = function (string $name, int $bytes, float $percent) use ($elapsed_days, $total_days, $allowed): array {
            $colors = Color::percentage($percent, null);

            return [
                'name' => $name,
                'data' => Billing::formatBytes($bytes),
                'allowed' => $allowed,
                'average' => Billing::formatBytes($bytes / $elapsed_days),
                'estimated' => Billing::formatBytes($bytes / $elapsed_days * $total_days),
                'bar' => Html::percentageBar(200, 10, $percent, null, $percent . '%', null, null, ['left' => $colors['left'], 'left_text' => null, 'right' => $colors['right'], 'right_text' => null]),
                'color' => $colors['left'],
            ];
        };

        $estimated_total = $bill->total_data / $elapsed_days * $total_days;
        $rows = [
            $row(__('Transferred'), (int) $bill->total_data, $bill->bill_type === 'quota' ? Number::calculatePercent($bill->total_data, $bill->bill_quota) : Number::calculatePercent($bill->total_data, $estimated_total)),
            $row(__('Inbound'), (int) $bill->total_data_in, Number::calculatePercent($bill->total_data_in, $bill->total_data)),
            $row(__('Outbound'), (int) $bill->total_data_out, Number::calculatePercent($bill->total_data_out, $bill->total_data)),
        ];
        $overuse = $bill->total_data - $bill->bill_quota;
        if ($bill->bill_type === 'quota' && $overuse > 0) {
            $rows[] = ['overuse' => true] + $row(__('Already overusage'), (int) $overuse, max(0, Number::calculatePercent($bill->total_data, $bill->bill_quota) - 100));
        }

        $graph = ['type' => 'bill_historictransfer', 'id' => $bill->bill_id, 'width' => 1190, 'height' => 250];
        $now = now();
        $graphs = [
            __('Billing Period View') => $graph + ['from' => strtotime($from), 'to' => strtotime($to), 'imgtype' => 'day'],
            __('Rolling 24 Hour View') => $graph + ['from' => $now->copy()->subDay()->getTimestamp(), 'to' => $now->getTimestamp(), 'imgtype' => 'hour'],
            __('Rolling Monthly View') => $graph + ['from' => $now->copy()->subMonth()->getTimestamp(), 'to' => $now->getTimestamp(), 'imgtype' => 'day'],
        ];

        return ['transfer' => $rows, 'graphs' => $graphs];
    }

    /**
     * The last 24 closed billing periods, with detail graphs for the requested one(s)
     *
     * @param  array<string, mixed>  $vars
     * @return array<string, mixed>
     */
    private function historyData(Bill $bill, array $vars): array
    {
        $base = (int) LibrenmsConfig::get('billing.base', 1000);
        $detail = $vars['detail'] ?? null;
        $graph = ['id' => $bill->bill_id, 'width' => 1190, 'height' => 250];

        $history = $bill->history()->orderByDesc('bill_datefrom')->limit(24)->get()->map(function ($entry) use ($bill, $base, $detail, $graph) {
            $cdr = strtolower((string) $entry->bill_type) === 'cdr';
            $colors = Color::percentage($entry->bill_percent, null);
            $format = $cdr
                ? fn ($value) => Number::formatSi($value, 2, 0, 'bps')
                : fn ($value) => Number::formatBase($value, $base, 2, 0, '');
            $show_detail = $detail === 'all' || (string) $detail === (string) $entry->bill_hist_id;

            return [
                'id' => $entry->bill_hist_id,
                'period' => date('Y-m-d', strtotime((string) $entry->bill_datefrom)) . ' to ' . date('Y-m-d', strtotime((string) $entry->bill_dateto)),
                'type' => $cdr ? 'CDR' : 'Quota',
                'allowed' => $format($entry->bill_allowed),
                'in' => $format($cdr ? $entry->rate_95th_in : $entry->traf_in),
                'out' => $format($cdr ? $entry->rate_95th_out : $entry->traf_out),
                'peak_out' => Number::formatBase($entry->bill_peak_out, $base, 2, 0, ''),
                'peak_in' => Number::formatBase($entry->bill_peak_in, $base, 2, 0, ''),
                'total' => Number::formatBase($entry->traf_total, $base, 2, 0, ''),
                'rate_95th' => Number::formatSi($entry->rate_95th, 2, 0, 'bps'),
                'overuse' => $entry->bill_overuse <= 0 ? null : ($cdr ? $format($entry->bill_overuse) : Number::formatBase($entry->bill_overuse, $base)),
                'color' => $colors['left'],
                'bar' => Html::percentageBar(250, 10, $entry->bill_percent, null, $entry->bill_percent . '%', null, null, ['left' => $colors['left'], 'left_text' => null, 'right' => $colors['right'], 'right_text' => null]),
                'detail_link' => route('bill.show', ['bill' => $bill, 'view' => 'history', 'detail' => $entry->bill_hist_id]),
                'detail_graphs' => $show_detail ? [
                    route('graph', $graph + ['type' => 'bill_historicbits', 'bill_hist_id' => $entry->bill_hist_id]),
                    route('graph', $graph + ['type' => 'bill_historictransfer', 'imgtype' => 'day', 'bill_hist_id' => $entry->bill_hist_id]),
                    route('graph', $graph + ['type' => 'bill_historictransfer', 'imgtype' => 'hour', 'bill_hist_id' => $entry->bill_hist_id]),
                ] : [],
            ];
        });

        return [
            'history' => $history,
            'monthly_graph' => route('graph', $graph + ['type' => 'bill_historicmonthly']),
            'all_details_link' => route('bill.show', ['bill' => $bill, 'view' => 'history', 'detail' => 'all']),
        ];
    }

    public function store(StoreBillRequest $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();

        $bill = Bill::create([
            'bill_name' => $validated['bill_name'],
            'bill_type' => $validated['bill_type'],
            'bill_day' => $validated['bill_day'],
            'dir_95th' => $validated['dir_95th'] ?? (LibrenmsConfig::get('billing.95th_default_agg') ? 'agg' : 'in'),
            'bill_custid' => (string) ($validated['bill_custid'] ?? ''),
            'bill_ref' => (string) ($validated['bill_ref'] ?? ''),
            'bill_notes' => (string) ($validated['bill_notes'] ?? ''),
            'rate_95th_in' => 0,
            'rate_95th_out' => 0,
            'rate_95th' => 0,
            'total_data' => 0,
            'total_data_in' => 0,
            'total_data_out' => 0,
            'rate_average' => 0,
            'rate_average_in' => 0,
            'rate_average_out' => 0,
            'bill_last_calc' => now(),
            'bill_autoadded' => 0,
        ] + $this->limits($request, $validated));

        if (! empty($validated['port_id'])) {
            $bill->ports()->attach($validated['port_id']);
        }

        toast()->success(__('Bill Created'));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'message' => __('Bill Created'), 'bill_id' => $bill->bill_id]);
        }

        return redirect()->route('bill.show', ['bill' => $bill, 'view' => 'edit']);
    }

    public function update(UpdateBillRequest $request, Bill $bill): RedirectResponse|JsonResponse
    {
        $validated = $request->validated();
        ['bill_quota' => $billQuota, 'bill_cdr' => $billCdr] = $this->limits($request, $validated);

        $bill->update([
            'bill_name' => $validated['bill_name'],
            'bill_day' => $validated['bill_day'],
            'bill_quota' => $billQuota,
            'bill_cdr' => $billCdr,
            'bill_type' => $validated['bill_type'],
            'dir_95th' => $validated['dir_95th'] ?? $bill->dir_95th ?? 'in',
            'bill_custid' => (string) ($validated['bill_custid'] ?? ''),
            'bill_ref' => (string) ($validated['bill_ref'] ?? ''),
            'bill_notes' => (string) ($validated['bill_notes'] ?? ''),
        ]);

        toast()->success(__('Bill Properties Updated'));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'message' => __('Bill Properties Updated')]);
        }

        return redirect()->route('bill.show', ['bill' => $bill, 'view' => 'edit']);
    }

    public function destroy(Request $request, Bill $bill): RedirectResponse|JsonResponse
    {
        $this->authorize('delete', $bill);

        $bill->delete();

        toast()->success(__('Bill Deleted'));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'message' => __('Bill Deleted')]);
        }

        return redirect()->route('bills.index');
    }

    public function reset(Request $request, Bill $bill): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $bill);

        $confirm = $request->input('confirm');

        if ($confirm === 'mysql') {
            $bill->history()->delete();
            $bill->data()->delete();
        }

        toast()->success(__('Bill Resetting'));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'message' => __('Bill Resetting')]);
        }

        return redirect()->route('bills.index');
    }

    /**
     * The quota or CDR limit in bytes or bits, converted from the unit the form was filled in
     *
     * @param  array<string, mixed>  $validated
     * @return array{bill_quota: int, bill_cdr: int}
     */
    private function limits(Request $request, array $validated): array
    {
        $base = (int) LibrenmsConfig::get('billing.base', 1000);
        $billQuota = 0;
        $billCdr = 0;

        if ($validated['bill_type'] === 'quota' && isset($validated['bill_quota'])) {
            $unit = $base == 1024 ? str_replace('B', 'iB', $request->input('bill_quota_type', 'MB')) : $request->input('bill_quota_type', 'MB');
            $bytes = Number::toBytes("{$validated['bill_quota']} $unit");
            $billQuota = is_nan($bytes) ? 0 : (int) $bytes;
        } elseif ($validated['bill_type'] === 'cdr' && isset($validated['bill_cdr'])) {
            $unit = $base == 1024 ? str_replace('bps', 'ibps', $request->input('bill_cdr_type', 'Mbps')) : $request->input('bill_cdr_type', 'Mbps');
            $bits = Number::toBytes("{$validated['bill_cdr']} $unit");
            $billCdr = is_nan($bits) ? 0 : (int) $bits;
        }

        return ['bill_quota' => $billQuota, 'bill_cdr' => $billCdr];
    }

    public function attachPort(Request $request, Bill $bill): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $bill);

        $validated = $request->validate([
            'port_id' => ['required', 'integer', 'exists:ports,port_id'],
        ]);

        $bill->ports()->syncWithoutDetaching([$validated['port_id']]);

        toast()->success(__('Port added to bill'));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'message' => __('Port added to bill')]);
        }

        return redirect()->route('bill.show', ['bill' => $bill, 'view' => 'edit']);
    }

    public function detachPort(Request $request, Bill $bill, Port $port): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $bill);

        $bill->ports()->detach($port->port_id);

        toast()->success(__('Port removed from bill'));

        if ($request->wantsJson()) {
            return response()->json(['status' => 'ok', 'message' => __('Port removed from bill')]);
        }

        return redirect()->route('bill.show', ['bill' => $bill, 'view' => 'edit']);
    }
}
