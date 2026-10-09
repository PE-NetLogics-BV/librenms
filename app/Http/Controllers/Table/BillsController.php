<?php

/**
 * BillsController.php
 *
 * Traffic bills table, current or previous billing period
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 */

namespace App\Http\Controllers\Table;

use App\Models\Bill;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use LibreNMS\Billing;
use LibreNMS\Util\Color;
use LibreNMS\Util\Html;
use LibreNMS\Util\Number;
use LibreNMS\Util\Url;

/**
 * @extends TableController<Bill>
 */
class BillsController extends TableController
{
    protected ?string $model = Bill::class;
    protected array $default_sort = ['bill_name' => 'asc'];

    private bool $previous = false;

    protected function rules(): array
    {
        return [
            'period' => 'nullable|in:prev',
            'bill_type' => 'nullable|in:cdr,quota',
            'state' => 'nullable|in:under,over',
        ];
    }

    protected function searchFields(Request $request): array
    {
        return ['bills.bill_name'];
    }

    protected function sortFields(Request $request): array
    {
        return $this->previous ? [
            'bill_name' => 'bills.bill_name',
            'bill_type' => 'bill_history.bill_type',
            'bill_allowed' => 'bill_history.bill_allowed',
            'total_data_in' => 'bill_history.traf_in',
            'total_data_out' => 'bill_history.traf_out',
            'total_data' => 'bill_history.traf_total',
            'rate_95th' => 'bill_history.rate_95th',
        ] : [
            'bill_name' => 'bills.bill_name',
            'bill_type' => 'bills.bill_type',
            'bill_allowed' => 'bill_allowed',
            'total_data_in' => 'bills.total_data_in',
            'total_data_out' => 'bills.total_data_out',
            'total_data' => 'bills.total_data',
            'rate_95th' => 'bills.rate_95th',
        ];
    }

    protected function filterFields(Request $request): array
    {
        $type = $this->previous ? 'bill_history.bill_type' : 'bills.bill_type';
        [$rate, $cdr, $traffic, $quota] = $this->previous
            ? ['bill_history.rate_95th', 'bill_history.bill_allowed', 'bill_history.traf_total', 'bill_history.bill_allowed']
            : ['bills.rate_95th', 'bills.bill_cdr', 'bills.total_data', 'bills.bill_quota'];

        return [
            'bill_type' => fn (Builder $query, string $value) => $query->where($type, $value),
            'state' => function (Builder $query, string $value) use ($type, $rate, $cdr, $traffic, $quota): void {
                $operator = $value === 'over' ? '>' : '<=';
                $query->where(function (Builder $query) use ($type, $rate, $cdr, $traffic, $quota, $operator): void {
                    $query->where(fn (Builder $q) => $q->where($type, 'cdr')->whereColumn($rate, $operator, $cdr))
                        ->orWhere(fn (Builder $q) => $q->where($type, 'quota')->whereColumn($traffic, $operator, $quota));
                });
            },
        ];
    }

    /**
     * @return Builder<Bill>
     */
    protected function baseQuery(Request $request): Builder
    {
        $this->authorize('viewAny', Bill::class);
        $this->previous = $request->input('period') === 'prev';

        $query = Bill::hasAccess($request->user());

        if ($this->previous) {
            // the last completed billing period of each bill, if it ended within the last 40 days
            $last = DB::table('bill_history')
                ->selectRaw('bill_id, MAX(bill_hist_id) AS bill_hist_id')
                ->where('bill_dateto', '<', now())
                ->where('bill_dateto', '>', now()->subDays(40))
                ->groupBy('bill_id');

            return $query->joinSub($last, 'last_history', 'last_history.bill_id', '=', 'bills.bill_id')
                ->join('bill_history', 'bill_history.bill_hist_id', '=', 'last_history.bill_hist_id')
                ->select([
                    'bills.bill_id', 'bills.bill_name', 'bills.bill_notes', 'bills.bill_day',
                    'bill_history.*',
                    DB::raw('bill_history.traf_total AS total_data'),
                    DB::raw('bill_history.traf_in AS total_data_in'),
                    DB::raw('bill_history.traf_out AS total_data_out'),
                ]);
        }

        return $query->select(['bills.*', DB::raw("IF(bills.bill_type = 'cdr', bills.bill_cdr, bills.bill_quota) AS bill_allowed")]);
    }

    /**
     * @param  Bill  $bill
     */
    public function formatItem(Model $bill): array
    {
        // columns joined from bill_history or computed in the select are not model properties
        $allowed = (int) $bill->getAttribute('bill_allowed');

        if ($this->previous) {
            [$from, $to] = [$bill->getAttribute('bill_datefrom'), $bill->getAttribute('bill_dateto')];
            $percent = $bill->getAttribute('bill_percent');
            $overuse = $bill->getAttribute('bill_overuse');
        } else {
            [$from, $to] = Billing::getDates($bill->bill_day, 0);
        }

        if ($bill->bill_type === 'cdr') {
            $used = $bill->rate_95th;
            $percent ??= Number::calculatePercent($used, $allowed);
            $overuse ??= $used - $allowed;
            $format = fn ($value) => Number::formatSi($value, 2, 0, '') . 'bps';
            $in = $format($bill->rate_95th_in);
            $out = $format($bill->rate_95th_out);
        } else {
            $used = $bill->total_data;
            $percent ??= Number::calculatePercent($used, $allowed);
            $overuse ??= $used - $allowed;
            $format = fn ($value) => Billing::formatBytes($value);
            $in = $format($bill->total_data_in);
            $out = $format($bill->total_data_out);
        }

        $colors = Color::percentage($percent, null);
        $bill_url = Url::generate(['page' => 'bill', 'bill_id' => $bill->bill_id]);
        $period = date('Y-m-d', strtotime((string) $from)) . ' to ' . date('Y-m-d', strtotime((string) $to));

        $actions = '';
        if (! $this->previous && Gate::allows('update', $bill)) {
            $edit_url = Url::generate(['page' => 'bill', 'bill_id' => $bill->bill_id, 'view' => 'edit']);
            $actions = "<a href='$edit_url'><i class='fa fa-pencil fa-lg icon-theme' title='" . __('Edit') . "' aria-hidden='true'></i> " . __('Edit') . '</a>';
        }

        return [
            'bill_name' => "<a href='$bill_url'><span class='tw:font-bold tw:text-blue-900 tw:visited:textc-blue-900 tw:dark:text-dark-white-100 tw:dark:visited:text-dark-white-100'>" . e($bill->bill_name) . '</span></a><br />' . $period,
            'notes' => e($bill->bill_notes),
            'bill_type' => $bill->bill_type === 'cdr' ? 'CDR' : 'Quota',
            'bill_allowed' => $format($allowed),
            'total_data_in' => $in,
            'total_data_out' => $out,
            'total_data' => $bill->bill_type === 'cdr' ? Billing::formatBytes($bill->total_data) : '<b>' . $format($used) . '</b>',
            'rate_95th' => $bill->bill_type === 'cdr' ? '<b>' . $format($used) . '</b>' : Number::formatSi($bill->rate_95th, 2, 0, '') . 'bps',
            'overusage' => $overuse <= 0 ? '-' : "<span style='color: #{$colors['left']}; font-weight: bold;'>" . $format($overuse) . '</span>',
            'predicted' => $format(Billing::getPredictedUsage($bill->bill_day, $used)),
            'graph' => Html::percentageBar(250, 10, $percent, null, $percent . '%', null, null, [
                'left' => $colors['left'],
                'left_text' => null,
                'right' => $colors['right'],
                'right_text' => null,
            ]),
            'actions' => $actions,
        ];
    }
}
